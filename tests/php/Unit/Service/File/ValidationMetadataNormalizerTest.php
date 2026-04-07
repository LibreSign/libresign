<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Service\File\ValidationMetadataNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidationMetadataNormalizerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	#[DataProvider('provideNormalizationScenarios')]
	public function testNormalizeMetadataContract(
		array $metadata,
		string $fileName,
		int $totalPages,
		array $expectedSubset,
		array $missingKeys = [],
	): void {
		$normalized = ValidationMetadataNormalizer::normalize($metadata, $fileName, $totalPages);

		foreach ($expectedSubset as $key => $value) {
			$this->assertArrayHasKey($key, $normalized);
			$this->assertSame($value, $normalized[$key]);
		}

		foreach ($missingKeys as $key) {
			$this->assertArrayNotHasKey($key, $normalized);
		}
	}

	public static function provideNormalizationScenarios(): array {
		return [
			'normalizes required keys from filename and page count' => [
				'metadata' => [],
				'fileName' => 'contract.PDF',
				'totalPages' => 5,
				'expectedSubset' => [
					'p' => 5,
					'extension' => 'pdf',
				],
			],
			'keeps provided non-empty extension' => [
				'metadata' => ['extension' => 'docx'],
				'fileName' => 'contract.PDF',
				'totalPages' => 1,
				'expectedSubset' => [
					'p' => 1,
					'extension' => 'docx',
				],
			],
			'clamps negative page count to zero' => [
				'metadata' => [],
				'fileName' => 'contract.pdf',
				'totalPages' => -2,
				'expectedSubset' => [
					'p' => 0,
					'extension' => 'pdf',
				],
			],
			'removes optional keys with invalid types' => [
				'metadata' => [
					'original_file_deleted' => '1',
					'pdfVersion' => 17,
					'status_changed_at' => 123,
				],
				'fileName' => 'contract.pdf',
				'totalPages' => 1,
				'expectedSubset' => [
					'p' => 1,
					'extension' => 'pdf',
				],
				'missingKeys' => ['original_file_deleted', 'pdfVersion', 'status_changed_at'],
			],
			'normalizes dimensions and strips invalid entries' => [
				'metadata' => [
					'd' => [
						['w' => '100', 'h' => 200],
						['w' => 'x', 'h' => 300],
					],
				],
				'fileName' => 'contract.pdf',
				'totalPages' => 2,
				'expectedSubset' => [
					'p' => 2,
					'extension' => 'pdf',
					'd' => [
						['w' => 100.0, 'h' => 200.0],
					],
				],
			],
			'removes dimensions key when all entries are invalid' => [
				'metadata' => [
					'd' => [
						['w' => 'x', 'h' => 'y'],
					],
				],
				'fileName' => 'contract.pdf',
				'totalPages' => 1,
				'expectedSubset' => [
					'p' => 1,
					'extension' => 'pdf',
				],
				'missingKeys' => ['d'],
			],
		];
	}
}
