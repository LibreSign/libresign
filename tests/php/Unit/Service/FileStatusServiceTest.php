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
use PHPUnit\Framework\TestCase;

class FileStatusServiceTest extends TestCase {
	private FileMapper $fileMapper;
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
}
