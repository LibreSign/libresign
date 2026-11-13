<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenApiRequestHeadersTest extends TestCase {
	private const SIGN_REQUEST_HEADER = 'libresign-sign-request-uuid';
	private const DESCRIPTION = 'The UUID of the sign request, used to identify the request';

	/** The optional header applies only to methods that can identify a request without a route UUID. */
	private const OPERATIONS = [
		'signature_elements-create-signature-element',
		'signature_elements-get-signature-elements',
		'signature_elements-use-saved-signature-elements',
		'signature_elements-preview-signature-element',
		'signature_elements-patch-signature-element',
		'signature_elements-delete-signature-element',
		'id_docs-add-files',
		'id_docs-delete',
		'id_docs-list-unauthenticated-signer-documents',
	];

	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'administration scope' => ['openapi-administration.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testCustomHeadersMatchActualMiddlewareConsumers(string $file): void {
		$operations = OpenApiFunctionalTags::loadOperations(dirname(__DIR__, 3) . '/' . $file);
		$present = [];
		foreach ($operations as $operation) {
			$id = $operation['operationId'];
			$parameters = $operation['raw']['parameters'] ?? [];
			$headers = array_values(array_filter(
				$parameters,
				static fn (array $parameter): bool => ($parameter['in'] ?? null) === 'header'
					&& strtolower((string)($parameter['name'] ?? '')) === self::SIGN_REQUEST_HEADER,
			));
			self::assertCount(in_array($id, self::OPERATIONS, true) ? 1 : 0, $headers, "Unexpected sign-request header in {$file}: {$id}");
			if ($headers !== []) {
				$present[] = $id;
				self::assertSame(self::SIGN_REQUEST_HEADER, $headers[0]['name']);
				self::assertSame(self::DESCRIPTION, $headers[0]['description']);
				self::assertFalse($headers[0]['required'] ?? false, "The header is optional for {$id}");
				self::assertSame('string', $headers[0]['schema']['type'] ?? null);
			}
		}

		$expected = $file === 'openapi-administration.json' ? [] : self::OPERATIONS;
		sort($present);
		sort($expected);
		self::assertSame($expected, $present, "Missing or unexpected sign-request UUID operations in {$file}");
	}
}
