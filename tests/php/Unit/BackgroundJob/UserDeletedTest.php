<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\UserDeleted;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Service\CrlService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class UserDeletedTest extends TestCase {
	private FileMapper&MockObject $fileMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private UserElementMapper&MockObject $userElementMapper;
	private CrlService&MockObject $crlService;
	private ITimeFactory&MockObject $time;
	private LoggerInterface&MockObject $logger;
	private UserDeleted $job;

	public function setUp(): void {
		parent::setUp();

		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->crlService = $this->createMock(CrlService::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->job = new UserDeleted(
			$this->fileMapper,
			$this->identifyMethodMapper,
			$this->userElementMapper,
			$this->crlService,
			$this->time,
			$this->logger
		);
	}

	public static function userDeletedScenariosProvider(): array {
		return [
			'missing userId' => [
				'argument' => [],
				'revokedCount' => null,
				'throwException' => false,
				'expectNeutralization' => false,
				'expectInfoLogs' => 0,
				'expectErrorLog' => false,
			],
			'successful revocation and neutralization' => [
				'argument' => ['user_id' => 'user123', 'display_name' => 'John Doe'],
				'revokedCount' => 3,
				'throwException' => false,
				'expectNeutralization' => true,
				'expectInfoLogs' => 2,
				'expectErrorLog' => false,
			],
			'no certificates to revoke' => [
				'argument' => ['user_id' => 'user456', 'display_name' => 'Jane Smith'],
				'revokedCount' => 0,
				'throwException' => false,
				'expectNeutralization' => true,
				'expectInfoLogs' => 1,
				'expectErrorLog' => false,
			],
			'revocation fails but continues neutralization' => [
				'argument' => ['user_id' => 'user789', 'display_name' => 'Bob Johnson'],
				'revokedCount' => null,
				'throwException' => true,
				'expectNeutralization' => true,
				'expectInfoLogs' => 1,
				'expectErrorLog' => true,
			],
		];
	}

	#[DataProvider('userDeletedScenariosProvider')]
	public function testRun(
		array $argument,
		?int $revokedCount,
		bool $throwException,
		bool $expectNeutralization,
		int $expectInfoLogs,
		bool $expectErrorLog,
	): void {
		$userId = $argument['user_id'] ?? null;
		$displayName = $argument['display_name'] ?? null;

		if ($expectInfoLogs > 0) {
			$this->logger->expects($this->exactly($expectInfoLogs))
				->method('info');
		} else {
			$this->logger->expects($this->never())
				->method('info');
		}

		if ($expectErrorLog) {
			$this->logger->expects($this->once())
				->method('error')
				->with(
					'Failed to revoke certificates for deleted user {user}: {error}',
					$this->callback(fn ($context) => isset($context['user']) && isset($context['error']))
				);
		} else {
			$this->logger->expects($this->never())
				->method('error');
		}

		if ($userId === null) {
			$this->crlService->expects($this->never())
				->method($this->anything());
		} else {
			if ($throwException) {
				$this->crlService->expects($this->once())
					->method('revokeUserCertificates')
					->with(
						$userId,
						CRLReason::CESSATION_OF_OPERATION,
						'User account deleted',
						'system'
					)
					->willThrowException(new \Exception('Certificate service unavailable'));
			} else {
				$this->crlService->expects($this->once())
					->method('revokeUserCertificates')
					->with(
						$userId,
						CRLReason::CESSATION_OF_OPERATION,
						'User account deleted',
						'system'
					)
					->willReturn($revokedCount);
			}
		}

		if ($expectNeutralization) {
			$this->fileMapper->expects($this->once())
				->method('neutralizeDeletedUser')
				->with($userId, $displayName);

			$this->identifyMethodMapper->expects($this->once())
				->method('neutralizeDeletedUser')
				->with($userId, $displayName);

			$this->userElementMapper->expects($this->once())
				->method('neutralizeDeletedUser')
				->with($userId, $displayName);
		} else {
			$this->fileMapper->expects($this->never())
				->method('neutralizeDeletedUser');

			$this->identifyMethodMapper->expects($this->never())
				->method('neutralizeDeletedUser');

			$this->userElementMapper->expects($this->never())
				->method('neutralizeDeletedUser');
		}

		self::invokePrivate($this->job, 'run', [$argument]);
	}
}
