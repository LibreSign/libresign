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
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdDocsService;
use OCA\Libresign\Service\RequestSignatureService;
use OCP\IAppConfig;
use OCP\IL10N;
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
	private ValidateHelper&MockObject $validateHelper;
	private RequestSignatureService&MockObject $requestSignatureService;
	private TimeFactory&MockObject $timeFactory;
	private IAppConfig&MockObject $appConfig;

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
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->requestSignatureService = $this->createMock(RequestSignatureService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
	}



	private function getIdDocsService(): IdDocsService {
		return new IdDocsService(
			$this->l10n,
			$this->fileTypeMapper,
			$this->validateHelper,
			$this->requestSignatureService,
			$this->idDocsMapper,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->identifyMethodMapper,
			$this->timeFactory,
			$this->appConfig,
		);
	}

	public function testDeleteIdDocAsApproverBypassesOwnershipCheck(): void {
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('approver1');

		$this->validateHelper->method('userCanApproveValidationDocuments')
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

		$this->validateHelper->method('userCanApproveValidationDocuments')
			->with($user, false)
			->willReturn(false);

		$this->validateHelper->expects($this->once())
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

		$this->validateHelper->expects($this->once())
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

		$this->validateHelper->method('validateIdDocBelongsToSignRequest')
			->with(123, 55)
			->willThrowException(new \OCA\Libresign\Exception\LibresignException('Not allowed'));

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionMessage('Not allowed');

		$service = $this->getIdDocsService();
		$service->deleteIdDocBySignRequest(123, $signRequest);
	}
}
