<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\TestCase;

class OpenApiSigningCodeContractTest extends TestCase {
	public function testOptionalSigningEnumsAcceptExplicitNull(): void {
		foreach (['openapi.json', 'openapi-full.json'] as $file) {
			$spec = json_decode(file_get_contents(dirname(__DIR__, 3) . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
			$checked = [];
			foreach ($spec['paths'] as $path) {
				$operation = $path['post'] ?? [];
				$id = $operation['operationId'] ?? '';
				if (!in_array($id, ['sign_file-request-code-by-signer-uuid', 'sign_file-request-code-by-file-id'], true)) {
					continue;
				}
				$body = $operation['requestBody'];
				$this->assertFalse($body['required'], $id);
				$schema = $body['content']['application/json']['schema'];
				$this->assertSame([], $schema['required'] ?? [], $id);
				$properties = $schema['properties'];
				$this->assertSame(['account', 'email', null], $properties['identifyMethod']['enum']);
				foreach (['identifyMethod', 'signMethod'] as $parameter) {
					$this->assertTrue($properties[$parameter]['nullable']);
					$this->assertArrayHasKey('default', $properties[$parameter]);
					$this->assertNull($properties[$parameter]['default']);
				}
				if ($id === 'sign_file-request-code-by-signer-uuid') {
					$this->assertSame(['true', null], $properties['idDocApproval']['enum']);
					$this->assertTrue($properties['idDocApproval']['nullable']);
				} else {
					$this->assertArrayNotHasKey('idDocApproval', $properties);
				}
				$checked[] = $id;
			}
			$this->assertCount(2, $checked, $file);
		}
	}

	public function testExtractorPreservesNullableEnumsWithoutWideningOtherEnums(): void {
		require_once dirname(__DIR__, 3) . '/vendor-bin/openapi-extractor/vendor/autoload.php';
		foreach ([['account', 'email'], ['true'], [0, 1]] as $values) {
			$type = new \OpenAPIExtractor\OpenApiType(context: 'test', type: is_int($values[0]) ? 'integer' : 'string', enum: $values);
			$this->assertSame($values, $type->toArray([])['enum']);
			$type->nullable = true;
			$this->assertSame([...$values, null], $type->toArray([])['enum']);
			$type->enum[] = null;
			$this->assertSame([...$values, null], $type->toArray([])['enum']);
		}
	}
}
