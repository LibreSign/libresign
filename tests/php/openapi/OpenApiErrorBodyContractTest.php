<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The documented error bodies must match what the controllers return, so that
 * generated clients do not type a body that is never sent.
 */
final class OpenApiErrorBodyContractTest extends TestCase {
	private const BASE = '/ocs/v2.php/apps/libresign/api/{apiVersion}/';

	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testRequestSignatureOnlyReturnsAMessageWith422(string $file): void {
		$paths = $this->load($file)['paths'];

		foreach (['post', 'patch'] as $method) {
			self::assertSame(
				'#/components/schemas/MessageResponse',
				$this->errorData($paths[self::BASE . 'request-signature'][$method], '422')['$ref'] ?? null,
				$method,
			);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testRemoveSignerDocumentsTheMessageItReturns(string $file): void {
		$operation = $this->load($file)['paths'][self::BASE . 'sign/file_id/{fileId}/{signRequestId}']['delete'];

		self::assertSame('#/components/schemas/MessageResponse', $this->errorData($operation, '422')['$ref'] ?? null);
	}

	#[DataProvider('specificationProvider')]
	public function testDeleteOperationsDoNotDocumentAnUnreachable422(string $file): void {
		$paths = $this->load($file)['paths'];

		foreach (['file/file_id/{fileId}', 'sign/file_id/{fileId}'] as $path) {
			self::assertArrayNotHasKey('422', $paths[self::BASE . $path]['delete']['responses'], $path);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testNotifyKeepsBothRealBodiesAt422(string $file): void {
		// canRequestSign() throws an action/errors body, other validations a message.
		$paths = $this->load($file)['paths'];

		foreach (['notify/signers', 'notify/signer'] as $path) {
			$data = $this->errorData($paths[self::BASE . $path]['post'], '422');
			self::assertEqualsCanonicalizing(
				['#/components/schemas/MessageResponse', '#/components/schemas/ActionErrorResponse'],
				array_column($data['anyOf'] ?? [], '$ref'),
				$path,
			);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testDetailedFileNodeIdIsNullable(string $file): void {
		// libresign_file.node_id is nullable and the value is copied as is.
		$nodeId = $this->load($file)['components']['schemas']['DetailedFile']['properties']['nodeId'];

		self::assertTrue($nodeId['nullable'] ?? false);
	}

	private function errorData(array $operation, string $status): array {
		return $operation['responses'][$status]['content']['application/json']['schema']['properties']['ocs']['properties']['data'] ?? [];
	}

	private function load(string $file): array {
		$source = file_get_contents(dirname(__DIR__, 3) . '/' . $file);
		self::assertNotFalse($source);

		return json_decode($source, true, 512, JSON_THROW_ON_ERROR);
	}
}
