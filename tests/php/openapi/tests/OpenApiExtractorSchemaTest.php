<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use OpenAPIExtractor\OpenApiType;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPUnit\Framework\TestCase;

class OpenApiExtractorSchemaTest extends TestCase {
	// TODO(#9453): Remove .patches/openapi-extractor-mixed-object.diff
	// when an unpatched upstream nextcloud/openapi-extractor release passes
	// the mixed-property, $ref metadata and object-default regression tests.
	public static function setUpBeforeClass(): void {
		// The unpatched-extractor CI check points this to a pristine install.
		$autoload = getenv('OPENAPI_EXTRACTOR_AUTOLOAD')
			?: dirname(__DIR__, 4) . '/vendor-bin/openapi-extractor/vendor/autoload.php';
		require_once $autoload;
		$GLOBALS['readableAppID'] = 'Libresign';
	}

	public function testStringKeyedMixedMapAllowsAnyPropertyValue(): void {
		$type = OpenApiType::resolve('array<string, mixed>', [], new GenericTypeNode(
			new IdentifierTypeNode('array'),
			[
				new IdentifierTypeNode('string'),
				new IdentifierTypeNode('mixed'),
			],
		));

		$schema = $this->schema($type);
		$this->assertSame('object', $schema['type']);
		$this->assertTrue($schema['additionalProperties']);
	}

	public function testNestedMixedMapKeepsOnlyItsInnerValuesUnrestricted(): void {
		$type = OpenApiType::resolve('nested map', [], new GenericTypeNode(
			new IdentifierTypeNode('array'),
			[
				new IdentifierTypeNode('string'),
				new GenericTypeNode(new IdentifierTypeNode('array'), [
					new IdentifierTypeNode('string'),
					new IdentifierTypeNode('mixed'),
				]),
			],
		));

		$this->assertSame([
			'type' => 'object',
			'additionalProperties' => ['type' => 'object', 'additionalProperties' => true],
		], $this->schema($type));
	}

	public function testTypedMapDoesNotBecomeUnrestricted(): void {
		$type = OpenApiType::resolve('string map', [], new GenericTypeNode(
			new IdentifierTypeNode('array'),
			[new IdentifierTypeNode('string'), new IdentifierTypeNode('string')],
		));

		$this->assertSame([
			'type' => 'object',
			'additionalProperties' => ['type' => 'string'],
		], $this->schema($type));
	}

	public function testBareReferenceStaysBare(): void {
		$type = new OpenApiType(context: 'bare reference', ref: '#/components/schemas/NewFile');

		$this->assertSame(['$ref' => '#/components/schemas/NewFile'], $this->schema($type));
	}

	public function testArrayAndInlineObjectDefaultsKeepTheirJsonTypes(): void {
		$schemas = ['Items' => ['type' => 'array', 'items' => ['type' => 'string']]];
		$reference = new OpenApiType(
			context: 'array reference', ref: '#/components/schemas/Items',
			hasDefaultValue: true, defaultValue: [], description: 'Items to save',
		);
		$this->assertSame([], $this->schema($reference, $schemas)['default']);
		$this->assertSame([['$ref' => '#/components/schemas/Items']], $this->schema($reference, $schemas)['allOf']);

		$object = new OpenApiType(context: 'inline object', type: 'object', hasDefaultValue: true, defaultValue: []);
		$this->assertInstanceOf(\stdClass::class, $object->toArray([])['default']);
	}

	public function testAliasTypedRequestParameterKeepsDescriptionAndDefaultOutsideRef(): void {
		$type = OpenApiType::resolve('value', [
			'LibresignEffectivePolicyValue' => new IdentifierTypeNode('string'),
		], new IdentifierTypeNode('LibresignEffectivePolicyValue'));
		$type->description = 'Policy value to persist for the group.';
		$type->hasDefaultValue = true;
		$type->defaultValue = null;

		$schema = $this->schema($type, ['EffectivePolicyValue' => ['type' => 'string', 'nullable' => true]]);
		$this->assertArrayNotHasKey('$ref', $schema);
		$this->assertSame(['$ref'], array_keys($schema['allOf'][0]));
		$this->assertSame('#/components/schemas/EffectivePolicyValue', $schema['allOf'][0]['$ref']);
		$this->assertSame('Policy value to persist for the group.', $schema['description']);
		$this->assertNull($schema['default']);
	}

	public function testObjectRefDoesNotPromoteAnEmptyArrayDefault(): void {
		$type = OpenApiType::resolve('file', [
			'LibresignNewFile' => new IdentifierTypeNode('string'),
		], new IdentifierTypeNode('LibresignNewFile'));
		$type->description = 'File to save';
		$type->hasDefaultValue = true;
		$type->defaultValue = [];

		$schema = $this->schema($type, [
			'NewFile' => ['type' => 'object'],
		]);
		$this->assertArrayNotHasKey('$ref', $schema);
		$this->assertArrayNotHasKey('default', $schema);
		$this->assertSame('File to save', $schema['description']);
		$this->assertSame(['$ref'], array_keys($schema['allOf'][0]));
		$this->assertSame('#/components/schemas/NewFile', $schema['allOf'][0]['$ref']);
	}

	public function testGeneratedObjectRefsDoNotDeclareArrayDefaults(): void {
		$spec = json_decode(file_get_contents(dirname(__DIR__, 4) . '/openapi-full.json'), true, 512, JSON_THROW_ON_ERROR);
		$expected = [
			'file-save' => ['file' => 'NewFile', 'settings' => 'FolderSettings'],
			'file_element-create-visible-element' => ['coordinates' => 'Coordinate'],
			'file_element-update-visible-element' => ['coordinates' => 'Coordinate'],
			'request_signature-request-signature' => ['file' => 'NewFile', 'settings' => 'FolderSettings'],
			'request_signature-update-signature-request' => ['settings' => 'FolderSettings'],
		];
		$found = [];
		foreach ($spec['paths'] as $path) {
			foreach ($path as $operation) {
				$id = $operation['operationId'] ?? '';
				if (!isset($expected[$id])) {
					continue;
				}
				$properties = $operation['requestBody']['content']['application/json']['schema']['properties'];
				foreach ($expected[$id] as $name => $schemaName) {
					$property = $properties[$name];
					$this->assertArrayNotHasKey('default', $property, $id . '.' . $name);
					$this->assertSame('#/components/schemas/' . $schemaName, $property['allOf'][0]['$ref'], $id . '.' . $name);
					$this->assertSame('object', $spec['components']['schemas'][$schemaName]['type'], $schemaName);
					$this->assertNotSame('', $property['description'], $id . '.' . $name);
				}
				$found[] = $id;
			}
		}
		$this->assertSame(array_keys($expected), $found);
	}

	public function testGeneratedReferencesHaveNoIgnoredSchemaKeywords(): void {
		$visit = function (array $node) use (&$visit): void {
			if (isset($node['$ref'])) {
				$this->assertSame(['$ref'], array_keys($node), $node['$ref']);
			}
			foreach ($node as $child) {
				if (is_array($child)) {
					$visit($child);
				}
			}
		};
		foreach (['openapi.json', 'openapi-administration.json', 'openapi-full.json'] as $file) {
			$spec = json_decode(file_get_contents(dirname(__DIR__, 4) . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
			$visit($spec);
		}
	}

	private function schema(OpenApiType $type, array $schemas = []): array {
		$decoded = json_decode(json_encode($type->toArray($schemas), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
		$this->assertIsArray($decoded);

		return $decoded;
	}
}
