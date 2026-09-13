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
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AsyncSigningService;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\RequestMetadataService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignFileControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IL10N&MockObject $l10n;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IUserSession&MockObject $userSession;
	private ValidateHelper&MockObject $validateHelper;
	private SignFileService&MockObject $signFileService;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->signFileService = $this->createMock(SignFileService::class);
	}

	private function getController(): SignFileController {
		return new SignFileController(
			$this->request,
			$this->l10n,
			$this->signRequestMapper,
			$this->userSession,
			$this->validateHelper,
			$this->signFileService,
			$this->createMock(IdentifyMethodService::class),
			$this->createMock(FileService::class),
			$this->createMock(SettingsLoader::class),
			$this->createMock(WorkerHealthService::class),
			$this->createMock(AsyncSigningService::class),
			$this->createMock(RequestMetadataService::class),
			$this->createMock(SigningErrorHandler::class),
		);
	}

	public function testGetCodeUsingUuidInIdDocApprovalContextUsesApproverSignRequest(): void {
		$approver = $this->createMock(IUser::class);
		$this->userSession->method('getUser')->willReturn($approver);
		$this->request->method('getParam')->willReturnMap([
			['idDocApproval', null, 'true'],
			['identifyMethod', '', 'account'],
			['signMethod', '', 'emailToken'],
			['identify', '', ''],
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
		$this->signRequestMapper->expects($this->never())
			->method('getBySignerUuidAndUserId');
		$this->signFileService->method('getFile')->with(10)->willReturn($idDoc);
		$this->validateHelper->expects($this->once())->method('fileCanBeSigned')->with($idDoc);
		$this->signFileService->expects($this->once())
			->method('requestCode')
			->with($approverSignRequest, 'account', 'emailToken', '');

		$response = $this->getController()->requestCodeBySignerUuid('file-uuid', 'account', 'emailToken', null);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Verification code sent.', $response->getData()['message']);
	}

	public function testGetCodeUsingUuidWithoutIdDocApprovalKeepsSignerLookup(): void {
		$this->request->method('getParam')->willReturnMap([
			['idDocApproval', null, null],
			['identifyMethod', '', 'email'],
			['signMethod', '', 'emailToken'],
			['identify', '', ''],
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
		$this->validateHelper->expects($this->once())->method('fileCanBeSigned')->with($file);
		$this->signFileService->expects($this->once())
			->method('requestCode')
			->with($signRequest, 'email', 'emailToken', '');

		$response = $this->getController()->requestCodeBySignerUuid('signer-uuid', 'email', 'emailToken', null);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
