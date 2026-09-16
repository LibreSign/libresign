<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\AEnvironmentPageAwareController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;

final class LibresignTraitControllerStub extends AEnvironmentPageAwareController {
}

final class LibresignTraitTest extends TestCase {
	private IRequest&MockObject $request;
	private SignFileService&MockObject $signFileService;
	private IL10N&MockObject $l10n;
	private IUserSession&MockObject $userSession;
	private LibresignTraitControllerStub $controller;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn(null);

		$this->controller = new LibresignTraitControllerStub(
			$this->request,
			$this->signFileService,
			$this->l10n,
			$this->userSession,
		);
	}

	public function testValidateSignRequestUuidRejectsObserverParticipants(): void {
		$uuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
		$signRequest = new SignRequestEntity();
		$signRequest->setFileId(10);
		$signRequest->setParticipantRole(ParticipantRole::OBSERVER->value);
		$file = new FileEntity();

		$this->signFileService->expects($this->once())
			->method('getSignRequestByUuid')
			->with($uuid)
			->willReturn($signRequest);
		$this->signFileService->expects($this->once())
			->method('getFile')
			->with(10)
			->willReturn($file);
		$this->signFileService->expects($this->once())
			->method('validateSigner')
			->with($uuid, null)
			->willThrowException(new LibresignException(json_encode([
				'action' => 2000,
				'errors' => [['message' => 'Observers cannot sign this document']],
			])));

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Observers cannot sign this document');

		$this->controller->validateSignRequestUuid($uuid);
	}

	public function testValidateParticipantUuidAllowsObserverParticipants(): void {
		$uuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
		$signRequest = new SignRequestEntity();
		$signRequest->setFileId(11);
		$signRequest->setParticipantRole(ParticipantRole::OBSERVER->value);
		$file = new FileEntity();

		$this->signFileService->expects($this->once())
			->method('getSignRequestByUuid')
			->with($uuid)
			->willReturn($signRequest);
		$this->signFileService->expects($this->once())
			->method('getFile')
			->with(11)
			->willReturn($file);
		$this->signFileService->expects($this->never())
			->method('validateSigner');

		$this->controller->validateParticipantUuid($uuid);

		$this->assertSame($signRequest, $this->controller->getSignRequestEntity());
		$this->assertSame($file, $this->controller->getFileEntity());
	}
}
