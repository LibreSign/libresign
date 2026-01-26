<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\FileProgressController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignRequest\ProgressService;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FileProgressControllerTest extends TestCase {
	private FileProgressController $controller;
	private FileMapper&MockObject $fileMapper;
	private SignRequestMapper&MockObject $signRequestMapper;
	private FileService&MockObject $fileService;
	private SessionService&MockObject $sessionService;
	private IUserSession&MockObject $userSession;
	private WorkerHealthService&MockObject $workerHealthService;
	private ProgressService&MockObject $progressService;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->workerHealthService = $this->createMock(WorkerHealthService::class);
		$this->progressService = $this->createMock(ProgressService::class);
		$this->request = $this->createMock(IRequest::class);

		$this->controller = new FileProgressController(
			$this->request,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->fileService,
			$this->sessionService,
			$this->userSession,
			$this->workerHealthService,
			$this->progressService,
		);
	}

	public function testCheckProgressByUuidNotFound(): void {
		$uuid = 'non-existent-uuid';

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willThrowException(new \Exception('Sign request not found'));

		$response = $this->controller->checkProgressByUuid($uuid);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals('ERROR', $data['status']);
	}

	public function testCheckProgressByUuidReturnsOkStatus(): void {
		$uuid = 'test-uuid';
		$file = new FileEntity();
		$file->setId(1);
		$file->setName('test.pdf');
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(1);
		$signRequest->setFileId(1);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($s) => FileStatus::tryFrom($s)?->name ?? 'UNKNOWN');

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::DRAFT->value);

		$this->progressService
			->method('pollForStatusOrErrorChange')
			->willReturn(FileStatus::DRAFT->value);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn(['total' => 1, 'signed' => 0, 'pending' => 1, 'files' => []]);

		$this->progressService
			->method('getSignRequestError')
			->willReturn(null);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$response = $this->controller->checkProgressByUuid($uuid);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('status', $data);
		$this->assertArrayHasKey('progress', $data);
	}

	public function testCheckProgressByUuidCallsWorkerHealthServiceWhenSigningInProgress(): void {
		$uuid = 'test-uuid';
		$file = new FileEntity();
		$file->setId(1);
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(1);
		$signRequest->setFileId(1);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($s) => FileStatus::tryFrom($s)?->name ?? 'UNKNOWN');

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->workerHealthService
			->expects($this->once())
			->method('ensureWorkerRunning');

		$this->progressService
			->method('pollForStatusOrErrorChange')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn(['total' => 1, 'files' => []]);

		$this->progressService
			->method('getSignRequestError')
			->willReturn(null);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$this->controller->checkProgressByUuid($uuid);
	}

	public function testCheckProgressByUuidNormalizesTimeout(): void {
		$uuid = 'test-uuid';
		$file = new FileEntity();
		$file->setId(1);
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(1);
		$signRequest->setFileId(1);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($s) => FileStatus::tryFrom($s)?->name ?? 'UNKNOWN');

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::DRAFT->value);

		$this->progressService
			->expects($this->once())
			->method('pollForStatusOrErrorChange')
			->with($file, $signRequest, FileStatus::DRAFT->value, 1);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn(['total' => 1, 'files' => []]);

		$this->progressService
			->method('getSignRequestError')
			->willReturn(null);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$this->controller->checkProgressByUuid($uuid, 0);
	}
}
