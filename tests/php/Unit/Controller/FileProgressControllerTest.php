<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OC\Memcache\ArrayCache;
use OCA\Libresign\Controller\FileProgressController;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\SessionService;
use OCA\Libresign\Service\SignRequest\ProgressService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCP\AppFramework\Http;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IMemcache;
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

	public function testCheckProgressByUuidWithError(): void {
		$uuid = 'test-uuid-error';
		$file = new FileEntity();
		$file->setId(757);
		$file->setName('test.pdf');
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(100);
		$signRequest->setFileId(757);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->with(757)
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$error = [
			'message' => 'Certificate validation failed',
			'code' => 400,
			'timestamp' => date('c'),
		];

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::DRAFT->value);

		$this->progressService
			->method('pollForStatusOrErrorChange')
			->willReturn(FileStatus::DRAFT->value);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn([
				'total' => 1,
				'signed' => 0,
				'pending' => 1,
				'inProgress' => 0,
				'files' => [
					['id' => 757, 'name' => 'test.pdf', 'status' => 1, 'statusText' => 'DRAFT'],
				],
			]);

		$this->progressService
			->method('getSignRequestError')
			->with($uuid)
			->willReturn($error);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$response = $this->controller->checkProgressByUuid($uuid);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertEquals('ERROR', $data['status']);
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Certificate validation failed', $data['error']['message']);
		$this->assertEquals(400, $data['error']['code']);
	}

	public function testCheckProgressByUuidWithoutError(): void {
		$uuid = 'test-uuid-no-error';
		$file = new FileEntity();
		$file->setId(757);
		$file->setName('test.pdf');
		$file->setStatus(FileStatus::DRAFT->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(100);
		$signRequest->setFileId(757);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->with(757)
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::DRAFT->value);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn([
				'total' => 1,
				'signed' => 0,
				'pending' => 1,
				'inProgress' => 0,
				'errors' => 0,
				'files' => [
					['id' => 757, 'name' => 'test.pdf', 'status' => 1, 'statusText' => 'DRAFT'],
				],
			]);

		$this->progressService
			->method('getSignRequestError')
			->with($uuid)
			->willReturn(null);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$response = $this->controller->checkProgressByUuid($uuid);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertNotEquals('ERROR', $data['status']);
		$this->assertArrayNotHasKey('error', $data);
		$this->assertArrayNotHasKey('file', $data);
	}

	public function testCheckProgressByUuidWithFileErrorInEnvelope(): void {
		$uuid = 'test-uuid-file-error';
		$envelope = new FileEntity();
		$envelope->setId(100);
		$envelope->setName('envelope.pdf');
		$envelope->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);
		$envelope->setNodeType('envelope');

		$signRequest = new SignRequestEntity();
		$signRequest->setId(200);
		$signRequest->setFileId(100);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->with(100)
			->willReturn($envelope);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		// Progress with file error in envelope
		$progress = [
			'total' => 2,
			'signed' => 0,
			'pending' => 1,
			'inProgress' => 1,
			'files' => [
				[
					'id' => 101,
					'name' => 'file1.pdf',
					'status' => FileStatus::SIGNING_IN_PROGRESS->value,
					'statusText' => 'SIGNING_IN_PROGRESS',
				],
				[
					'id' => 102,
					'name' => 'file2.pdf',
					'status' => FileStatus::DRAFT->value,
					'statusText' => 'DRAFT',
					'error' => [
						'message' => 'File validation failed',
						'code' => 422,
					],
				],
			],
		];

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->progressService
			->method('pollForStatusOrErrorChange')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn($progress);

		$this->progressService
			->method('getSignRequestError')
			->with($uuid)
			->willReturn(null);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$response = $this->controller->checkProgressByUuid($uuid);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertArrayHasKey('progress', $data);
		$this->assertCount(2, $data['progress']['files']);
		$this->assertArrayNotHasKey('error', $data['progress']['files'][0]);
		$this->assertArrayHasKey('error', $data['progress']['files'][1]);
		$this->assertEquals('File validation failed', $data['progress']['files'][1]['error']['message']);
	}

	public function testRootErrorSuppressedWhenFileErrorsExist(): void {
		$uuid = 'mixed-error-uuid';
		$file = new FileEntity();
		$file->setId(501);
		$file->setName('parent.pdf');
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(601);
		$signRequest->setFileId(501);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->with(501)
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$progress = [
			'total' => 1,
			'signed' => 0,
			'pending' => 1,
			'inProgress' => 0,
			'files' => [
				[
					'id' => 701,
					'name' => 'child.pdf',
					'status' => FileStatus::DRAFT->value,
					'statusText' => 'DRAFT',
					'error' => [
						'message' => 'Child failed',
						'code' => 422,
					],
				],
			],
		];

		$generalError = [
			'message' => 'General failure',
			'code' => 500,
		];

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->progressService
			->method('pollForStatusOrErrorChange')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn($progress);

		$this->progressService
			->method('getSignRequestError')
			->with($uuid)
			->willReturn($generalError);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$response = $this->controller->checkProgressByUuid($uuid);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertEquals('SIGNING_IN_PROGRESS', $data['status']);
		$this->assertArrayNotHasKey('error', $data, 'Root error should be suppressed when file errors exist');
		$this->assertArrayNotHasKey('file', $data, 'Should not include file object when errors exist');
		$this->assertArrayHasKey('progress', $data);
		$this->assertArrayHasKey('files', $data['progress']);
		$this->assertArrayHasKey('error', $data['progress']['files'][0]);
		$this->assertEquals('Child failed', $data['progress']['files'][0]['error']['message']);
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

	public function testCheckProgressByUuidReadsErrorFromCache(): void {
		$uuid = 'cache-error-uuid';
		$file = new FileEntity();
		$file->setId(777);
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(888);
		$signRequest->setFileId(777);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->with(777)
			->willReturn($file);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$cache = new InMemoryCache();
		$cache->set(ProgressService::ERROR_KEY_PREFIX . $uuid, [
			'message' => 'Cached error',
			'code' => 500,
		]);
		$cacheFactory = new InMemoryCacheFactory($cache);
		$statusCacheService = new StatusCacheService($cacheFactory);
		$realProgressService = new ProgressService($this->fileMapper, $cacheFactory, $this->signRequestMapper, $statusCacheService);

		$controller = new FileProgressController(
			$this->request,
			$this->fileMapper,
			$this->signRequestMapper,
			$this->fileService,
			$this->sessionService,
			$this->userSession,
			$this->workerHealthService,
			$realProgressService,
		);

		$response = $controller->checkProgressByUuid($uuid);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertEquals('ERROR', $data['status']);
		$this->assertEquals('Cached error', $data['error']['message']);
	}

	public function testCheckProgressByUuidNormalizesTimeout(): void {
		$uuid = 'timeout-uuid';
		$file = new FileEntity();
		$file->setId(321);
		$file->setStatus(FileStatus::SIGNING_IN_PROGRESS->value);

		$signRequest = new SignRequestEntity();
		$signRequest->setId(654);
		$signRequest->setFileId(321);
		$signRequest->setUuid($uuid);

		$this->signRequestMapper
			->method('getByUuid')
			->with($uuid)
			->willReturn($signRequest);

		$this->fileMapper
			->method('getById')
			->with(321)
			->willReturn($file);

		$this->progressService
			->method('getStatusCodeForSignRequest')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$this->progressService
			->expects($this->once())
			->method('pollForStatusOrErrorChange')
			->with($file, $signRequest, FileStatus::SIGNING_IN_PROGRESS->value, 1);

		$this->progressService
			->method('getSignRequestProgress')
			->willReturn([
				'total' => 1,
				'signed' => 0,
				'pending' => 1,
				'inProgress' => 0,
				'files' => [],
			]);

		$this->progressService
			->method('getSignRequestError')
			->with($uuid)
			->willReturn(null);

		$this->progressService
			->method('isProgressComplete')
			->willReturn(false);

		$this->controller->checkProgressByUuid($uuid, 0);
	}
}

