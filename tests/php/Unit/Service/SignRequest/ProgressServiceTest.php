<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OC\Memcache\ArrayCache;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\SignRequest\ProgressPollDecisionPolicy;
use OCA\Libresign\Service\SignRequest\ProgressService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IMemcache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProgressServiceTest extends TestCase {
	private ProgressService $service;
	private FileMapper&MockObject $fileMapper;
	private ICache&MockObject $cache;
	private ICacheFactory&MockObject $cacheFactory;
	private SignRequestMapper&MockObject $signRequestMapper;
	private StatusCacheService&MockObject $statusCacheService;
	private ProgressPollDecisionPolicy $pollDecisionPolicy;

	protected function setUp(): void {
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->cache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->cacheFactory
			->method('createDistributed')
			->with('libresign_progress')
			->willReturn($this->cache);
		$this->statusCacheService = $this->createMock(StatusCacheService::class);
		$this->pollDecisionPolicy = new ProgressPollDecisionPolicy();

		$this->service = new ProgressService(
			$this->fileMapper,
			$this->cacheFactory,
			$this->signRequestMapper,
			$this->statusCacheService,
			$this->pollDecisionPolicy,
		);
	}

	public function testPollReturnsInitialStatusWhenCacheNeverChanges(): void {
		$uuid = 'test-uuid';
		$initialStatus = FileStatus::DRAFT->value;
		$timeout = 1;

		$this->statusCacheService->method('getStatus')->willReturn(false);

		$result = $this->service->pollForStatusChange($uuid, $initialStatus, $timeout, 0);

		$this->assertEquals($initialStatus, $result);
	}

	public function testPollReturnsNewStatusWhenCacheChanges(): void {
		$uuid = 'test-uuid';
		$initialStatus = FileStatus::DRAFT->value;
		$newStatus = FileStatus::SIGNING_IN_PROGRESS->value;

		$sequence = [$initialStatus, $initialStatus, $newStatus];
		$index = 0;

		$this->statusCacheService
			->method('getStatus')
			->with($uuid)
			->willReturnCallback(function () use (&$sequence, &$index) {
				$value = $sequence[$index] ?? $sequence[count($sequence) - 1];
				$index++;
				return $value;
			});

		$result = $this->service->pollForStatusChange($uuid, $initialStatus, 5, 0);

		$this->assertEquals($newStatus, $result);
	}

	public function testPollForStatusOrErrorChangeUsesFileUuidCacheKeyForSingleFile(): void {
		$file = $this->createFileEntity(10, 'file.pdf', FileStatus::DRAFT->value, null);
		$file->setUuid('file-uuid');
		$signRequest = $this->createSignRequestEntity(20, 'Signer', FileStatus::DRAFT->value, null);
		$signRequest->setUuid('sign-request-uuid');

		$uuids = [];
		$this->statusCacheService
			->method('getStatus')
			->willReturnCallback(function (string $uuid) use (&$uuids) {
				$uuids[] = $uuid;
				return false;
			});

		$this->signRequestMapper
			->method('getByUuidUncached')
			->willThrowException(new DoesNotExistException('not found'));

		$result = $this->service->pollForStatusOrErrorChange($file, $signRequest, FileStatus::DRAFT->value, 1, 0);

		$this->assertEquals(FileStatus::DRAFT->value, $result);
		$this->assertContains('file-uuid', $uuids);
		$this->assertNotContains('sign-request-uuid', $uuids);
	}

	public function testPollForStatusOrErrorChangeStopsOnChildErrorForEnvelope(): void {
		$envelope = $this->createFileEntity(100, 'envelope.pdf', FileStatus::SIGNING_IN_PROGRESS->value, null, 'envelope');
		$envelope->setUuid('envelope-uuid');
		$signRequest = $this->createSignRequestEntity(200, 'Signer', FileStatus::DRAFT->value, null);
		$signRequest->setUuid('envelope-sign-request-uuid');

		$childSignRequest = $this->createSignRequestEntity(300, 'Child', FileStatus::DRAFT->value, null);
		$childSignRequest->setUuid('child-sign-request-uuid');

		$this->signRequestMapper
			->method('getByEnvelopeChildrenAndIdentifyMethod')
			->with(100, 200)
			->willReturn([$childSignRequest]);

		$this->fileMapper
			->method('getChildrenFiles')
			->with(100)
			->willReturn([]);

		$errorKey = ProgressService::ERROR_KEY_PREFIX . 'child-sign-request-uuid';

		$this->cache
			->method('get')
			->willReturnCallback(function (string $key) use ($errorKey) {
				if ($key === $errorKey) {
					return ['message' => 'child error'];
				}
				return false;
			});

		$this->statusCacheService
			->method('getStatus')
			->with('envelope-uuid')
			->willReturn(FileStatus::SIGNING_IN_PROGRESS->value);

		$result = $this->service->pollForStatusOrErrorChange(
			$envelope,
			$signRequest,
			FileStatus::SIGNING_IN_PROGRESS->value,
			1,
			0,
		);

		$this->assertEquals(FileStatus::SIGNING_IN_PROGRESS->value, $result);
	}

	#[DataProvider('envelopeProgressChangeProvider')]
	public function testPollForStatusOrErrorChangeReturnsWhenEnvelopeProgressChanges(int $childIndex, int $newStatus): void {
		$envelope = $this->createFileEntity(1, 'envelope.pdf', FileStatus::DRAFT->value, null, 'envelope');
		$envelope->setUuid('envelope-uuid');
		$signRequest = $this->createSignRequestEntity(10, 'Signer', FileStatus::DRAFT->value, null);
		$signRequest->setUuid('sign-request-uuid');

		$children = [
			$this->createFileEntity(2, 'child1.pdf', FileStatus::DRAFT->value, $envelope->getId()),
			$this->createFileEntity(3, 'child2.pdf', FileStatus::DRAFT->value, $envelope->getId()),
		];

		$call = 0;
		$this->fileMapper
			->method('getChildrenFiles')
			->willReturnCallback(function () use (&$call, $children, $childIndex, $newStatus): array {
				if ($call === 0) {
					$call++;
					return $children;
				}
				$children[$childIndex]->setStatus($newStatus);
				return $children;
			});

		$this->signRequestMapper
			->method('getByEnvelopeChildrenAndIdentifyMethod')
			->willReturn([]);

		$this->statusCacheService
			->method('getStatus')
			->with('envelope-uuid')
			->willReturn(FileStatus::ABLE_TO_SIGN->value);

		$result = $this->service->pollForStatusOrErrorChange(
			$envelope,
			$signRequest,
			FileStatus::SIGNING_IN_PROGRESS->value,
			2,
			0,
		);

		$this->assertEquals(FileStatus::ABLE_TO_SIGN->value, $result);
	}

	public static function envelopeProgressChangeProvider(): array {
		return [
			'child 1 signed' => [0, FileStatus::SIGNED->value],
			'child 2 signed' => [1, FileStatus::SIGNED->value],
			'child 1 in progress' => [0, FileStatus::SIGNING_IN_PROGRESS->value],
		];
	}

	public function testGetSignRequestProgressForSingleFile(): void {
		$file = $this->createFileEntity(1, 'test.pdf', FileStatus::DRAFT->value, null);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);

		$progress = $this->service->getSingleFileProgressForSignRequest($file, $signRequest);

		$this->assertEquals(1, $progress['total']);
		$this->assertEquals(0, $progress['signed']);
		$this->assertEquals(0, $progress['inProgress']);
		$this->assertEquals(1, $progress['pending']);
		$this->assertCount(1, $progress['files']);
	}

	public function testGetSignRequestProgressForSignedFile(): void {
		$file = $this->createFileEntity(1, 'test.pdf', FileStatus::SIGNED->value, null);
		$signedTime = new \DateTime('2024-01-01');
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::SIGNED->value, $signedTime);

		$progress = $this->service->getSingleFileProgressForSignRequest($file, $signRequest);

		$this->assertEquals(1, $progress['total']);
		$this->assertEquals(1, $progress['signed']);
		$this->assertEquals(0, $progress['inProgress']);
		$this->assertEquals(0, $progress['pending']);
	}

	public function testGetSignRequestProgressForSigningInProgress(): void {
		$file = $this->createFileEntity(1, 'test.pdf', FileStatus::SIGNING_IN_PROGRESS->value, null);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::SIGNING_IN_PROGRESS->value, null);

		$progress = $this->service->getSingleFileProgressForSignRequest($file, $signRequest);

		$this->assertEquals(1, $progress['total']);
		$this->assertEquals(0, $progress['signed']);
		$this->assertEquals(1, $progress['inProgress']);
		$this->assertEquals(0, $progress['pending']);
	}

	public function testGetSignRequestProgressForEnvelopeUnsigned(): void {
		$envelope = $this->createFileEntity(1, 'envelope.pdf', FileStatus::DRAFT->value, null, 'envelope');
		$child1 = $this->createFileEntity(2, 'child1.pdf', FileStatus::DRAFT->value, 1);
		$child2 = $this->createFileEntity(3, 'child2.pdf', FileStatus::DRAFT->value, 1);

		$this->fileMapper
			->method('getChildrenFiles')
			->with(1)
			->willReturn([$child1, $child2]);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);

		$progress = $this->service->getEnvelopeProgressForSignRequest($envelope, $signRequest);

		$this->assertEquals(2, $progress['total']);
		$this->assertEquals(0, $progress['signed']);
		$this->assertEquals(0, $progress['inProgress']);
		$this->assertEquals(2, $progress['pending']);
		$this->assertCount(2, $progress['files']);
	}

	public function testGetSignRequestProgressForEnvelopeSigned(): void {
		$envelope = $this->createFileEntity(1, 'envelope.pdf', FileStatus::SIGNED->value, null, 'envelope');
		$child1 = $this->createFileEntity(2, 'child1.pdf', FileStatus::SIGNED->value, 1);
		$child2 = $this->createFileEntity(3, 'child2.pdf', FileStatus::SIGNED->value, 1);

		$this->fileMapper
			->method('getChildrenFiles')
			->with(1)
			->willReturn([$child1, $child2]);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$signedTime = new \DateTime('2024-01-01');
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::SIGNED->value, $signedTime);

		$progress = $this->service->getEnvelopeProgressForSignRequest($envelope, $signRequest);

		$this->assertEquals(2, $progress['total']);
		$this->assertEquals(2, $progress['signed']);
		$this->assertEquals(0, $progress['inProgress']);
		$this->assertEquals(0, $progress['pending']);
	}

	public function testGetSignRequestProgressForFileInEnvelope(): void {
		$childFile = $this->createFileEntity(2, 'child.pdf', FileStatus::DRAFT->value, 1);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');

		$progress = $this->service->getFileProgressForSignRequest($childFile, $signRequest);

		$this->assertEquals(1, $progress['total']);
		$this->assertEquals(0, $progress['signed']);
		$this->assertEquals(0, $progress['inProgress']);
		$this->assertEquals(1, $progress['pending']);
		$this->assertArrayHasKey('signers', $progress);
		$this->assertCount(1, $progress['signers']);
	}

	public function testGetSignRequestProgressRoutesToCorrectMethod(): void {
		$file = $this->createFileEntity(1, 'test.pdf', FileStatus::DRAFT->value, null);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);

		$progress = $this->service->getSignRequestProgress($file, $signRequest);

		$this->assertIsArray($progress);
		$this->assertArrayHasKey('total', $progress);
		$this->assertArrayHasKey('signed', $progress);
		$this->assertArrayHasKey('pending', $progress);
	}

	public function testSetAndGetSignRequestError(): void {
		$uuid = 'test-error-uuid';
		$error = [
			'message' => 'Certificate validation failed',
			'code' => 400,
			'timestamp' => date('c'),
		];

		$this->service->setSignRequestError($uuid, $error);
		$retrieved = $this->service->getSignRequestError($uuid);

		$this->assertIsArray($retrieved);
		$this->assertEquals('Certificate validation failed', $retrieved['message']);
		$this->assertEquals(400, $retrieved['code']);
	}

	public function testGetSignRequestErrorReturnsNullWhenNotSet(): void {
		$uuid = 'non-existent-uuid';

		$error = $this->service->getSignRequestError($uuid);

		$this->assertNull($error);
	}

	public function testClearSignRequestError(): void {
		$uuid = 'clear-test-uuid';
		$error = ['message' => 'Test error', 'code' => 500];

		$this->service->setSignRequestError($uuid, $error);
		$this->assertNotNull($this->service->getSignRequestError($uuid));

		$this->service->clearSignRequestError($uuid);
		$this->assertNull($this->service->getSignRequestError($uuid));
	}

	public function testSetAndGetSignRequestErrorWithInMemoryCache(): void {
		$cache = new InMemoryCache();
		$cacheFactory = new InMemoryCacheFactory($cache);
		$service = new ProgressService(
			$this->fileMapper,
			$cacheFactory,
			$this->signRequestMapper,
			$this->statusCacheService,
			$this->pollDecisionPolicy
		);

		$uuid = 'real-cache-uuid';
		$error = [
			'message' => 'Real cache error',
			'code' => 501,
			'timestamp' => date('c'),
		];

		$service->setSignRequestError($uuid, $error);
		$retrieved = $service->getSignRequestError($uuid);

		$this->assertIsArray($retrieved);
		$this->assertEquals('Real cache error', $retrieved['message']);
		$this->assertEquals(501, $retrieved['code']);
	}

	public function testGetSignRequestErrorFallsBackToMetadata(): void {
		$uuid = 'metadata-uuid';
		$error = [
			'message' => 'Metadata error',
			'code' => 409,
		];

		$signRequest = new SignRequestEntity();
		$signRequest->setUuid($uuid);
		$signRequest->setMetadata(['libresign_error' => $error]);

		$this->cache->method('get')->willReturn(false);
		$this->signRequestMapper->method('getByUuidUncached')->with($uuid)->willReturn($signRequest);

		$service = new ProgressService(
			$this->fileMapper,
			$this->cacheFactory,
			$this->signRequestMapper,
			$this->statusCacheService,
			$this->pollDecisionPolicy
		);

		$retrieved = $service->getSignRequestError($uuid);

		$this->assertIsArray($retrieved);
		$this->assertEquals('Metadata error', $retrieved['message']);
		$this->assertEquals(409, $retrieved['code']);
	}

	public function testGetFileErrorFallsBackToMetadata(): void {
		$uuid = 'file-metadata-uuid';
		$fileId = 321;
		$error = [
			'message' => 'File metadata error',
			'code' => 422,
		];

		$signRequest = new SignRequestEntity();
		$signRequest->setUuid($uuid);
		$signRequest->setMetadata([
			'libresign_file_errors' => [
				$fileId => $error,
			],
		]);

		$this->cache->method('get')->willReturn(false);
		$this->signRequestMapper->method('getByUuidUncached')->with($uuid)->willReturn($signRequest);

		$service = new ProgressService(
			$this->fileMapper,
			$this->cacheFactory,
			$this->signRequestMapper,
			$this->statusCacheService,
			$this->pollDecisionPolicy
		);

		$retrieved = $service->getFileError($uuid, $fileId);

		$this->assertIsArray($retrieved);
		$this->assertEquals('File metadata error', $retrieved['message']);
		$this->assertEquals(422, $retrieved['code']);
	}

	public function testSetAndGetFileError(): void {
		$uuid = 'file-error-uuid';
		$fileId = 758;
		$error = [
			'message' => 'Invalid file format',
			'code' => 422,
			'timestamp' => date('c'),
		];

		$this->service->setFileError($uuid, $fileId, $error);
		$retrieved = $this->service->getFileError($uuid, $fileId);

		$this->assertIsArray($retrieved);
		$this->assertEquals('Invalid file format', $retrieved['message']);
		$this->assertEquals(422, $retrieved['code']);
	}

	public function testGetFileErrorReturnsNullWhenNotSet(): void {
		$uuid = 'no-file-error-uuid';
		$fileId = 999;

		$error = $this->service->getFileError($uuid, $fileId);

		$this->assertNull($error);
	}

	public function testClearFileError(): void {
		$uuid = 'clear-file-uuid';
		$fileId = 758;
		$error = ['message' => 'Test file error', 'code' => 400];

		$this->service->setFileError($uuid, $fileId, $error);
		$this->assertNotNull($this->service->getFileError($uuid, $fileId));

		$this->service->clearFileError($uuid, $fileId);
		$this->assertNull($this->service->getFileError($uuid, $fileId));
	}

	public function testMultipleFileErrorsIndependent(): void {
		$uuid = 'multi-file-uuid';
		$error1 = ['message' => 'Error 1', 'code' => 400];
		$error2 = ['message' => 'Error 2', 'code' => 500];

		$this->service->setFileError($uuid, 1, $error1);
		$this->service->setFileError($uuid, 2, $error2);

		$retrieved1 = $this->service->getFileError($uuid, 1);
		$retrieved2 = $this->service->getFileError($uuid, 2);

		$this->assertEquals('Error 1', $retrieved1['message']);
		$this->assertEquals('Error 2', $retrieved2['message']);
	}

	public function testMapSignRequestFileProgressIncludesError(): void {
		$uuid = 'progress-error-uuid';
		$file = $this->createFileEntity(758, 'test.pdf', FileStatus::DRAFT->value, null);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);

		$error = ['message' => 'File processing failed', 'code' => 500];
		$this->service->setFileError($uuid, 758, $error);

		$signRequest->setUuid($uuid);

		$reflection = new \ReflectionClass($this->service);
		$method = $reflection->getMethod('mapSignRequestFileProgress');
		$method->setAccessible(true);

		$mapped = $method->invoke($this->service, $file, $signRequest);

		$this->assertArrayHasKey('error', $mapped);
		$this->assertEquals('File processing failed', $mapped['error']['message']);
	}

	public function testMapSignRequestFileProgressWithoutError(): void {
		$file = $this->createFileEntity(758, 'test.pdf', FileStatus::DRAFT->value, null);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);
		$signRequest->setUuid('no-error-uuid');

		$reflection = new \ReflectionClass($this->service);
		$method = $reflection->getMethod('mapSignRequestFileProgress');
		$method->setAccessible(true);

		$mapped = $method->invoke($this->service, $file, $signRequest);

		$this->assertArrayNotHasKey('error', $mapped);
	}

	public function testEnvelopeProgressWithFileErrors(): void {
		$uuid = 'envelope-error-uuid';
		$envelope = $this->createFileEntity(1, 'envelope.pdf', FileStatus::DRAFT->value, null, 'envelope');
		$child1 = $this->createFileEntity(2, 'child1.pdf', FileStatus::DRAFT->value, 1);
		$child2 = $this->createFileEntity(3, 'child2.pdf', FileStatus::SIGNING_IN_PROGRESS->value, 1);

		$this->fileMapper
			->method('getChildrenFiles')
			->with(1)
			->willReturn([$child1, $child2]);

		$this->fileMapper
			->method('getTextOfStatus')
			->willReturnCallback(fn ($status) => FileStatus::tryFrom($status)?->name ?? 'UNKNOWN');
		$error = ['message' => 'Signature failed', 'code' => 422];
		$this->service->setFileError($uuid, 3, $error);

		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);
		$signRequest->setUuid($uuid);

		$progress = $this->service->getEnvelopeProgressForSignRequest($envelope, $signRequest);

		$this->assertCount(2, $progress['files']);
		$this->assertArrayNotHasKey('error', $progress['files'][0]); // child1
		$this->assertArrayHasKey('error', $progress['files'][1]); // child2
		$this->assertEquals('Signature failed', $progress['files'][1]['error']['message']);
		$this->assertEquals(1, $progress['errors']);
		$this->assertEquals(0, $progress['pending']);
	}

	public function testSingleFileProgressCountsErrorAsProcessed(): void {
		$uuid = 'single-error-uuid';
		$file = $this->createFileEntity(10, 'file.pdf', FileStatus::DRAFT->value, null);
		$signRequest = $this->createSignRequestEntity(20, 'User', FileStatus::DRAFT->value, null);
		$signRequest->setUuid($uuid);

		$error = ['message' => 'Failed to sign', 'code' => 500];
		$this->service->setFileError($uuid, $file->getId(), $error);

		$progress = $this->service->getSingleFileProgressForSignRequest($file, $signRequest);

		$this->assertEquals(1, $progress['total']);
		$this->assertEquals(1, $progress['errors']);
		$this->assertEquals(0, $progress['pending']);
		$this->assertEquals(0, $progress['inProgress']);
		$this->assertTrue($this->service->isProgressComplete($progress));
	}

	public function testMapSignRequestFileProgressWithContextUsesChildError(): void {
		$parentUuid = 'parent-uuid';
		$childUuid = 'child-uuid';
		$file = $this->createFileEntity(10, 'child.pdf', FileStatus::DRAFT->value, 1);
		$defaultSignRequest = $this->createSignRequestEntity(200, 'Parent', FileStatus::DRAFT->value, null);
		$defaultSignRequest->setUuid($parentUuid);
		$childSignRequest = $this->createSignRequestEntity(201, 'Child', FileStatus::DRAFT->value, null);
		$childSignRequest->setUuid($childUuid);

		$error = ['message' => 'Child file failed', 'code' => 422];
		$this->service->setFileError($childUuid, $file->getId(), $error);

		$reflection = new \ReflectionClass($this->service);
		$method = $reflection->getMethod('mapSignRequestFileProgressWithContext');
		$method->setAccessible(true);

		$mapped = $method->invoke($this->service, $file, $defaultSignRequest, $childSignRequest);

		$this->assertArrayHasKey('error', $mapped);
		$this->assertEquals('Child file failed', $mapped['error']['message']);
		$this->assertEquals(422, $mapped['error']['code']);
	}

	public function testMapSignRequestFileProgressWithContextFallsBackToMetadata(): void {
		$childUuid = 'child-meta-uuid';
		$fileId = 55;
		$file = $this->createFileEntity($fileId, 'child.pdf', FileStatus::DRAFT->value, 1);
		$defaultSignRequest = $this->createSignRequestEntity(300, 'Parent', FileStatus::DRAFT->value, null);
		$childSignRequest = $this->createSignRequestEntity(301, 'Child', FileStatus::DRAFT->value, null);
		$childSignRequest->setUuid($childUuid);

		$signRequestWithMetadata = new SignRequestEntity();
		$signRequestWithMetadata->setUuid($childUuid);
		$signRequestWithMetadata->setMetadata([
			'libresign_file_errors' => [
				$fileId => ['message' => 'Metadata file error', 'code' => 409],
			],
		]);

		$this->cache->method('get')->willReturn(false);
		$this->signRequestMapper
			->method('getByUuidUncached')
			->with($childUuid)
			->willReturn($signRequestWithMetadata);

		$reflection = new \ReflectionClass($this->service);
		$method = $reflection->getMethod('mapSignRequestFileProgressWithContext');
		$method->setAccessible(true);

		$mapped = $method->invoke($this->service, $file, $defaultSignRequest, $childSignRequest);

		$this->assertArrayHasKey('error', $mapped);
		$this->assertEquals('Metadata file error', $mapped['error']['message']);
		$this->assertEquals(409, $mapped['error']['code']);
	}

	private function createFileEntity(
		int $id,
		string $name,
		int $status,
		?int $parentFileId,
		string $nodeType = 'file',
	): FileEntity {
		$file = new FileEntity();
		$file->setId($id);
		$file->setName($name);
		$file->setStatus($status);
		$file->setParentFileId($parentFileId);
		$file->setNodeType($nodeType);

		return $file;
	}

	private function createSignRequestEntity(
		int $id,
		string $displayName,
		int $status,
		?\DateTime $signed,
	): SignRequestEntity {
		$signRequest = new SignRequestEntity();
		$signRequest->setId($id);
		$signRequest->setDisplayName($displayName);
		$signRequest->setStatus($status);
		$signRequest->setSigned($signed);

		return $signRequest;
	}

	public function testFileErrorsSuppressRootError(): void {
		$uuid = 'suppress-test-uuid';
		$progress = [
			'total' => 2,
			'signed' => 0,
			'pending' => 2,
			'files' => [
				[
					'id' => 101,
					'name' => 'file1.pdf',
					'status' => FileStatus::DRAFT->value,
					'statusText' => 'DRAFT',
					'error' => [
						'message' => 'File validation failed',
						'code' => 422,
					],
				],
				[
					'id' => 102,
					'name' => 'file2.pdf',
					'status' => FileStatus::DRAFT->value,
					'statusText' => 'DRAFT',
				],
			],
		];

		$rootError = [
			'message' => 'Root error that should be suppressed',
			'code' => 500,
		];

		$this->service->setSignRequestError($uuid, $rootError);

		$cachedError = $this->service->getSignRequestError($uuid);

		$this->assertIsArray($cachedError);
		$this->assertEquals('Root error that should be suppressed', $cachedError['message']);
		$this->assertIsArray($progress['files'][0]['error']);
		$this->assertEquals('File validation failed', $progress['files'][0]['error']['message']);
	}

	public function testMultipleFileErrorsArePreserved(): void {
		$uuid = 'multi-error-uuid';
		$fileId1 = 201;
		$fileId2 = 202;

		$error1 = [
			'message' => 'File 1 error',
			'code' => 422,
		];

		$error2 = [
			'message' => 'File 2 error',
			'code' => 400,
		];

		$this->service->setFileError($uuid, $fileId1, $error1);
		$this->service->setFileError($uuid, $fileId2, $error2);

		$retrieved1 = $this->service->getFileError($uuid, $fileId1);
		$retrieved2 = $this->service->getFileError($uuid, $fileId2);

		$this->assertEquals('File 1 error', $retrieved1['message']);
		$this->assertEquals('File 2 error', $retrieved2['message']);
	}

	public function testErrorsArePersistedInMetadata(): void {
		$uuid = 'metadata-persist-uuid';
		$fileId = 303;

		$error = [
			'message' => 'Persistent error',
			'code' => 500,
		];

		$cache = new InMemoryCache();
		$cacheFactory = new InMemoryCacheFactory($cache);
		$service = new ProgressService(
			$this->fileMapper,
			$cacheFactory,
			$this->signRequestMapper,
			$this->statusCacheService,
			$this->pollDecisionPolicy
		);

		$service->setFileError($uuid, $fileId, $error);

		$cache->clear();

		$signRequest = new SignRequestEntity();
		$signRequest->setUuid($uuid);
		$signRequest->setMetadata([
			'libresign_file_errors' => [
				$fileId => $error,
			],
		]);

		$this->signRequestMapper
			->method('getByUuidUncached')
			->with($uuid)
			->willReturn($signRequest);

		$retrieved = $service->getFileError($uuid, $fileId);

		$this->assertIsArray($retrieved);
		$this->assertEquals('Persistent error', $retrieved['message']);
	}

	public function testClearSignRequestErrorRemovesFromCacheAndMetadata(): void {
		$uuid = 'clear-error-uuid';
		$error = [
			'message' => 'Error to be cleared',
			'code' => 500,
		];

		$this->service->setSignRequestError($uuid, $error);
		$this->assertIsArray($this->service->getSignRequestError($uuid));

		$this->service->clearSignRequestError($uuid);

		$this->assertNull($this->service->getSignRequestError($uuid));
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
