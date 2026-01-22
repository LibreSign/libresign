<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignRequest;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\SignRequest\ProgressService;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProgressServiceTest extends TestCase {
	private ProgressService $service;
	private FileMapper&MockObject $fileMapper;
	private ICache&MockObject $cache;
	private ICacheFactory&MockObject $cacheFactory;

	protected function setUp(): void {
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->cache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory
			->method('createDistributed')
			->with('libresign_progress')
			->willReturn($this->cache);

		$this->service = new ProgressService(
			$this->fileMapper,
			$this->cacheFactory,
		);
	}

	public function testPollReturnsInitialStatusWhenCacheNeverChanges(): void {
		$uuid = 'test-uuid';
		$initialStatus = FileStatus::DRAFT->value;
		$timeout = 1;

		$this->cache->method('get')->willReturn(false);

		$result = $this->service->pollForStatusChange($uuid, $initialStatus, $timeout, 0);

		$this->assertEquals($initialStatus, $result);
	}

	public function testPollReturnsNewStatusWhenCacheChanges(): void {
		$uuid = 'test-uuid';
		$initialStatus = FileStatus::DRAFT->value;
		$newStatus = FileStatus::SIGNING_IN_PROGRESS->value;

		$this->cache
			->method('get')
			->willReturnOnConsecutiveCalls(
				$initialStatus,
				$initialStatus,
				$newStatus,
			);

		$result = $this->service->pollForStatusChange($uuid, $initialStatus, 5, 0);

		$this->assertEquals($newStatus, $result);
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
		// Test single file
		$file = $this->createFileEntity(1, 'test.pdf', FileStatus::DRAFT->value, null);
		$signRequest = $this->createSignRequestEntity(100, 'John Doe', FileStatus::DRAFT->value, null);

		$progress = $this->service->getSignRequestProgress($file, $signRequest);

		$this->assertIsArray($progress);
		$this->assertArrayHasKey('total', $progress);
		$this->assertArrayHasKey('signed', $progress);
		$this->assertArrayHasKey('pending', $progress);
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
}
