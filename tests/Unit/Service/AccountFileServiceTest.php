<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Service\AccountFileService;
use OCP\IConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private AccountFileService $service;
	private AccountFileMapper|MockObject $accountFileMapper;
	private IConfig $config;

	public function setUp(): void {
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->service = new AccountFileService(
			$this->accountFileMapper,
			$this->config
		);
	}

	public function testAddFileWithSuccess() {
		$file = $this->createMock(File::class);
		$file->method('__call')
			->with($this->equalTo('getId'), $this->anything())
			->willReturn(1);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('username');
		$actual = $this->service->addFile($file, $user, 'FAKE_TYPE');
		$this->assertNull($actual);
	}
}
