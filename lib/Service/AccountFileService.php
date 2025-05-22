<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\ResponseDefinitions;
use OCP\IAppConfig;
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
		$page ??= 1;
		$length ??= (int)$this->appConfig->getValueInt(Application::APP_ID, 'length_of_page', 100);
		$data = $this->accountFileMapper->accountFileList($filter, $page, $length);
		$data['pagination']->setRouteName('ocs.libresign.File.list');
		return [
			'data' => $data['data'],
			'pagination' => $data['pagination']->getPagination($page, $length, $filter)
		];
	}
}
