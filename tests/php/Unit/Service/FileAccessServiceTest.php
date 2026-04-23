<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\FileAccessService;
use OCA\Libresign\Service\SignFileService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FileAccessServiceTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private SignFileService&MockObject $signFileService;
	private IUserSession&MockObject $userSession;
	private FileAccessService $service;

	protected function setUp(): void {
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->service = new FileAccessService(
			$this->fileMapper,
			$this->signFileService,
			$this->userSession,
		);
	}

	#[DataProvider('provideFileAccessScenarios')]
	public function testUserCanAccessFileUsesOwnershipAndSignerRules(
		string $method,
		string $mapperMethod,
		int $identifier,
		string $ownerId,
		string $userId,
		bool $canSign,
		bool $expected,
	): void {
		$user = $this->mockUser($userId);
		$file = $this->createFileEntity(ownerId: $ownerId);

		$this->fileMapper->expects($this->once())
			->method($mapperMethod)
			->with($identifier)
			->willReturn($file);

		$expectsSignerLookup = $ownerId !== $userId;
		if (!$expectsSignerLookup) {
			$this->signFileService->expects($this->never())->method('getSignRequestToSign');
		} elseif ($canSign) {
			$this->signFileService->expects($this->once())
				->method('getSignRequestToSign')
				->with($file, null, $user);
		} else {
			$this->signFileService->expects($this->once())
				->method('getSignRequestToSign')
				->with($file, null, $user)
				->willThrowException(new \RuntimeException('not a signer'));
		}

		$this->assertSame($expected, $this->service->{$method}($identifier, $user));
	}

	#[DataProvider('provideMissingUserScenarios')]
	public function testUserCanAccessFileReturnsFalseWithoutResolvedUser(
		string $method,
		string $mapperMethod,
		int $identifier,
	): void {
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);
		$this->fileMapper->expects($this->never())->method($mapperMethod);

		$this->assertFalse($this->service->{$method}($identifier));
	}

	public static function provideFileAccessScenarios(): array {
		return [
			'owner by file id' => ['userCanAccessFileById', 'getById', 10, 'owner', 'owner', false, true],
			'signer by file id' => ['userCanAccessFileById', 'getById', 20, 'owner', 'signer', true, true],
			'outsider by file id' => ['userCanAccessFileById', 'getById', 30, 'owner', 'outsider', false, false],
			'signer by node id' => ['userCanAccessFileByNodeId', 'getByNodeId', 99, 'owner', 'signer', true, true],
			'outsider by node id' => ['userCanAccessFileByNodeId', 'getByNodeId', 100, 'owner', 'outsider', false, false],
		];
	}

	public static function provideMissingUserScenarios(): array {
		return [
			'file id without user' => ['userCanAccessFileById', 'getById', 40],
			'node id without user' => ['userCanAccessFileByNodeId', 'getByNodeId', 41],
		];
	}

	private function mockUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	private function createFileEntity(string $ownerId): FileEntity {
		$file = new FileEntity();
		$file->setUserId($ownerId);
		return $file;
	}
}
