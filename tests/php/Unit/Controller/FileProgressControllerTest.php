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
use PHPUnit\Framework\Attributes\DataProvider;
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

	public static function progressResponseScenarios(): array {
		return [
			'complete without errors returns file' => [
				'progress' => [
					'total' => 1,
					'signed' => 1,
					'pending' => 0,
					'inProgress' => 0,
					'files' => [],
				],
				'rootError' => null,
				'complete' => true,
				'expectedStatus' => 'SIGNED',
				'expectFile' => true,
				'expectError' => false,
			],
			'root error without file errors sets ERROR status' => [
				'progress' => [
					'total' => 1,
					'signed' => 0,
					'pending' => 1,
					'files' => [],
				],
				'rootError' => ['message' => 'Envelope error', 'code' => 500],
				'complete' => false,
				'expectedStatus' => 'ERROR',
				'expectFile' => false,
				'expectError' => true,
			],
			'file errors suppress root error' => [
				'progress' => [
					'total' => 1,
					'signed' => 0,
					'pending' => 0,
					'inProgress' => 0,
					'files' => [
						[
							'id' => 10,
							'name' => 'file.pdf',
							'status' => FileStatus::DRAFT->value,
							'statusText' => 'DRAFT',
							'error' => ['message' => 'File failed', 'code' => 422],
						],
					],
					'errors' => 1,
				],
				'rootError' => ['message' => 'Root error', 'code' => 500],
				'complete' => true,
				'expectedStatus' => 'DRAFT',
				'expectFile' => false,
				'expectError' => false,
			],
			'file errors prevent file response even if complete' => [
				'progress' => [
					'total' => 1,
					'signed' => 0,
					'pending' => 0,
					'inProgress' => 0,
					'files' => [
						[
							'id' => 11,
							'name' => 'file.pdf',
							'status' => FileStatus::DRAFT->value,
							'statusText' => 'DRAFT',
							'error' => ['message' => 'File failed', 'code' => 422],
						],
					],
					'errors' => 1,
				],
				'rootError' => null,
				'complete' => true,
				'expectedStatus' => 'DRAFT',
				'expectFile' => false,
				'expectError' => false,
			],
		];
	}

	#[DataProvider('progressResponseScenarios')]
	public function testCheckProgressByUuidResponseBusinessRules(
		array $progress,
		?array $rootError,
		bool $complete,
		string $expectedStatus,
		bool $expectFile,
		bool $expectError,
	): void {
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

		$statusCode = $expectedStatus === 'SIGNED'
			? FileStatus::SIGNED->value
			: FileStatus::DRAFT->value;

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn($statusCode);

		$this->progressService
			->method('pollForStatusOrErrorChange')
			->willReturn($statusCode);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn($progress);

		$this->progressService
			->method('getSignRequestError')
			->willReturn($rootError);

		$this->progressService
			->method('isProgressComplete')
			->willReturn($complete);

		if ($expectFile) {
			$this->sessionService->method('getIdentifyMethodId')->willReturn(1);
			$this->request->method('getServerHost')->willReturn('localhost');
			$this->userSession->method('getUser')->willReturn(null);
			$this->fileService->method('setFile')->willReturnSelf();
			$this->fileService->method('setSignRequest')->willReturnSelf();
			$this->fileService->method('setIdentifyMethodId')->willReturnSelf();
			$this->fileService->method('setHost')->willReturnSelf();
			$this->fileService->method('setMe')->willReturnSelf();
			$this->fileService->method('showVisibleElements')->willReturnSelf();
			$this->fileService->method('showSigners')->willReturnSelf();
			$this->fileService->method('showSettings')->willReturnSelf();
			$this->fileService->method('showMessages')->willReturnSelf();
			$this->fileService->method('showValidateFile')->willReturnSelf();
			$this->fileService->method('toArray')->willReturn(['id' => 1]);
		}

		$response = $this->controller->checkProgressByUuid($uuid);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertEquals($expectedStatus, $data['status']);
		if ($expectFile) {
			$this->assertArrayHasKey('file', $data);
		} else {
			$this->assertArrayNotHasKey('file', $data);
		}

		if ($expectError) {
			$this->assertArrayHasKey('error', $data);
		} else {
			$this->assertArrayNotHasKey('error', $data);
		}
	}
}
