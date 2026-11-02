<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\SignatureElementsController;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\EmailSignerElementsService;
use OCA\Libresign\Service\IdentifyMethod\AbstractIdentifyMethod;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Preview\IMimeIconProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SignatureElementsControllerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private EmailSignerElementsService&MockObject $emailSignerElementsService;
	private IUserSession&MockObject $userSession;
	private LoggerInterface&MockObject $logger;
	private AccountService&MockObject $accountService;

	protected function setUp(): void {
		parent::setUp();
		$this->emailSignerElementsService = $this->createMock(EmailSignerElementsService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->accountService = $this->createMock(AccountService::class);
	}

	private function getController(?SignRequest $signRequest = null): SignatureElementsController {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$controller = new SignatureElementsController(
			$this->createMock(IRequest::class),
			$l10n,
			$this->accountService,
			$this->createMock(SignerElementsService::class),
			$this->createMock(SignatureTextService::class),
			$this->userSession,
			$this->createMock(SessionService::class),
			$this->createMock(SignFileService::class),
			$this->createMock(IPreview::class),
			$this->createMock(IMimeIconProvider::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(VisibleElementValidator::class),
			$this->emailSignerElementsService,
			$this->logger,
		);
		self::invokePrivate($controller, 'signRequestEntity', [$signRequest]);
		return $controller;
	}

	public function testSavedSignatureRequiresAnAuthorizedSigningRequest(): void {
		$this->emailSignerElementsService->expects($this->never())->method('unlock');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $this->getController()->useSavedSignatureElements()->getStatus());
	}

	public function testAccountSignerCannotLoadExternalEmailSignatures(): void {
		$this->userSession->method('getUser')->willReturn($this->createMock(IUser::class));
		$this->emailSignerElementsService->expects($this->never())->method('unlock');

		$response = $this->getController(new SignRequest())->useSavedSignatureElements();

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testSavedSignatureIsLoadedOnlyForTheRequestResolvedByTheBackend(): void {
		$signRequest = new SignRequest();
		$elements = [['type' => 'signature']];
		$this->emailSignerElementsService->expects($this->once())->method('unlock')
			->with($signRequest, '123456')->willReturn($elements);

		$response = $this->getController($signRequest)->useSavedSignatureElements('123456');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['elements' => $elements], $response->getData());
	}

	public function testInvalidCodeDoesNotReturnSavedElements(): void {
		$this->emailSignerElementsService->method('unlock')->willThrowException(new LibresignException('Invalid code.', AbstractIdentifyMethod::CODE_INVALID_TOKEN));

		$response = $this->getController(new SignRequest())->useSavedSignatureElements('000000');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame(['message' => 'Invalid code.'], $response->getData());
		$this->assertTrue($response->isThrottled());
	}

	public function testUnexpectedStorageErrorsAreNotReportedAsInvalidVerification(): void {
		$exception = new \RuntimeException('Internal storage path and email');
		$this->emailSignerElementsService->method('unlock')->willThrowException($exception);
		$this->logger->expects($this->once())->method('error')->with(
			'Unable to load saved email signature elements', ['exception' => $exception],
		);

		$this->expectExceptionObject($exception);
		$this->getController(new SignRequest())->useSavedSignatureElements();
	}

	public function testMissingSavedSignatureDoesNotCountAsInvalidCredentials(): void {
		$this->emailSignerElementsService->method('unlock')->willThrowException(new LibresignException('No saved signature found'));

		$response = $this->getController(new SignRequest())->useSavedSignatureElements('123456');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertFalse($response->isThrottled());
	}

	public function testSavedCopyStorageFailurePreservesSessionElementForRetry(): void {
		$exception = new \RuntimeException('Storage unavailable');
		$this->emailSignerElementsService->expects($this->once())->method('forgetSessionElement')
			->with(10)->willThrowException($exception);
		$this->accountService->expects($this->never())->method('deleteSignatureElement');
		$this->logger->expects($this->once())->method('error')
			->with('Unable to delete signature element', ['exception' => $exception]);

		$this->expectExceptionObject($exception);
		$this->getController()->deleteSignatureElement(10);
	}

	public function testMissingSessionElementStillReturnsNotFound(): void {
		$this->accountService->method('deleteSignatureElement')
			->willThrowException(new DoesNotExistException('Element not found'));

		$response = $this->getController()->deleteSignatureElement(10);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testAccountElementDeletionDoesNotAccessEmailStorage(): void {
		$user = $this->createMock(IUser::class);
		$this->userSession->method('getUser')->willReturn($user);
		$this->emailSignerElementsService->expects($this->never())->method('forgetSessionElement');
		$this->accountService->expects($this->once())->method('deleteSignatureElement')->with($user, $this->anything(), 10);

		$this->assertSame(Http::STATUS_OK, $this->getController()->deleteSignatureElement(10)->getStatus());
	}
}
