<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenApiIdDocSchemaTest extends TestCase {
	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testIdDocumentRecordHasADomainSpecificSchemaName(string $file): void {
		$source = file_get_contents(dirname(__DIR__, 3) . '/' . $file);
		self::assertNotFalse($source);

		$schemas = json_decode($source, true, 512, JSON_THROW_ON_ERROR)['components']['schemas'];

		// A schema named File collides with the global File type of TypeScript
		// and is too generic: other schemas describe different file records.
		self::assertArrayNotHasKey('File', $schemas);
		self::assertArrayHasKey('IdDocFile', $schemas);
		foreach (['IdDocsListResponse', 'IdDocsApprovalListResponse'] as $response) {
			self::assertSame(
				'#/components/schemas/IdDocFile',
				$schemas[$response]['properties']['data']['items']['$ref'] ?? null,
				$response,
			);
		}
	}
}
