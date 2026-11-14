<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\TestCase;

class OpenApiPolicyValueTest extends TestCase {
	public function testPolicyObjectsAllowScalarAndNestedProperties(): void {
		foreach (['openapi.json', 'openapi-administration.json', 'openapi-full.json'] as $file) {
			$spec = json_decode(file_get_contents(dirname(__DIR__, 3) . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
			$value = $spec['components']['schemas']['EffectivePolicyValue'];
			$this->assertTrue($value['nullable'], $file);
			$objects = array_values(array_filter($value['anyOf'], static fn (array $schema): bool => $schema['type'] === 'object'));
			$this->assertCount(1, $objects, $file);
			// Nested booleans, strings, lists and null must not be restricted to objects.
			$this->assertTrue($objects[0]['additionalProperties'], $file);
		}
	}

	public function testIndividualAndCompoundWritesShareTheResponseValueContract(): void {
		$spec = json_decode(file_get_contents(dirname(__DIR__, 3) . '/openapi-full.json'), true, 512, JSON_THROW_ON_ERROR);
		$checked = [];
		foreach ($spec['paths'] as $path) {
			foreach ($path as $operation) {
				$id = $operation['operationId'] ?? '';
				if (!str_starts_with($id, 'policy-set-')) {
					continue;
				}
				$schema = $operation['requestBody']['content']['application/json']['schema'];
				$properties = $schema['properties'];
				if (isset($properties['value'])) {
					$value = $properties['value'];
					$this->assertContains('value', $schema['required'] ?? [], $id);
					$this->assertArrayNotHasKey('$ref', $value, $id);
					$this->assertArrayNotHasKey('default', $value, $id);
					$this->assertSame(['$ref'], array_keys($value['allOf'][0]), $id);
					$this->assertSame('#/components/schemas/EffectivePolicyValue', $value['allOf'][0]['$ref'], $id);
					$this->assertNotSame('', $value['description'], $id);
				} else {
					$value = $properties['values']['additionalProperties'];
					$this->assertSame('#/components/schemas/EffectivePolicyValue', $value['$ref'], $id);
					$this->assertArrayNotHasKey('description', $value, $id);
					$this->assertArrayNotHasKey('default', $value, $id);
				}
				$checked[] = $id;
			}
		}
		$this->assertCount(8, $checked);
	}
}
