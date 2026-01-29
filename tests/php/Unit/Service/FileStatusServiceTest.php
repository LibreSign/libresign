<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use DateTimeInterface;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\SignRequest\StatusCacheService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FileStatusServiceTest extends TestCase {
	private FileMapper|MockObject $fileMapper;
	private StatusCacheService|MockObject $statusCacheService;
	private FileStatusService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->statusCacheService = $this->createMock(StatusCacheService::class);
		$this->service = new FileStatusService($this->fileMapper, $this->statusCacheService);
	}

	#[DataProvider('dataFileStatusUpgrade')]
	public function testUpdateFileStatusIfUpgrade(int $currentStatus, int $newStatus, bool $shouldUpdate): void {
		$file = new FileEntity();
		$file->setStatus($currentStatus);

		if ($shouldUpdate) {
			$this->fileMapper->expects($this->once())
				->method('update')
				->with($file)
				->willReturn($file);
		} else {
			$this->fileMapper->expects($this->never())->method('update');
		}

		$result = $this->service->updateFileStatusIfUpgrade($file, $newStatus);

		$expectedStatus = $shouldUpdate ? $newStatus : $currentStatus;
		$this->assertEquals($expectedStatus, $result->getStatus());
	}

	public static function dataFileStatusUpgrade(): array {
		$draft = FileStatus::DRAFT->value;
		$able = FileStatus::ABLE_TO_SIGN->value;
		$partial = FileStatus::PARTIAL_SIGNED->value;
		$signed = FileStatus::SIGNED->value;
		$deleted = FileStatus::DELETED->value;

		return [
			[$draft, $able, true],
			[$draft, $partial, true],
			[$draft, $signed, true],
			[$draft, $deleted, true],
			[$able, $partial, true],
			[$able, $signed, true],
			[$able, $deleted, true],
			[$partial, $signed, true],
			[$partial, $deleted, true],
			[$signed, $deleted, true],
			[$able, $draft, false],
			[$partial, $draft, false],
			[$partial, $able, false],
			[$signed, $draft, false],
			[$signed, $able, false],
			[$signed, $partial, false],
			[$deleted, $draft, false],
		];
	}

	#[DataProvider('dataCanNotifySigners')]
	public function testCanNotifySigners(?int $fileStatus, bool $expected): void {
		$result = $this->service->canNotifySigners($fileStatus);
		$this->assertEquals($expected, $result);
	}

	public function testUpdateFileStatusIfUpgradeSetsStatusChangedAt(): void {
		$file = new FileEntity();
		$file->setStatus(FileStatus::DRAFT->value);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (FileEntity $updated) {
				$this->assertStatusChangedAtSet($updated);
				return true;
			}));

		$this->service->updateFileStatusIfUpgrade($file, FileStatus::ABLE_TO_SIGN->value);
	}

	public function testUpdateSetsStatusChangedAt(): void {
		$file = new FileEntity();
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (FileEntity $updated) {
				$this->assertStatusChangedAtSet($updated);
				return true;
			}));

		$this->service->update($file);
	}

	public static function dataCanNotifySigners(): array {
		return [
			[FileStatus::DRAFT->value, false],
			[FileStatus::ABLE_TO_SIGN->value, true],
			[FileStatus::PARTIAL_SIGNED->value, false],
			[FileStatus::SIGNED->value, false],
			[FileStatus::DELETED->value, false],
			[null, false],
		];
	}

	#[DataProvider('dataPropagateStatusToParent')]
	public function testPropagateStatusToParent(array $childrenStatuses, int $expectedEnvelopeStatus, int $currentEnvelopeStatus): void {
		$parentId = 1;
		$envelope = new FileEntity();
		$envelope->setId($parentId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus($currentEnvelopeStatus);

		$children = [];
		foreach ($childrenStatuses as $index => $status) {
			$child = new FileEntity();
			$child->setId($index + 10);
			$child->setStatus($status);
			$children[] = $child;
		}

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($parentId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($parentId)
			->willReturn($children);

		if ($currentEnvelopeStatus !== $expectedEnvelopeStatus) {
			$this->fileMapper->expects($this->once())
				->method('update')
				->with($this->callback(function (FileEntity $file) use ($expectedEnvelopeStatus) {
					return $file->getStatus() === $expectedEnvelopeStatus;
				}));
		} else {
			$this->fileMapper->expects($this->never())->method('update');
		}

		$this->service->propagateStatusToParent($parentId);
	}

	public function testPropagateStatusToParentSetsStatusChangedAt(): void {
		$parentId = 99;
		$envelope = new FileEntity();
		$envelope->setId($parentId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus(FileStatus::DRAFT->value);

		$child = new FileEntity();
		$child->setStatus(FileStatus::PARTIAL_SIGNED->value);

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($parentId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($parentId)
			->willReturn([$child]);

		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (FileEntity $updated) {
				$this->assertStatusChangedAtSet($updated);
				$this->assertEquals(FileStatus::PARTIAL_SIGNED->value, $updated->getStatus());
				return true;
			}));

		$this->service->propagateStatusToParent($parentId);
	}

	public static function dataPropagateStatusToParent(): array {
		$draft = FileStatus::DRAFT->value;
		$able = FileStatus::ABLE_TO_SIGN->value;
		$partial = FileStatus::PARTIAL_SIGNED->value;
		$signed = FileStatus::SIGNED->value;

		return [
			'all draft' => [[$draft, $draft, $draft], $draft, $draft],
			'all able to sign' => [[$able, $able, $able], $able, $draft],
			'all partial signed' => [[$partial, $partial, $partial], $partial, $draft],
			'all signed' => [[$signed, $signed, $signed], $signed, $draft],
			'mixed draft and able' => [[$draft, $able, $draft], $able, $draft],
			'mixed able and partial' => [[$able, $partial, $able], $partial, $draft],
			'mixed partial and signed' => [[$partial, $signed, $partial], $partial, $draft],
			'mixed draft, able and partial' => [[$draft, $able, $partial], $partial, $draft],
			'mixed all statuses' => [[$draft, $able, $partial, $signed], $partial, $draft],
			'one signed, rest draft' => [[$draft, $draft, $signed], $partial, $draft],
		];
	}

	public function testPropagateStatusToParentWhenParentNotFound(): void {
		$parentId = 999;

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($parentId)
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('Not found'));

		$this->fileMapper->expects($this->never())->method('getChildrenFiles');
		$this->fileMapper->expects($this->never())->method('update');

		$this->service->propagateStatusToParent($parentId);
	}

	public function testPropagateStatusToParentWhenParentIsNotEnvelope(): void {
		$parentId = 1;
		$file = new FileEntity();
		$file->setId($parentId);
		$file->setNodeType('file');

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($parentId)
			->willReturn($file);

		$this->fileMapper->expects($this->never())->method('getChildrenFiles');
		$this->fileMapper->expects($this->never())->method('update');

		$this->service->propagateStatusToParent($parentId);
	}

	public function testPropagateStatusToParentWhenNoChildren(): void {
		$parentId = 1;
		$envelope = new FileEntity();
		$envelope->setId($parentId);
		$envelope->setNodeType('envelope');

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($parentId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($parentId)
			->willReturn([]);

		$this->fileMapper->expects($this->never())->method('update');

		$this->service->propagateStatusToParent($parentId);
	}

	public function testPropagateStatusToParentDoesNotUpdateIfStatusUnchanged(): void {
		$parentId = 1;
		$envelope = new FileEntity();
		$envelope->setId($parentId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus(FileStatus::SIGNED->value);

		$child1 = new FileEntity();
		$child1->setStatus(FileStatus::SIGNED->value);
		$child2 = new FileEntity();
		$child2->setStatus(FileStatus::SIGNED->value);

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($parentId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($parentId)
			->willReturn([$child1, $child2]);

		$this->fileMapper->expects($this->never())->method('update');

		$this->service->propagateStatusToParent($parentId);
	}

	public function testPropagateStatusToChildrenUpdatesAllChildren(): void {
		$envelopeId = 1;
		$newStatus = FileStatus::ABLE_TO_SIGN->value;

		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus($newStatus);

		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileStatus::DRAFT->value);

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileStatus::DRAFT->value);

		$children = [$child1, $child2];

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($envelopeId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($envelopeId)
			->willReturn($children);

		$this->fileMapper->expects($this->exactly(2))
			->method('update')
			->with($this->callback(function (FileEntity $file) use ($newStatus) {
				return $file->getStatus() === $newStatus;
			}));

		$this->service->propagateStatusToChildren($envelopeId, $newStatus);
	}

	public function testPropagateStatusToChildrenSetsStatusChangedAt(): void {
		$envelopeId = 50;
		$newStatus = FileStatus::ABLE_TO_SIGN->value;

		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus($newStatus);

		$child1 = new FileEntity();
		$child1->setId(500);
		$child1->setStatus(FileStatus::DRAFT->value);

		$child2 = new FileEntity();
		$child2->setId(501);
		$child2->setStatus(FileStatus::PARTIAL_SIGNED->value);

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($envelopeId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($envelopeId)
			->willReturn([$child1, $child2]);

		$updatedIds = [];
		$this->fileMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function (FileEntity $file) use (&$updatedIds) {
				$this->assertStatusChangedAtSet($file);
				$updatedIds[] = $file->getId();
				return $file;
			});

		$this->service->propagateStatusToChildren($envelopeId, $newStatus);

		$this->assertEqualsCanonicalizing([500, 501], $updatedIds);
	}

	public function testPropagateStatusToChildrenWhenEnvelopeNotFound(): void {
		$envelopeId = 999;
		$newStatus = FileStatus::ABLE_TO_SIGN->value;

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($envelopeId)
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('Not found'));

		$this->fileMapper->expects($this->never())->method('getChildrenFiles');
		$this->fileMapper->expects($this->never())->method('update');

		$this->service->propagateStatusToChildren($envelopeId, $newStatus);
	}

	public function testPropagateStatusToChildrenWhenNotEnvelope(): void {
		$fileId = 1;
		$newStatus = FileStatus::ABLE_TO_SIGN->value;

		$file = new FileEntity();
		$file->setId($fileId);
		$file->setNodeType('file');

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($fileId)
			->willReturn($file);

		$this->fileMapper->expects($this->never())->method('getChildrenFiles');
		$this->fileMapper->expects($this->never())->method('update');

		$this->service->propagateStatusToChildren($fileId, $newStatus);
	}

	public function testPropagateStatusToChildrenSkipsChildrenWithSameStatus(): void {
		$envelopeId = 1;
		$newStatus = FileStatus::ABLE_TO_SIGN->value;

		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus($newStatus);

		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileStatus::ABLE_TO_SIGN->value); // Already has the new status

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileStatus::DRAFT->value); // Needs update

		$children = [$child1, $child2];

		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($envelopeId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($envelopeId)
			->willReturn($children);

		// Should only update child2, not child1
		$this->fileMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (FileEntity $file) use ($newStatus) {
				return $file->getId() === 11 && $file->getStatus() === $newStatus;
			}));

		$this->service->propagateStatusToChildren($envelopeId, $newStatus);
	}

	/**
	 * Test to ensure envelope status is properly propagated to children files
	 * This addresses the bug where envelope status changes but children remain in DRAFT
	 */
	public function testPropagateStatusFromEnvelopeToChildrenWhenSignersAdded(): void {
		// Setup: Envelope with ABLE_TO_SIGN status
		$envelopeId = 1;
		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus(FileStatus::ABLE_TO_SIGN->value);

		// Setup: Children files with DRAFT status (the bug scenario)
		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileStatus::DRAFT->value);

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileStatus::DRAFT->value);

		$children = [$child1, $child2];

		// Mock expectations
		$this->fileMapper->expects($this->once())
			->method('getById')
			->with($envelopeId)
			->willReturn($envelope);

		$this->fileMapper->expects($this->once())
			->method('getChildrenFiles')
			->with($envelopeId)
			->willReturn($children);

		// Both children should be updated to ABLE_TO_SIGN
		$updateCount = 0;
		$this->fileMapper->expects($this->exactly(2))
			->method('update')
			->with($this->callback(function (FileEntity $file) use (&$updateCount) {
				$updateCount++;
				// Verify status is updated correctly
				$this->assertEquals(FileStatus::ABLE_TO_SIGN->value, $file->getStatus());
				// Verify it's one of our children
				$this->assertContains($file->getId(), [10, 11]);
				return true;
			}));

		// Execute: Propagate status from envelope to children
		$this->service->propagateStatusToChildren($envelopeId, FileStatus::ABLE_TO_SIGN->value);

		// Assert: Both children were updated
		$this->assertEquals(2, $updateCount, 'Both children should have been updated');
	}

	public function testEnvelopeStatusTransitionFromDraftToAbleToSign(): void {
		// This test simulates the complete workflow:
		// 1. Envelope is created (STATUS_DRAFT)
		// 2. Files are added to envelope (all STATUS_DRAFT)
		// 3. Signers are added to envelope (envelope changes to STATUS_ABLE_TO_SIGN)
		// 4. Children should also change to STATUS_ABLE_TO_SIGN

		$envelopeId = 1;
		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus(FileStatus::ABLE_TO_SIGN->value);

		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileStatus::DRAFT->value);

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileStatus::DRAFT->value);

		$this->fileMapper->method('getById')->willReturn($envelope);
		$this->fileMapper->method('getChildrenFiles')->willReturn([$child1, $child2]);

		$updatedFiles = [];
		$this->fileMapper->method('update')
			->willReturnCallback(function (FileEntity $file) use (&$updatedFiles) {
				$updatedFiles[] = [
					'id' => $file->getId(),
					'status' => $file->getStatus(),
				];
				return $file;
			});

		$this->service->propagateStatusToChildren($envelopeId, FileStatus::ABLE_TO_SIGN->value);

		// Verify both children were updated
		$this->assertCount(2, $updatedFiles);

		foreach ($updatedFiles as $updated) {
			$this->assertEquals(FileStatus::ABLE_TO_SIGN->value, $updated['status']);
			$this->assertContains($updated['id'], [10, 11]);
		}
	}

	private function assertStatusChangedAtSet(FileEntity $file): void {
		$metadata = $file->getMetadata();
		$this->assertIsArray($metadata);
		$this->assertArrayHasKey('status_changed_at', $metadata);
		$timestamp = $metadata['status_changed_at'];
		$this->assertNotEmpty($timestamp);
		$this->assertNotFalse(
			\DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $timestamp)
		);
	}
}
