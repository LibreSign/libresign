<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\SignFileController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Handler\SigningErrorHandler;
use OCA\Libresign\Service\AsyncSigningService;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestMetadataService;
use OCA\Libresign\Service\SignatureRejection\SignatureRejectionService;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SignFileControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IL10N&MockObject $l10n;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserSession&MockObject $userSession;
	private SigningRequestValidator&MockObject $signingRequestValidator;
	private SignFileService&MockObject $signFileService;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->signingRequestValidator = $this->createMock(SigningRequestValidator::class);
		$this->signFileService = $this->createMock(SignFileService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getController(): SignFileController {
		return new SignFileController(
			$this->request,
			$this->l10n,
			$this->signRequestMapper,
			$this->userSession,
			$this->createMock(IdentityDocumentValidator::class),
			$this->createMock(VisibleElementValidator::class),
			$this->createMock(SignerValidator::class),
			$this->signingRequestValidator,
			$this->signFileService,
			$this->createMock(IdentifyMethodService::class),
			$this->createMock(FileService::class),
			$this->createMock(SettingsLoader::class),
			$this->createMock(WorkerHealthService::class),
			$this->createMock(AsyncSigningService::class),
			$this->createMock(RequestMetadataService::class),
			$this->createMock(SignerGeolocationMetadataValidator::class),
			$this->createMock(SigningErrorHandler::class),
			$this->createMock(SignatureRejectionService::class),
			$this->logger,
		);
	}

	/**
	 * Regression for #8365: the approver requests the code with the uuid of
	 * the identification document, not with a signer uuid. The code must go
	 * to the approver's own sign request, resolved the same way sign() does.
	 */
	public function testRequestCodeBySignerUuidInIdDocApprovalContextUsesTheApproverSignRequest(): void {
		$approver = $this->createMock(IUser::class);
		$this->userSession->method('getUser')->willReturn($approver);
		$this->request->method('getParam')->willReturnMap([
			['idDocApproval', null, 'true'],
		]);

		$idDoc = new FileEntity();
		$idDoc->setId(10);
		$approverSignRequest = new SignRequest();
		$approverSignRequest->setId(77);
		$approverSignRequest->setFileId(10);

		$this->signFileService->expects($this->once())
			->method('getFileByUuid')
			->with('file-uuid')
			->willReturn($idDoc);
		$this->signFileService->expects($this->once())
			->method('getSignRequestToSign')
			->with($idDoc, null, $approver)
			->willReturn($approverSignRequest);
		// The uploader's sign request is never used to pick where the code goes.
		$this->signRequestMapper->expects($this->never())
			->method('getBySignerUuidAndUserId');
		$this->signFileService->method('getFile')->with(10)->willReturn($idDoc);
		$this->signFileService->expects($this->once())
			->method('requestCode')
			->with($approverSignRequest, 'account', 'emailToken');

		$response = $this->getController()->requestCodeBySignerUuid('file-uuid', 'account', 'emailToken');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Verification code sent.', $response->getData()['message']);
	}

	public function testRequestCodeBySignerUuidWithoutIdDocApprovalKeepsTheSignerLookup(): void {
		$this->request->method('getParam')->willReturnMap([
			['idDocApproval', null, null],
		]);

		$signRequest = new SignRequest();
		$signRequest->setId(5);
		$signRequest->setFileId(3);
		$file = new FileEntity();
		$file->setId(3);

		$this->signRequestMapper->expects($this->once())
			->method('getBySignerUuidAndUserId')
			->with('signer-uuid')
			->willReturn($signRequest);
		$this->signFileService->expects($this->never())->method('getFileByUuid');
		$this->signFileService->expects($this->never())->method('getSignRequestToSign');
		$this->signFileService->method('getFile')->with(3)->willReturn($file);
		$this->signFileService->expects($this->once())
			->method('requestCode')
			->with($signRequest, 'email', 'emailToken');

		$response = $this->getController()->requestCodeBySignerUuid('signer-uuid', 'email', 'emailToken');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testRequestCodeEndpointsDoNotExposeIdentifyDestinationParameter(): void {
		$uuidMethod = new \ReflectionMethod(
			SignFileController::class,
			'requestCodeBySignerUuid',
		);
		$fileIdMethod = new \ReflectionMethod(
			SignFileController::class,
			'requestCodeByFileId',
		);

		$this->assertSame(
			['uuid', 'identifyMethod', 'signMethod'],
			array_map(
				static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
				$uuidMethod->getParameters(),
			),
		);

		$this->assertSame(
			['fileId', 'identifyMethod', 'signMethod'],
			array_map(
				static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
				$fileIdMethod->getParameters(),
			),
		);
	}

	public function testRequestCodeDoesNotExposeInternalExceptionMessage(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(5);
		$signRequest->setFileId(3);

		$file = new FileEntity();
		$file->setId(3);

		$this->signRequestMapper
			->method('getByFileIdAndUserId')
			->with(3)
			->willReturn($signRequest);

		$this->signFileService
			->method('getFile')
			->with(3)
			->willReturn($file);

		$internalException = new \RuntimeException(
			'SMTP authentication failed: password=super-secret'
		);

		$this->signFileService
			->expects($this->once())
			->method('requestCode')
			->with($signRequest, 'email', 'emailToken')
			->willThrowException($internalException);

		$this->logger
			->expects($this->once())
			->method('error')
			->with(
				'Unable to send verification code.',
				['exception' => $internalException],
			);

		$response = $this->getController()->requestCodeByFileId(
			3,
			'email',
			'emailToken',
		);

		$this->assertSame(
			Http::STATUS_UNPROCESSABLE_ENTITY,
			$response->getStatus(),
		);
		$this->assertSame(
			'Unable to send verification code.',
			$response->getData()['message'],
		);
		$this->assertStringNotContainsString(
			'super-secret',
			$response->getData()['message'],
		);
	}

}
