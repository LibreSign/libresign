<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Service\AccountFileService;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var AccountFileService */
	private $service;
	/** @var AccountFileMapper|MockObject */
	private $accountFileMapper;

	public function setUp(): void {
		$this->accountFileMapper = $this->createMock(accountFileMapper::class);
		$this->service = new AccountFileService(
			$this->accountFileMapper
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
