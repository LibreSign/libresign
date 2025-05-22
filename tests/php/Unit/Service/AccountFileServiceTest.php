<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\File;
use OCA\Libresign\Service\AccountFileService;
use OCP\IAppConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

final class AccountFileServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private AccountFileService $service;
	private AccountFileMapper&MockObject $accountFileMapper;
	private IAppConfig $appConfig;

	public function setUp(): void {
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->service = new AccountFileService(
			$this->accountFileMapper,
			$this->appConfig
		);
	}

	public function testAddFileWithSuccess():void {
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
