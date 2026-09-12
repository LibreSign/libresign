<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OC\AppFramework\Utility\TimeFactory;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\RequestSignatureService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 * @group DB
 */
final class IdDocsServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IL10N&MockObject $l10n;
	private FileTypeMapper&MockObject $fileTypeMapper;
	private IdDocsMapper&MockObject $idDocsMapper;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private IdentifyMethodMapper&MockObject $identifyMethodMapper;
	private FileInputValidator&MockObject $fileInputValidator;
	private IdentityDocumentValidator&MockObject $identityDocumentValidator;
	private RequestSignatureService&MockObject $requestSignatureService;
	private TimeFactory&MockObject $timeFactory;
	private IAppConfig&MockObject $appConfig;
	private IUserManager&MockObject $userManager;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->idDocsMapper = $this->createMock(IdDocsMapper::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodMapper = $this->createMock(IdentifyMethodMapper::class);
		$this->fileInputValidator = $this->createMock(FileInputValidator::class);
		$this->identityDocumentValidator = $this->createMock(IdentityDocumentValidator::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
	}

	private function getIdDocsService(): IdDocsService {
		return new IdDocsService(
			$this->l10n,
			$this->fileTypeMapper,
			$this->fileInputValidator,
			$this->identityDocumentValidator,
			$this->requestSignatureService,
			$this->idDocsMapper,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->identifyMethodMapper,
			$this->timeFactory,
			$this->appConfig,
			$this->userManager,
		);
	}

	public function testValidateIdDocsUsesFocusedValidators(): void {
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('user1');
		$file = [
			'type' => 'IDENTIFICATION',
			'file' => ['base64' => 'encoded'],
		];

		$this->fileTypeMapper->method('getTypes')->willReturn([
			'IDENTIFICATION' => [],
		]);
		$this->identityDocumentValidator->expects($this->once())
			->method('validateFileTypeExists')
			->with('IDENTIFICATION');
		$this->fileInputValidator->expects($this->once())
			->method('validateNewFile')
			->with($file, FileInputValidator::TYPE_ACCOUNT_DOCUMENT, $user);
		$this->identityDocumentValidator->expects($this->once())
			->method('validateUserHasNoFileWithThisType')
			->with('user1', 'IDENTIFICATION');

		$this->getIdDocsService()->validateIdDocs([$file], $user);
	}

	public function testDeleteIdDocAsApproverBypassesOwnershipCheck(): void {
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('approver1');

		$this->identityDocumentValidator->method('userCanApproveValidationDocuments')
			->with($user, false)
			->willReturn(true);

		$idDocs = new \OCA\Libresign\Db\IdDocs();
		$idDocs->setFileId(10);

		$this->idDocsMapper->method('getByNodeId')
			->with(123)
			->willReturn($idDocs);

		$file = new \OCA\Libresign\Db\File();
		$this->fileMapper->method('getById')
			->with(10)
			->willReturn($file);

		$this->idDocsMapper->expects($this->once())->method('delete')->with($idDocs);
		$this->fileMapper->expects($this->once())->method('delete')->with($file);

		$service = $this->getIdDocsService();
		$service->deleteIdDoc(123, $user);
	}

	public function testDeleteIdDocAsNonApproverValidatesOwnership(): void {
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->identityDocumentValidator->method('userCanApproveValidationDocuments')
			->with($user, false)
			->willReturn(false);

		$this->identityDocumentValidator->expects($this->once())
			->method('validateIdDocIsOwnedByUser')
			->with(123, 'user1');

		$idDocs = new \OCA\Libresign\Db\IdDocs();
		$idDocs->setFileId(10);

		$this->idDocsMapper->method('getByUserIdAndNodeId')
			->with('user1', 123)
			->willReturn($idDocs);

		$file = new \OCA\Libresign\Db\File();
		$this->fileMapper->method('getById')
			->with(10)
			->willReturn($file);

		$service = $this->getIdDocsService();
		$service->deleteIdDoc(123, $user);
	}

	public function testDeleteIdDocBySignRequestValidatesAndDeletes(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(55);

		$this->identityDocumentValidator->expects($this->once())
			->method('validateIdDocBelongsToSignRequest')
			->with(123, 55);

		$idDocs = new \OCA\Libresign\Db\IdDocs();
		$idDocs->setFileId(10);

		$this->idDocsMapper->method('getBySignRequestIdAndNodeId')
			->with(55, 123)
			->willReturn($idDocs);

		$file = new \OCA\Libresign\Db\File();
		$this->fileMapper->method('getById')
			->with(10)
			->willReturn($file);

		$this->idDocsMapper->expects($this->once())->method('delete')->with($idDocs);
		$this->fileMapper->expects($this->once())->method('delete')->with($file);

		$service = $this->getIdDocsService();
		$service->deleteIdDocBySignRequest(123, $signRequest);
	}

	public function testDeleteIdDocBySignRequestThrowsOnInvalidDoc(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(55);

		$this->identityDocumentValidator->method('validateIdDocBelongsToSignRequest')
			->with(123, 55)
			->willThrowException(new \OCA\Libresign\Exception\LibresignException('Not allowed'));

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionMessage('Not allowed');

		$service = $this->getIdDocsService();
		$service->deleteIdDocBySignRequest(123, $signRequest);
	}
	public function testAddFilesToDocumentFolderStoresFilesUnderTheOwnerOfTheSignedFile(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(55);
		$signRequest->setFileId(10);

		$signedFile = new \OCA\Libresign\Db\File();
		$signedFile->setUserId('owner');
		$this->fileMapper->method('getById')
			->with(10)
			->willReturn($signedFile);

		$owner = $this->createMock(IUser::class);
		$this->userManager->method('get')
			->with('owner')
			->willReturn($owner);

		$this->fileTypeMapper->method('getTypes')
			->willReturn(['IDENTIFICATION' => ['type' => 'IDENTIFICATION']]);

		$savedFile = new \OCA\Libresign\Db\File();
		$savedFile->setId(77);
		$this->requestSignatureService->expects($this->once())
			->method('saveFile')
			->with($this->callback(function (array $data) use ($owner, $signRequest): bool {
				$this->assertSame($owner, $data['userManager']);
				$this->assertSame($signRequest, $data['signRequest']);
				$this->assertSame('id-front.pdf', $data['name']);
				return true;
			}))
			->willReturn($savedFile);

		$this->idDocsMapper->expects($this->once())
			->method('save')
			->with(77, 55, null, 'IDENTIFICATION');

		$service = $this->getIdDocsService();
		$service->addFilesToDocumentFolder(
			[['type' => 'IDENTIFICATION', 'name' => 'id-front.pdf', 'base64' => 'ZmFrZQ==']],
			$signRequest,
		);
	}

	public function testAddFilesToDocumentFolderWithoutResolvableOwnerKeepsCurrentBehaviour(): void {
		$signRequest = new SignRequest();
		$signRequest->setId(55);
		$signRequest->setFileId(10);

		$signedFile = new \OCA\Libresign\Db\File();
		$signedFile->setUserId('deleted-owner');
		$this->fileMapper->method('getById')
			->with(10)
			->willReturn($signedFile);

		$this->userManager->method('get')
			->with('deleted-owner')
			->willReturn(null);

		$this->fileTypeMapper->method('getTypes')
			->willReturn(['IDENTIFICATION' => ['type' => 'IDENTIFICATION']]);

		$savedFile = new \OCA\Libresign\Db\File();
		$savedFile->setId(77);
		$this->requestSignatureService->expects($this->once())
			->method('saveFile')
			->with($this->callback(function (array $data): bool {
				$this->assertArrayNotHasKey('userManager', $data);
				return true;
			}))
			->willReturn($savedFile);

		$this->idDocsMapper->expects($this->once())
			->method('save')
			->with(77, 55, null, 'IDENTIFICATION');

		$service = $this->getIdDocsService();
		$service->addFilesToDocumentFolder(
			[['type' => 'IDENTIFICATION', 'base64' => 'ZmFrZQ==']],
			$signRequest,
		);
	}
}
