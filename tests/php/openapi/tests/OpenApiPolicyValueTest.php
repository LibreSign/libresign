<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpenApiPolicyValueTest extends TestCase {
	public static function specFiles(): array {
		return [
			'public' => ['openapi.json'],
			'administration' => ['openapi-administration.json'],
			'full' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specFiles')]
	public function testPolicyObjectsAllowScalarAndNestedProperties(string $file): void {
		$spec = json_decode(file_get_contents(dirname(__DIR__, 4) . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
		$value = $spec['components']['schemas']['EffectivePolicyValue'];
		$this->assertTrue($value['nullable'], $file);
		$objects = array_values(array_filter($value['oneOf'], static fn (array $schema): bool => $schema['type'] === 'object'));
		$this->assertCount(1, $objects, $file);
		// Nested booleans, strings, lists and null must not be restricted to objects.
		$this->assertTrue($objects[0]['additionalProperties'], $file);
	}

	public function testIndividualAndCompoundWritesShareTheResponseValueContract(): void {
		$spec = json_decode(file_get_contents(dirname(__DIR__, 4) . '/openapi-full.json'), true, 512, JSON_THROW_ON_ERROR);
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
					$values = $properties['values'];
					$this->assertArrayNotHasKey('$ref', $values, $id);
					$this->assertArrayNotHasKey('default', $values, $id);
					$this->assertSame([['$ref' => '#/components/schemas/PolicyValueMap']], $values['allOf'], $id);
					$this->assertNotSame('', $values['description'], $id);
					$map = $spec['components']['schemas']['PolicyValueMap'];
					$this->assertSame('object', $map['type'], $id);
					$this->assertSame(['$ref' => '#/components/schemas/EffectivePolicyValue'], $map['additionalProperties'], $id);
				}
				$checked[] = $id;
			}
		}
		$this->assertCount(8, $checked);
	}
}
