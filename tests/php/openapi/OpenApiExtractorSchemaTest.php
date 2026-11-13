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
	public static function setUpBeforeClass(): void {
		require_once dirname(__DIR__, 3) . '/vendor-bin/openapi-extractor/vendor/autoload.php';
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

	public function testAliasTypedRequestParameterKeepsDescriptionAndDefaultOutsideRef(): void {
		$type = OpenApiType::resolve('value', [
			'LibresignEffectivePolicyValue' => new IdentifierTypeNode('string'),
		], new IdentifierTypeNode('LibresignEffectivePolicyValue'));
		$type->description = 'Policy value to persist for the group.';
		$type->hasDefaultValue = true;
		$type->defaultValue = null;

		$schema = $this->schema($type);
		$this->assertArrayNotHasKey('$ref', $schema);
		$this->assertSame(['$ref'], array_keys($schema['allOf'][0]));
		$this->assertSame('#/components/schemas/EffectivePolicyValue', $schema['allOf'][0]['$ref']);
		$this->assertSame('Policy value to persist for the group.', $schema['description']);
		$this->assertNull($schema['default']);
	}

	private function schema(OpenApiType $type): array {
		$decoded = json_decode(json_encode($type->toArray([]), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
		$this->assertIsArray($decoded);

		return $decoded;
	}
}
