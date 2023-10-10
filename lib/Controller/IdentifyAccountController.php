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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Share\IShare;

class IdentifyAccountController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private ISearch $collaboratorSearch,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function search(string $search = '', int $page = 1, int $perPage = 10): DataResponse {
		$shareTypes = [
			IShare::TYPE_USER,
		];
		$lookup = false;

		// only search for string larger than a given threshold
		$threshold = 1;
		if (strlen($search) < $threshold) {
			return new DataResponse([]);
		}

		// never return more than the max. number of results configured in the config.php
		$maxResults = 25;

		$limit = min($perPage, $maxResults);
		$offset = $perPage * ($page - 1);
		[$result] = $this->collaboratorSearch->search($search, $shareTypes, $lookup, $limit, $offset);
		$return = array_merge($result['exact']['users'], $result['users']);
		$return = $this->formatForNcSelect($return);

		return new DataResponse($return);
	}

	private function formatForNcSelect(array $list): array {
		foreach ($list as $key => $item) {
			$list[$key] = [
				'id' => $item['value']['shareWith'],
				'isNoUser' => false,
				'displayName' => $item['label'],
				'subname' => $item['shareWithDisplayNameUnique'],
			];
		}
		return $list;
	}
}
