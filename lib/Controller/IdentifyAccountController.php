<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
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
	public function search(string $search = '', int $page = 1, int $limit = 25): DataResponse {
		$shareTypes = $this->getShareTypes();
		$lookup = false;

		// only search for string larger than a given threshold
		$threshold = 1;
		if (strlen($search) < $threshold) {
			return new DataResponse();
		}

		$offset = $limit * ($page - 1);
		[$result] = $this->collaboratorSearch->search($search, $shareTypes, $lookup, $limit, $offset);
		$result['exact'] = $this->unifyResult($result['exact']);
		$result = $this->unifyResult($result);
		$result = $this->excludeEmptyShareWith($result);
		$return = $this->formatForNcSelect($result);
		$return = $this->addHerselfAccount($return, $search);
		$return = $this->addHerselfEmail($return, $search);
		$return = $this->excludeNotAllowed($return);

		return new DataResponse($return);
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
		foreach ($list as $key => $item) {
			$list[$key] = [
				'id' => $item['value']['shareWith'],
				'isNoUser' => $item['value']['shareType'] !== IShare::TYPE_USER,
				'displayName' => $item['label'],
				'subname' => $item['shareWithDisplayNameUnique'] ?? '',
				'shareType' => $item['value']['shareType'],
			];
			if ($item['value']['shareType'] === IShare::TYPE_EMAIL) {
				$list[$key]['icon'] = 'icon-mail';
			} elseif ($item['value']['shareType'] === IShare::TYPE_USER) {
				$list[$key]['icon'] = 'icon-user';
			}
		}
		return $list;
	}

	private function addHerselfAccount(array $return, string $search): array {
		$settings = $this->identifyAccountMethod->getSettings();
		if (empty($settings['enabled'])) {
			return $return;
		}
		$user = $this->userSession->getUser();
		if (!str_contains($user->getUID(), $search) && !str_contains(strtolower($user->getDisplayName()), $search)) {
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
			'shareType' => IShare::TYPE_USER,
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
		if (!str_contains($user->getEMailAddress(), $search) && !str_contains($user->getDisplayName(), $search)) {
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
			'shareType' => IShare::TYPE_EMAIL,
		];
		return $return;
	}

	private function excludeEmptyShareWith(array $list): array {
		return array_filter($list, fn ($result) => strlen((string)$result['value']['shareWith']) > 0);
	}

	private function excludeNotAllowed(array $list): array {
		$shareTypes = $this->getShareTypes();
		return array_filter($list, fn ($result) => in_array($result['shareType'], $shareTypes));
	}
}
