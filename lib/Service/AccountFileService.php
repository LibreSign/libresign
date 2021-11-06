<?php

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\AccountFile;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCP\IConfig;
use OCP\IUser;

class AccountFileService {
	/** @var AccountFileMapper */
	protected $accountFileMapper;
	/** @var IConfig */
	protected $config;
	public function __construct(
		AccountFileMapper $accountFileMapper,
		IConfig $config
	) {
		$this->accountFileMapper = $accountFileMapper;
		$this->config = $config;
	}

	public function addFile(File $file, IUser $user, string $fileType): void {
		$accountFile = new AccountFile();
		$accountFile->setFileId($file->getId());
		$accountFile->setUserId($user->getUID());
		$accountFile->setFileType($fileType);
		$this->accountFileMapper->insert($accountFile);
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array{data: array, pagination: array}
	 */
	public function accountFileList(array $filter, int $page = null, int $length = null): array {
		$page = $page ?? 1;
		$length = $length ?? $this->config->getAppValue(Application::APP_ID, 'length_of_page', 100);
		$data = $this->accountFileMapper->accountFileList($filter, $page, $length);
		$data['pagination']->setRootPath('/file/list');
		return [
			'data' => $data['data'],
			'pagination' => $data['pagination']->getPagination($page, $length)
		];
	}
}
