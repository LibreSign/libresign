<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Envelope;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\Envelope\EnvelopeStatusDeterminer;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class EnvelopeStatusDeterminerTest extends TestCase {
	private EnvelopeStatusDeterminer $determiner;

	public function setUp(): void {
		parent::setUp();
		$this->determiner = new EnvelopeStatusDeterminer();
	}

	#[DataProvider('statusProvider')]
	public function testDetermineStatus(
		array $childFilesData,
		array $signRequestsData,
		int $expectedStatus
	): void {
		$childFiles = [];
		$signRequestsMap = [];

		foreach ($childFilesData as $fileId) {
			$file = new FileEntity();
			$file->setId($fileId);
			$childFiles[] = $file;

			$requests = [];
			if (isset($signRequestsData[$fileId])) {
				foreach ($signRequestsData[$fileId] as $signedStatus) {
					$signRequest = new SignRequest();
					$signRequest->setFileId($fileId);
					if ($signedStatus) {
						$signRequest->setSigned(new \DateTime());
					}
					$requests[] = $signRequest;
				}
			}
			$signRequestsMap[$fileId] = $requests;
		}

		$result = $this->determiner->determineStatus($childFiles, $signRequestsMap);

		$this->assertEquals($expectedStatus, $result);
	}

	public static function statusProvider(): array {
		return [
			'no sign requests returns DRAFT' => [
				'childFilesData' => [1, 2],
				'signRequestsData' => [
					1 => [],
					2 => [],
				],
				'expectedStatus' => FileStatus::DRAFT->value,
			],
			'all unsigned returns ABLE_TO_SIGN' => [
				'childFilesData' => [1, 2],
				'signRequestsData' => [
					1 => [false, false],
					2 => [false],
				],
				'expectedStatus' => FileStatus::ABLE_TO_SIGN->value,
			],
			'partially signed returns PARTIAL_SIGNED' => [
				'childFilesData' => [1, 2],
				'signRequestsData' => [
					1 => [true, false],
					2 => [false],
				],
				'expectedStatus' => FileStatus::PARTIAL_SIGNED->value,
			],
			'all signed returns SIGNED' => [
				'childFilesData' => [1, 2],
				'signRequestsData' => [
					1 => [true, true],
					2 => [true],
				],
				'expectedStatus' => FileStatus::SIGNED->value,
			],
			'single file with one unsigned request' => [
				'childFilesData' => [1],
				'signRequestsData' => [
					1 => [false],
				],
				'expectedStatus' => FileStatus::ABLE_TO_SIGN->value,
			],
			'single file with one signed request' => [
				'childFilesData' => [1],
				'signRequestsData' => [
					1 => [true],
				],
				'expectedStatus' => FileStatus::SIGNED->value,
			],
			'multiple files, some signed, some not' => [
				'childFilesData' => [1, 2, 3],
				'signRequestsData' => [
					1 => [true],
					2 => [false, false],
					3 => [true, false],
				],
				'expectedStatus' => FileStatus::PARTIAL_SIGNED->value,
			],
			'empty child files returns DRAFT' => [
				'childFilesData' => [],
				'signRequestsData' => [],
				'expectedStatus' => FileStatus::DRAFT->value,
			],
		];
	}

	public function testDetermineStatusWithMissingSignRequestsMap(): void {
		$file = new FileEntity();
		$file->setId(99);

		// File ID not present in signRequestsMap - should be treated as no requests
		$result = $this->determiner->determineStatus([$file], []);

		$this->assertEquals(FileStatus::DRAFT->value, $result);
	}
}
