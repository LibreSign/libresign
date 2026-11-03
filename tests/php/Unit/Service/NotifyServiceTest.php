<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\NotifyService;
use OCA\Libresign\Service\SignerIndexProvider;
use OCA\Libresign\Service\SignerNotificationPolicy;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;
use PHPUnit\Framework\TestCase;

final class NotifyServiceTest extends TestCase {
	private SigningRequestValidator $validator;
	private SignRequestMapper $requests;
	private IdentifyMethodService $identifiers;
	private SignerIndexProvider $index;
	private NotifyService $service;

	protected function setUp(): void {
		$this->validator = $this->createMock(SigningRequestValidator::class);
		$this->requests = $this->createMock(SignRequestMapper::class);
		$this->identifiers = $this->createMock(IdentifyMethodService::class);
		$this->index = $this->createMock(SignerIndexProvider::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		$this->service = new NotifyService(
			$this->validator, $this->createMock(FileInputValidator::class),
			$this->createMock(IdentityDocumentValidator::class), $session,
			$this->requests, $this->identifiers, $this->index,
			$this->createMock(SignerNotificationPolicy::class),
			$this->createMock(ITimeFactory::class), $this->createMock(IManager::class), $l10n,
		);
	}

	private function request(int $status = 1): SignRequest {
		$request = new SignRequest();
		$request->setId(23);
		$request->setFileId(12);
		$request->setStatus($status);
		return $request;
	}

	public function testAuthorizationPrecedesSignerLookup(): void {
		$this->validator->method('iRequestedSignThisFile')
			->willThrowException(new LibresignException('not authorized'));
		$this->requests->expects($this->never())->method('getByFileIdAndSignRequestId');
		$this->expectException(LibresignException::class);
		$this->service->signer(12, 23);
	}

	public function testCompletedOrDraftRequestsCannotReceiveManualReminders(): void {
		foreach ([SignRequestStatus::SIGNED, SignRequestStatus::REJECTED, SignRequestStatus::DRAFT] as $status) {
			$requests = $this->createMock(SignRequestMapper::class);
			$requests->method('getByFileIdAndSignRequestId')->willReturn($this->request($status->value));
			$identifiers = $this->createMock(IdentifyMethodService::class);
			$identifiers->expects($this->never())->method('getIdentifyMethodsFromSignRequestId');
			$service = $this->serviceFor($requests, $identifiers);
			try {
				$service->signer(12, 23);
				$this->fail('A completed or draft request must not be notified');
			} catch (LibresignException $e) {
				$this->assertStringContainsString('not waiting', $e->getMessage());
			}
		}
	}

	private function serviceFor(SignRequestMapper $mapper, IdentifyMethodService $identifiers): NotifyService {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		return new NotifyService(
			$this->createMock(SigningRequestValidator::class),
			$this->createMock(FileInputValidator::class),
			$this->createMock(IdentityDocumentValidator::class),
			$session, $mapper, $identifiers, $this->createMock(SignerIndexProvider::class),
			$this->createMock(SignerNotificationPolicy::class),
			$this->createMock(ITimeFactory::class), $this->createMock(IManager::class), $l10n,
		);
	}

	public function testBulkNotifyOnlySelectedRecipient(): void {
		$request = $this->request(SignRequestStatus::ABLE_TO_SIGN->value);
		$this->requests->method('getByFileId')->willReturn([$request]);
		$this->index->method('build')->willReturn(['email:selected@example.invalid' => [$request]]);
		$first = new IdentifyMethod();
		$first->setIdentifierKey('email');
		$first->setIdentifierValue('selected@example.invalid');
		$other = new IdentifyMethod();
		$other->setIdentifierKey('email');
		$other->setIdentifierValue('other@example.invalid');
		$selected = $this->createMock(IIdentifyMethod::class);
		$selected->method('getEntity')->willReturn($first);
		$selected->expects($this->once())->method('notify')->with($this->callback(
			static fn (string $eventId): bool => str_starts_with($eventId, 'manual:')
		))->willReturn(true);
		$unselected = $this->createMock(IIdentifyMethod::class);
		$unselected->method('getEntity')->willReturn($other);
		$unselected->expects($this->never())->method('notify');
		$this->identifiers->method('getIdentifyMethodsFromSignRequestId')->willReturn([
			'email' => [$selected, $unselected],
		]);
		$this->service->signers(12, [['email' => 'selected@example.invalid']]);
	}

	public function testInvalidManualRequestIdDoesNotNotify(): void {
		$this->requests->method('getByFileIdAndSignRequestId')
			->willReturn($this->request(SignRequestStatus::ABLE_TO_SIGN->value));
		$this->identifiers->expects($this->never())->method('getIdentifyMethodsFromSignRequestId');
		$this->expectException(LibresignException::class);
		$this->service->signer(12, 23, 'invalid');
	}

	public function testManualRequestIdIsReusedForTheSameAttempt(): void {
		$this->requests->method('getByFileIdAndSignRequestId')
			->willReturn($this->request(SignRequestStatus::ABLE_TO_SIGN->value));
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('email');
		$entity->setIdentifierValue('synthetic@example.invalid');
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($entity);
		$method->expects($this->once())->method('notify')
			->with('manual:' . str_repeat('a', 32))->willReturn(true);
		$this->identifiers->method('getIdentifyMethodsFromSignRequestId')->willReturn(['email' => [$method]]);
		$this->service->signer(12, 23, str_repeat('a', 32));
	}

	public function testEmptyBulkRequestCannotNotifyEveryone(): void {
		$this->requests->expects($this->never())->method('getByFileId');
		$this->expectException(LibresignException::class);
		$this->service->signers(12, []);
	}
}
