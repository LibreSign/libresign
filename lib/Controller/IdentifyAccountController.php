<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Middleware\Attribute\RequireManager;
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IShare;

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

	#[NoAdminRequired]
	#[RequireManager]
	public function search(string $search = '', int $page = 1, int $limit = 25): DataResponse {
		$shareTypes = $this->getShareTypes();
		$lookup = false;

		// only search for string larger than a given threshold
		$threshold = 1;
		if (strlen($search) < $threshold) {
			return new DataResponse([]);
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
				'isNoUser' => $item['value']['shareType'] !== IShare::TYPE_USER ?? false,
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
		if (!str_contains($user->getUID(), $search) && !str_contains($user->getDisplayName(), $search)) {
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
		return array_filter($list, function ($result) {
			return strlen($result['value']['shareWith']) > 0;
		});
	}

	private function excludeNotAllowed(array $list): array {
		$shareTypes = $this->getShareTypes();
		return array_filter($list, function ($result) use ($shareTypes) {
			return in_array($result['shareType'], $shareTypes);
		});
	}
}
