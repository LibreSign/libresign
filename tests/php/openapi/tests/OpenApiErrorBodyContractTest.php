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
 * The documented 422 bodies must match what the API really sends.
 *
 * Two sources exist. The #[RequireManager] middleware calls canRequestSign()
 * before the controller runs and, for a user without permission, answers 422
 * with an {action, errors} body (ActionErrorResponse). The controller catch
 * blocks answer {message} (MessageResponse). Documenting only one of them
 * would type a body that is not the only one sent.
 */
final class OpenApiErrorBodyContractTest extends TestCase {
	private const BASE = '/ocs/v2.php/apps/libresign/api/{apiVersion}/';
	private const MESSAGE = '#/components/schemas/MessageResponse';
	private const ACTION_ERROR = '#/components/schemas/ActionErrorResponse';

	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testRequestSignatureDocumentsBothBodiesAt422(string $file): void {
		$paths = $this->load($file)['paths'];

		foreach (['post', 'patch'] as $method) {
			$this->assertBothBodies($paths[self::BASE . 'request-signature'][$method], $method);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testRemoveSignerDocumentsBothBodiesAt422(string $file): void {
		// The catch block answers {message} with 422 when the exception code is 422.
		$this->assertBothBodies(
			$this->load($file)['paths'][self::BASE . 'sign/file_id/{fileId}/{signRequestId}']['delete'],
			'removeSigner',
		);
	}

	#[DataProvider('specificationProvider')]
	public function testDeleteOperationsDocumentBothBodiesAt422AndTheUnexpectedFailureAt500(string $file): void {
		// The middleware rejects a user without permission with 422 before the
		// method runs; the catch block answers {message} with 422 for a business
		// failure and 500 for an unexpected one.
		$paths = $this->load($file)['paths'];

		foreach (['file/file_id/{fileId}', 'sign/file_id/{fileId}'] as $path) {
			$operation = $paths[self::BASE . $path]['delete'];
			$this->assertBothBodies($operation, $path);
			self::assertSame(self::MESSAGE, $this->errorData($operation, '500')['$ref'] ?? null, $path);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testFailuresAreNotDocumentedAsUnauthorized(string $file): void {
		// 401 means "not authenticated". The operations below used to answer 401
		// for validation and business failures; only the untyped framework 401
		// (data: {}) may remain.
		$paths = $this->load($file)['paths'];
		$operations = [
			'account/signature' => 'post',
			'file/file_id/{fileId}' => 'delete',
			'sign/file_id/{fileId}' => 'delete',
			'sign/file_id/{fileId}/{signRequestId}' => 'delete',
		];
		if ($file === 'openapi-full.json') {
			$operations += ['admin/certificate/openssl' => 'post', 'admin/certificate/cfssl' => 'post'];
		}

		foreach ($operations as $path => $method) {
			$data = $this->errorData($paths[self::BASE . $path][$method], '401');
			self::assertArrayNotHasKey('$ref', $data, $path);
			self::assertArrayNotHasKey('anyOf', $data, $path);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testPfxGenerationDocumentsItsFailures(string $file): void {
		$operation = $this->load($file)['paths'][self::BASE . 'account/signature']['post'];

		self::assertSame(self::MESSAGE, $this->errorData($operation, '422')['$ref'] ?? null);
		self::assertSame(self::MESSAGE, $this->errorData($operation, '500')['$ref'] ?? null);
	}

	public function testCertificateGenerationDocumentsItsFailures(): void {
		$paths = $this->load('openapi-full.json')['paths'];

		foreach (['admin/certificate/openssl', 'admin/certificate/cfssl'] as $path) {
			$operation = $paths[self::BASE . $path]['post'];
			self::assertSame(self::MESSAGE, $this->errorData($operation, '422')['$ref'] ?? null, $path);
			self::assertSame(self::MESSAGE, $this->errorData($operation, '500')['$ref'] ?? null, $path);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testFileUploadsDocumentBothBodiesAt422(string $file): void {
		// The middleware rejects a user without permission before the method; the
		// catch block answers {message}.
		$paths = $this->load($file)['paths'];

		$this->assertBothBodies($paths[self::BASE . 'file']['post'], 'file-save');
		$this->assertBothBodies($paths[self::BASE . 'file/{uuid}/add-file']['post'], 'file-add-file-to-envelope');
	}

	#[DataProvider('specificationProvider')]
	public function testIdentifySearchDocumentsTheMiddleware422(string $file): void {
		// The method has no try block: only the middleware can answer 422.
		$operation = $this->load($file)['paths'][self::BASE . 'identify-account/search']['get'];

		self::assertSame(self::ACTION_ERROR, $this->errorData($operation, '422')['$ref'] ?? null);
	}

	#[DataProvider('specificationProvider')]
	public function testNotifyKeepsBothRealBodiesAt422(string $file): void {
		// canRequestSign() throws an action/errors body, other validations a message body.
		$paths = $this->load($file)['paths'];

		foreach (['notify/signers', 'notify/signer'] as $path) {
			$this->assertBothBodies($paths[self::BASE . $path]['post'], $path);
		}
	}

	#[DataProvider('specificationProvider')]
	public function testFileSchemasWithTheEntityNodeIdAreNullable(string $file): void {
		// libresign_file.node_id is nullable (BeforeNodeDeletedListener clears it
		// when the original file is deleted) and the value is copied as is.
		$schemas = $this->load($file)['components']['schemas'];

		foreach (['DetailedFile', 'ValidatedFile', 'ValidatedChildFile'] as $schema) {
			self::assertTrue($schemas[$schema]['properties']['nodeId']['nullable'] ?? false, $schema);
		}
	}

	private function assertBothBodies(array $operation, string $label): void {
		$data = $this->errorData($operation, '422');

		self::assertEqualsCanonicalizing(
			[self::MESSAGE, self::ACTION_ERROR],
			array_column($data['anyOf'] ?? [], '$ref'),
			$label,
		);
	}

	private function errorData(array $operation, string $status): array {
		return $operation['responses'][$status]['content']['application/json']['schema']['properties']['ocs']['properties']['data'] ?? [];
	}

	private function load(string $file): array {
		$source = file_get_contents(dirname(__DIR__, 4) . '/' . $file);
		self::assertNotFalse($source);

		return json_decode($source, true, 512, JSON_THROW_ON_ERROR);
	}
}
