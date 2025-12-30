<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\FileStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FileStatusServiceTest extends TestCase {
	private FileMapper|MockObject $fileMapper;
	private FileStatusService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->service = new FileStatusService($this->fileMapper);
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
		$draft = FileEntity::STATUS_DRAFT;
		$able = FileEntity::STATUS_ABLE_TO_SIGN;
		$partial = FileEntity::STATUS_PARTIAL_SIGNED;
		$signed = FileEntity::STATUS_SIGNED;
		$deleted = FileEntity::STATUS_DELETED;

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

	public static function dataCanNotifySigners(): array {
		return [
			[FileEntity::STATUS_DRAFT, false],
			[FileEntity::STATUS_ABLE_TO_SIGN, true],
			[FileEntity::STATUS_PARTIAL_SIGNED, false],
			[FileEntity::STATUS_SIGNED, false],
			[FileEntity::STATUS_DELETED, false],
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

	public static function dataPropagateStatusToParent(): array {
		$draft = FileEntity::STATUS_DRAFT;
		$able = FileEntity::STATUS_ABLE_TO_SIGN;
		$partial = FileEntity::STATUS_PARTIAL_SIGNED;
		$signed = FileEntity::STATUS_SIGNED;

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
		$envelope->setStatus(FileEntity::STATUS_SIGNED);

		$child1 = new FileEntity();
		$child1->setStatus(FileEntity::STATUS_SIGNED);
		$child2 = new FileEntity();
		$child2->setStatus(FileEntity::STATUS_SIGNED);

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
		$newStatus = FileEntity::STATUS_ABLE_TO_SIGN;

		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus($newStatus);

		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileEntity::STATUS_DRAFT);

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileEntity::STATUS_DRAFT);

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

	public function testPropagateStatusToChildrenWhenEnvelopeNotFound(): void {
		$envelopeId = 999;
		$newStatus = FileEntity::STATUS_ABLE_TO_SIGN;

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
		$newStatus = FileEntity::STATUS_ABLE_TO_SIGN;

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
		$newStatus = FileEntity::STATUS_ABLE_TO_SIGN;

		$envelope = new FileEntity();
		$envelope->setId($envelopeId);
		$envelope->setNodeType('envelope');
		$envelope->setStatus($newStatus);

		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileEntity::STATUS_ABLE_TO_SIGN); // Already has the new status

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileEntity::STATUS_DRAFT); // Needs update

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
		$envelope->setStatus(FileEntity::STATUS_ABLE_TO_SIGN);

		// Setup: Children files with DRAFT status (the bug scenario)
		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileEntity::STATUS_DRAFT);

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileEntity::STATUS_DRAFT);

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
				$this->assertEquals(FileEntity::STATUS_ABLE_TO_SIGN, $file->getStatus());
				// Verify it's one of our children
				$this->assertContains($file->getId(), [10, 11]);
				return true;
			}));

		// Execute: Propagate status from envelope to children
		$this->service->propagateStatusToChildren($envelopeId, FileEntity::STATUS_ABLE_TO_SIGN);

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
		$envelope->setStatus(FileEntity::STATUS_ABLE_TO_SIGN);

		$child1 = new FileEntity();
		$child1->setId(10);
		$child1->setStatus(FileEntity::STATUS_DRAFT);

		$child2 = new FileEntity();
		$child2->setId(11);
		$child2->setStatus(FileEntity::STATUS_DRAFT);

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

		$this->service->propagateStatusToChildren($envelopeId, FileEntity::STATUS_ABLE_TO_SIGN);

		// Verify both children were updated
		$this->assertCount(2, $updatedFiles);

		foreach ($updatedFiles as $updated) {
			$this->assertEquals(FileEntity::STATUS_ABLE_TO_SIGN, $updated['status']);
			$this->assertContains($updated['id'], [10, 11]);
		}
	}
}
