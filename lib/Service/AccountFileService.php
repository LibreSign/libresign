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

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\ResponseDefinitions;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IUser;

/**
 * @psalm-import-type LibresignFile from ResponseDefinitions
 */
class AccountFileService {
	public function __construct(
		protected AccountFileMapper $accountFileMapper,
		protected IAppConfig $appConfig,
	) {
	}

	public function addFile(File $file, IUser $user, string $fileType): void {
		$accountFile = new AccountFile();
		$accountFile->setFileId($file->getId());
		$accountFile->setUserId($user->getUID());
		$accountFile->setFileType($fileType);
		$this->accountFileMapper->insert($accountFile);
	}

	public function deleteFile(int $fileId, string $uid): void {
		$accountFile = new AccountFile();
		$accountFile->setFileId($fileId);
		$accountFile->setUserId($uid);
		$this->accountFileMapper->delete($accountFile);
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array{data: LibresignFile[], pagination: array}
	 */
	public function accountFileList(array $filter, ?int $page = null, ?int $length = null): array {
		$page = $page ?? 1;
		$length = $length ?? (int)$this->appConfig->getAppValue('length_of_page', '100');
		$data = $this->accountFileMapper->accountFileList($filter, $page, $length);
		$data['pagination']->setRouteName('ocs.libresign.File.list');
		return [
			'data' => $data['data'],
			'pagination' => $data['pagination']->getPagination($page, $length, $filter)
		];
	}
}
