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
		$return = $this->formatForNcSelect($result);
		$return = $this->addHerself($return, $search);
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

	private function formatForNcSelect(array $list): array {
		$return = [];
		foreach ($list['exact'] as $item) {
			$return = array_merge($return, $item);
		}
		unset($list['exact']);
		foreach ($list as $item) {
			$return = array_merge($return, $item);
		}

		foreach ($return as $key => $item) {
			$return[$key] = [
				'id' => $item['value']['shareWith'],
				'isNoUser' => $item['value']['shareType'] !== IShare::TYPE_USER ?? false,
				'displayName' => $item['label'],
				'subname' => $item['shareWithDisplayNameUnique'],
				'shareType' => $item['value']['shareType'],
			];
			if ($item['value']['shareType'] === IShare::TYPE_EMAIL) {
				$return[$key]['icon'] = 'icon-mail';
			} elseif ($item['value']['shareType'] === IShare::TYPE_USER) {
				$return[$key]['icon'] = 'icon-user';
			}
		}
		return $return;
	}

	private function addHerself(array $return, string $search): array {
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
		];
		return $return;
	}

	private function excludeNotAllowed(array $list): array {
		$shareTypes = $this->getShareTypes();
		return array_filter($list, function($result) use($shareTypes) {
			return in_array($result['shareType'], $shareTypes);
		});
	}
}
