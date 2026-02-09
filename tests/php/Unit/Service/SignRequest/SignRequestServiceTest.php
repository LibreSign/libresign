<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OCA\Libresign\Db\IdentifyMethod as IdentifyMethodEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignRequest\SignRequestService;
use OCA\Libresign\Service\SignRequest\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignRequestServiceTest extends TestCase {
	private IL10N&MockObject $l10n;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserManager&MockObject $userManager;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private StatusService&MockObject $statusService;
	private SignRequestService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->statusService = $this->createMock(StatusService::class);

		$this->service = new SignRequestService(
			$this->l10n,
			$this->signRequestMapper,
			$this->userManager,
			$this->identifyMethodService,
			$this->statusService,
		);
	}

	public function testCreateOrUpdateSignRequestThrowsOnInvalidIdentifyMethod(): void {
		$this->identifyMethodService->method('getByUserData')->willReturn([]);

		$this->expectExceptionMessage('Invalid identification method');

		$this->service->createOrUpdateSignRequest(
			[],
			'',
			'',
			false,
			99,
		);
	}

	public function testCreateOrUpdateSignRequestCreatesNewRequestAndNotifies(): void {
		$identifyMethod = $this->createIdentifyMethod('email', 'signer@example.com');
		$this->identifyMethodService->method('getByUserData')
			->willReturn([$identifyMethod]);

		$this->signRequestMapper->method('getByIdentifyMethodAndFileId')
			->willThrowException(new DoesNotExistException('not found'));

		$this->statusService->expects($this->once())
			->method('determineInitialStatus')
			->willReturn(SignRequestStatus::ABLE_TO_SIGN);

		$this->statusService->expects($this->once())
			->method('updateStatusIfAllowed');

		$this->statusService->method('shouldNotifySignRequest')->willReturn(true);

		$this->signRequestMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (SignRequestEntity $request): SignRequestEntity {
				$request->setId(10);
				return $request;
			});

		$identifyMethod->expects($this->once())
			->method('willNotifyUser')
			->with(true);
		$identifyMethod->expects($this->once())
			->method('save');

		$signRequest = $this->service->createOrUpdateSignRequest(
			[['email' => 'signer@example.com']],
			'Signer Name',
			'Please sign',
			true,
			42,
			1,
			null,
			null,
		);

		$this->assertSame(42, $signRequest->getFileId());
		$this->assertSame('Signer Name', $signRequest->getDisplayName());
		$this->assertSame('Please sign', $signRequest->getDescription());
		$this->assertSame(10, $signRequest->getId());
	}

	public function testCreateOrUpdateSignRequestUsesAccountDisplayNameWhenMissing(): void {
		$identifyMethod = $this->createIdentifyMethod('account', 'john.doe');
		$this->identifyMethodService->method('getByUserData')
			->willReturn([$identifyMethod]);

		$this->signRequestMapper->method('getByIdentifyMethodAndFileId')
			->willThrowException(new DoesNotExistException('not found'));

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('John Doe');
		$this->userManager->method('get')->with('john.doe')->willReturn($user);

		$this->statusService->method('determineInitialStatus')
			->willReturn(SignRequestStatus::ABLE_TO_SIGN);
		$this->statusService->method('shouldNotifySignRequest')->willReturn(false);

		$this->signRequestMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (SignRequestEntity $request): SignRequestEntity {
				$request->setId(5);
				return $request;
			});

		$signRequest = $this->service->createOrUpdateSignRequest(
			[['account' => 'john.doe']],
			'',
			'',
			false,
			7,
		);

		$this->assertSame('John Doe', $signRequest->getDisplayName());
	}

	public function testCreateOrUpdateSignRequestUpdatesExisting(): void {
		$identifyMethod = $this->createIdentifyMethod('email', 'signer@example.com');
		$this->identifyMethodService->method('getByUserData')
			->willReturn([$identifyMethod]);

		$existing = new SignRequestEntity();
		$existing->setId(77);
		$existing->setStatusEnum(SignRequestStatus::DRAFT);

		$this->signRequestMapper->method('getByIdentifyMethodAndFileId')
			->willReturn($existing);

		$this->statusService->method('determineInitialStatus')
			->willReturn(SignRequestStatus::ABLE_TO_SIGN);
		$this->statusService->method('shouldNotifySignRequest')->willReturn(false);

		$this->signRequestMapper->expects($this->once())
			->method('update')
			->with($this->isInstanceOf(SignRequestEntity::class));

		$signRequest = $this->service->createOrUpdateSignRequest(
			[['email' => 'signer@example.com']],
			'Existing',
			'',
			false,
			101,
		);

		$this->assertSame(77, $signRequest->getId());
	}

	private function createIdentifyMethod(string $name, string $identifier): IIdentifyMethod&MockObject {
		$entity = new IdentifyMethodEntity();
		$entity->setIdentifierValue($identifier);

		$identifyMethod = $this->createMock(IIdentifyMethod::class);
		$identifyMethod->method('getName')->willReturn($name);
		$identifyMethod->method('getEntity')->willReturn($entity);

		return $identifyMethod;
	}
}
