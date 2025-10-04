<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Collaboration\Collaborators\SignerPlugin;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IShare;

/**
 * @psalm-import-type LibresignIdentifyAccount from ResponseDefinitions
 */
class IdentifyAccountController extends AEnvironmentAwareController {
	private array $shareTypes = [];
	public function __construct(
		IRequest $request,
		private ISearch $collaboratorSearch,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private Email $identifyEmailMethod,
		private Account $identifyAccountMethod,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List possible signers
	 *
	 * Used to identify who can sign the document. The return of this endpoint is related with Administration Settiongs > LibreSign > Identify method.
	 *
	 * @param string $search search params
	 * @param string $method filter by method (email, account, sms, signal, telegram, whatsapp, xmpp)
	 * @param int $page the number of page to return. Default: 1
	 * @param int $limit Total of elements to return. Default: 25
	 * @return DataResponse<Http::STATUS_OK, LibresignIdentifyAccount[], array{}>
	 *
	 * 200: Certificate saved with success
	 * 400: No file provided or other problem with provided file
	 */
	#[NoAdminRequired]
	#[RequireManager]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/identify-account/search', requirements: ['apiVersion' => '(v1)'])]
	public function search(string $search = '', string $method = '', int $page = 1, int $limit = 25): DataResponse {
		// only search for string larger than a minimum length
		if (strlen($search) < 1) {
			return new DataResponse();
		}

		$shareTypes = $this->getShareTypes();
		$lookup = false;

		$offset = $limit * ($page - 1);
		$this->registerPlugin($method);
		[$result] = $this->collaboratorSearch->search($search, $shareTypes, $lookup, $limit, $offset);
		$result['exact'] = $this->unifyResult($result['exact']);
		$result = $this->unifyResult($result);
		$result = $this->excludeEmptyShareWith($result);
		$return = $this->formatForNcSelect($result);
		$return = $this->addHerselfAccount($return, $search);
		$return = $this->addHerselfEmail($return, $search);
		$return = $this->replaceShareTypeByMethod($return);
		$return = $this->excludeNotAllowed($return);

		return new DataResponse($return);
	}

	private function registerPlugin(string $method): void {
		SignerPlugin::setMethod($method);

		$refObject = new \ReflectionObject($this->collaboratorSearch);
		$refProperty = $refObject->getProperty('pluginList');

		$plugins = $refProperty->getValue($this->collaboratorSearch);
		$plugins[SignerPlugin::TYPE_SIGNER] = [SignerPlugin::class];

		$refProperty->setValue($this->collaboratorSearch, $plugins);
	}

	private function getShareTypes(): array {
		if (count($this->shareTypes) > 0) {
			return $this->shareTypes;
		}
		$settings = $this->identifyEmailMethod->getSettings();
		if ($settings['enabled']) {
			$this->shareTypes[] = IShare::TYPE_EMAIL;
		}
		$settings = $this->identifyAccountMethod->getSettings();
		if ($settings['enabled']) {
			$this->shareTypes[] = IShare::TYPE_USER;
		}

		$this->shareTypes[] = SignerPlugin::TYPE_SIGNER;
		return $this->shareTypes;
	}

	private function unifyResult(array $list): array {
		$ids = [];
		$return = [];
		foreach ($list as $items) {
			foreach ($items as $item) {
				if (in_array($item['value']['shareWith'], $ids)) {
					continue;
				}
				$ids[] = $item['value']['shareWith'];
				$return[] = $item;
			}
		}
		return $return;
	}

	private function formatForNcSelect(array $list): array {
		$formattedList = [];
		foreach ($list as $key => $item) {
			$formattedList[$key] = [
				'id' => $item['value']['shareWith'],
				'isNoUser' => $item['value']['shareType'] !== IShare::TYPE_USER
					&& isset($item['method'])
					&& $item['method'] !== 'account',
				'displayName' => $item['label'],
				'subname' => $item['shareWithDisplayNameUnique'] ?? '',
			];
			if ($item['value']['shareType'] === IShare::TYPE_EMAIL) {
				$formattedList[$key]['method'] = 'email';
				$formattedList[$key]['icon'] = 'icon-mail';
			} elseif ($item['value']['shareType'] === IShare::TYPE_USER) {
				$formattedList[$key]['method'] = 'account';
				$formattedList[$key]['icon'] = 'icon-user';
			} elseif ($item['value']['shareType'] === SignerPlugin::TYPE_SIGNER) {
				$formattedList[$key]['method'] = $item['method'] ?? '';
				if ($item['method'] === 'email') {
					$formattedList[$key]['icon'] = 'icon-mail';
				} elseif ($item['method'] === 'account') {
					$formattedList[$key]['icon'] = 'icon-user';
				} else {
					$formattedList[$key]['iconSvg'] = 'svg' . ucfirst($item['method']);
					$formattedList[$key]['iconName'] = $item['method'];
				}
			}
		}
		return $formattedList;
	}

	private function addHerselfAccount(array $return, string $search): array {
		$settings = $this->identifyAccountMethod->getSettings();
		if (empty($settings['enabled'])) {
			return $return;
		}
		$user = $this->userSession->getUser();
		$search = strtolower($search);
		if (!str_contains($user->getUID(), $search)
			&& !str_contains(strtolower($user->getDisplayName()), $search)
			&& (
				$user->getEMailAddress() === null
				|| ($user->getEMailAddress() !== null && !str_contains($user->getEMailAddress(), $search))
			)
		) {
			return $return;
		}
		$filtered = array_filter($return, fn ($i) => $i['id'] === $user->getUID());
		if (count($filtered)) {
			return $return;
		}
		$return[] = [
			'id' => $user->getUID(),
			'isNoUser' => false,
			'displayName' => $user->getDisplayName(),
			'subname' => $user->getEMailAddress(),
			'icon' => 'icon-user',
			'method' => 'account',
		];
		return $return;
	}

	private function addHerselfEmail(array $return, string $search): array {
		$settings = $this->identifyEmailMethod->getSettings();
		if (empty($settings['enabled'])) {
			return $return;
		}
		$user = $this->userSession->getUser();
		if (empty($user->getEMailAddress())) {
			return $return;
		}
		if (!str_contains($user->getEMailAddress(), $search)
			&& !str_contains($user->getDisplayName(), $search)
		) {
			return $return;
		}
		$filtered = array_filter($return, fn ($i) => $i['id'] === $user->getUID());
		if (count($filtered)) {
			return $return;
		}
		$return[] = [
			'id' => $user->getEMailAddress(),
			'isNoUser' => true,
			'displayName' => $user->getDisplayName(),
			'subname' => $user->getEMailAddress(),
			'icon' => 'icon-mail',
			'method' => 'email',
		];
		return $return;
	}

	private function excludeEmptyShareWith(array $list): array {
		return array_filter($list, fn ($result) => strlen((string)$result['value']['shareWith']) > 0);
	}

	private function excludeNotAllowed(array $list): array {
		return array_filter($list, fn ($result) => isset($result['method']) && !empty($result['method']));
	}

	private function replaceShareTypeByMethod(array $list): array {
		foreach ($list as $key => $item) {
			if (isset($item['method']) && !empty($item['method'])) {
				continue;
			}
			$list[$key]['method'] = match ($item['shareType']) {
				IShare::TYPE_EMAIL => 'email',
				IShare::TYPE_USER => 'account',
				default => '',
			};
			unset($list[$key]['shareType']);
		}
		return $list;
	}
}
