<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCP\IUser;

class AccountFileService {
	/** @var AccountFileMapper */
	protected $accountFileMapper;
	public function __construct(
		AccountFileMapper $accountFileMapper
	) {
		$this->accountFileMapper = $accountFileMapper;
	}

	public function addFile(File $file, IUser $user, string $fileType) {
		$accountFile = new AccountFile();
		$accountFile->setFileId($file->getId());
		$accountFile->setUserId($user->getUID());
		$accountFile->setFileType($fileType);
		$this->accountFileMapper->insert($accountFile);
	}
}
