<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenApiBinaryMediaTypesTest extends TestCase {
	public static function mediaTypeProvider(): array {
		return [
			'CRL download' => ['openapi-full.json', 'crl-get-revocation-list', 'application/pkix-crl'],
			'CRL download in the default scope' => ['openapi.json', 'crl-get-revocation-list', 'application/pkix-crl'],
			'admin background image' => ['openapi-full.json', 'admin-signature-background-get', 'image/png'],
			'certificate policy PDF' => ['openapi-full.json', 'certificate_policy-get-certificate-policy', 'application/pdf'],
		];
	}

	#[DataProvider('mediaTypeProvider')]
	public function testBinaryResponseDeclaresItsFixedMediaType(string $file, string $operationId, string $mediaType): void {
		$source = file_get_contents(dirname(__DIR__, 4) . '/' . $file);
		self::assertNotFalse($source);
		$spec = json_decode($source, true, 512, JSON_THROW_ON_ERROR);

		$content = null;
		foreach ($spec['paths'] as $methods) {
			foreach ($methods as $operation) {
				if (is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
					$content = $operation['responses']['200']['content'] ?? null;
				}
			}
		}

		// Thumbnails and signature element previews keep */* on purpose: their
		// media type depends on the previewed file.
		self::assertIsArray($content, $operationId);
		self::assertSame([$mediaType], array_keys($content), $operationId);
		self::assertSame(['type' => 'string', 'format' => 'binary'], $content[$mediaType]['schema'], $operationId);
	}
}