class InMemoryCache implements ICache {
	private array $data = [];

	public function get($key) {
		return $this->data[$key] ?? null;
	}

	public function set($key, $value, $ttl = 0) {
		$this->data[$key] = $value;
		return true;
	}

	public function hasKey($key) {
		return array_key_exists($key, $this->data);
	}

	public function remove($key) {
		unset($this->data[$key]);
		return true;
	}

	public function clear($prefix = '') {
		if ($prefix === '') {
			$this->data = [];
			return true;
		}

		foreach (array_keys($this->data) as $key) {
			if (str_starts_with($key, $prefix)) {
				unset($this->data[$key]);
			}
		}
		return true;
	}

	public static function isAvailable(): bool {
		return true;
	}
}

class InMemoryCacheFactory implements ICacheFactory {
	public function __construct(
		private ICache $cache,
	) {
	}

	public function isAvailable(): bool {
		return true;
	}

	public function isLocalCacheAvailable(): bool {
		return true;
	}

	public function createLocking(string $prefix = ''): IMemcache {
		return new ArrayCache();
	}

	public function createDistributed(string $prefix = ''): ICache {
		return $this->cache;
	}

	public function createLocal(string $prefix = ''): ICache {
		return new InMemoryCache();
	}

	public function createInMemory(int $capacity = 512): ICache {
		return new InMemoryCache();
	}
}
