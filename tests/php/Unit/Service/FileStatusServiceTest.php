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

	#[DataProvider('fileStatusUpgradeScenarios')]
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

	public static function fileStatusUpgradeScenarios(): array {
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

	#[DataProvider('fileStatusNotificationScenarios')]
	public function testCanNotifySigners(?int $fileStatus, bool $expected): void {
		$result = $this->service->canNotifySigners($fileStatus);
		$this->assertEquals($expected, $result);
	}

	public static function fileStatusNotificationScenarios(): array {
		return [
			[FileEntity::STATUS_DRAFT, false],
			[FileEntity::STATUS_ABLE_TO_SIGN, true],
			[FileEntity::STATUS_PARTIAL_SIGNED, false],
			[FileEntity::STATUS_SIGNED, false],
			[FileEntity::STATUS_DELETED, false],
			[null, false],
		];
	}
}
