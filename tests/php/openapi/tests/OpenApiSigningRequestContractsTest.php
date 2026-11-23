<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenApiSigningRequestContractsTest extends TestCase {
	private static function loadSpec(string $name): array {
		$content = file_get_contents(dirname(__DIR__, 3) . '/' . $name);
		self::assertNotFalse($content);
		return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
	}

	private static function operation(array $spec, string $operationId): array {
		foreach ($spec['paths'] as $path) {
			foreach ($path as $operation) {
				if (is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
					return $operation;
				}
			}
		}
		self::fail('Missing operation: ' . $operationId);
	}

	private static function requestProperty(array $spec, string $operationId, string $name): array {
		$operation = self::operation($spec, $operationId);
		$schema = $operation['requestBody']['content']['application/json']['schema'];
		self::assertArrayHasKey($name, $schema['properties']);
		return $schema['properties'][$name];
	}

	public static function signingOperations(): array {
		return [
			'file ID' => ['sign_file-sign-by-file-id'],
			'signer UUID' => ['sign_file-sign-by-signer-uuid'],
		];
	}

	#[DataProvider('signingOperations')]
	public function testSigningElementsAreAnOptionalTypedList(string $operationId): void {
		$spec = self::loadSpec('openapi-full.json');
		$operation = self::operation($spec, $operationId);
		$schema = $operation['requestBody']['content']['application/json']['schema'];
		self::assertNotContains('elements', $schema['required']);
		$elements = $schema['properties']['elements'];
		self::assertSame('array', $elements['type']);
		self::assertSame([], $elements['default']);
		self::assertSame('#/components/schemas/SigningElement', $elements['items']['$ref']);
		$item = $spec['components']['schemas']['SigningElement'];
		self::assertSame(['documentElementId'], $item['required']);
		self::assertSame('integer', $item['properties']['documentElementId']['type']);
		self::assertSame('integer', $item['properties']['profileNodeId']['type']);
	}

	#[DataProvider('signingOperations')]
	public function testSigningGeolocationHasTypedStatusAndOptionalFields(string $operationId): void {
		$spec = self::loadSpec('openapi-full.json');
		$geolocation = self::requestProperty($spec, $operationId, 'deviceGeolocation');
		self::assertNull($geolocation['default']);
		self::assertTrue($geolocation['nullable']);
		self::assertSame([['$ref' => '#/components/schemas/SignerDeviceGeolocation']], $geolocation['allOf']);
		self::assertArrayNotHasKey('anyOf', $geolocation);
		$device = $spec['components']['schemas']['SignerDeviceGeolocation'];
		self::assertSame(['status'], $device['required']);
		self::assertSame('#/components/schemas/GeolocationCollectionStatus', $device['properties']['status']['$ref']);
		foreach (['latitude', 'longitude', 'accuracy'] as $name) {
			self::assertSame('number', $device['properties'][$name]['type']);
		}
		self::assertSame('integer', $device['properties']['timestamp']['type']);
	}

	public function testSavedSignatureCreationAcceptsTypedList(): void {
		$spec = self::loadSpec('openapi-full.json');
		$elements = self::requestProperty($spec, 'signature_elements-create-signature-element', 'elements');
		self::assertSame('array', $elements['type']);
		self::assertSame('#/components/schemas/SavedSignatureElement', $elements['items']['$ref']);
		$item = $spec['components']['schemas']['SavedSignatureElement'];
		self::assertSame(['signature', 'initial', 'date', 'datetime', 'text'], $item['properties']['type']['enum']);
		self::assertSame('#/components/schemas/SavedSignatureFile', $item['properties']['file']['$ref']);
		foreach (['elementId', 'nodeId'] as $field) {
			self::assertSame('integer', $item['properties'][$field]['type']);
		}
	}

	public function testSavedSignatureUpdateUsesTypedFile(): void {
		$spec = self::loadSpec('openapi-full.json');
		$file = self::requestProperty($spec, 'signature_elements-patch-signature-element', 'file');
		self::assertSame([['$ref' => '#/components/schemas/SavedSignatureFile']], $file['allOf']);
		self::assertArrayNotHasKey('default', $file);
		$definition = $spec['components']['schemas']['SavedSignatureFile'];
		foreach (['base64', 'url'] as $field) {
			self::assertSame('string', $definition['properties'][$field]['type']);
		}
		self::assertArrayNotHasKey('required', $definition);
	}

	/** @return array<string, array{string}> */
	public static function requestPolicyOperations(): array {
		return [
			'new request' => ['request_signature-request-signature'],
			'updated request' => ['request_signature-update-signature-request'],
		];
	}

	#[DataProvider('requestPolicyOperations')]
	public function testRequestPolicyReflectsOverridesAndActiveContext(string $operationId): void {
		$spec = self::loadSpec('openapi-full.json');
		$policy = self::requestProperty($spec, $operationId, 'policy');
		self::assertTrue($policy['nullable']);
		self::assertNull($policy['default']);
		self::assertSame([['$ref' => '#/components/schemas/RequestPolicy']], $policy['allOf']);
		$request = $spec['components']['schemas']['RequestPolicy'];
		self::assertSame('object', $request['type']);
		self::assertArrayNotHasKey('required', $request);
		self::assertSame('#/components/schemas/RequestPolicyOverrides', $request['properties']['overrides']['$ref']);
		self::assertSame('#/components/schemas/PolicyActiveContext', $request['properties']['activeContext']['$ref']);
		$overrides = $spec['components']['schemas']['RequestPolicyOverrides'];
		self::assertSame('object', $overrides['type']);
		$branches = $overrides['additionalProperties']['anyOf'];
		self::assertContains('#/components/schemas/EffectivePolicyValue', array_column($branches, '$ref'));
		self::assertContains('array', array_column($branches, 'type'));
		$activeContext = $spec['components']['schemas']['PolicyActiveContext'];
		self::assertSame(['type', 'id'], $activeContext['required']);
		self::assertSame(['group'], $activeContext['properties']['type']['enum']);
		self::assertSame(1, $activeContext['properties']['id']['minLength']);
	}

	/** @return array<string, array{string}> */
	public static function signingCodeOperations(): array {
		return [
			'signer UUID' => ['sign_file-request-code-by-signer-uuid'],
			'file ID' => ['sign_file-request-code-by-file-id'],
		];
	}

	#[DataProvider('signingCodeOperations')]
	public function testSigningCodeIdentificationSupportsConfiguredMethodsAndNull(string $operationId): void {
		$spec = self::loadSpec('openapi-full.json');
		$body = self::operation($spec, $operationId)['requestBody'];
		self::assertFalse($body['required']);
		$schema = $body['content']['application/json']['schema'];
		self::assertNotContains('identifyMethod', $schema['required'] ?? []);
		$identifyMethod = $schema['properties']['identifyMethod'];
		self::assertSame('string', $identifyMethod['type']);
		self::assertTrue($identifyMethod['nullable']);
		self::assertNull($identifyMethod['default']);
		self::assertArrayNotHasKey('enum', $identifyMethod, 'Configured methods must not be limited to account/email');
		$signMethod = $schema['properties']['signMethod'];
		self::assertSame('string', $signMethod['type']);
		self::assertTrue($signMethod['nullable']);
		self::assertNull($signMethod['default']);
	}

	public function testIdDocApprovalDocumentsTheBodyFlagAndFrontendQuery(): void {
		$spec = self::loadSpec('openapi-full.json');
		$operation = self::operation($spec, 'sign_file-request-code-by-signer-uuid');
		$schema = $operation['requestBody']['content']['application/json']['schema'];
		$flag = $schema['properties']['idDocApproval'];
		self::assertSame('string', $flag['type']);
		self::assertTrue($flag['nullable']);
		self::assertNull($flag['default']);
		self::assertArrayNotHasKey('enum', $flag);
		self::assertStringContainsString('?idDocApproval=true', $operation['description'] ?? '');

		$signOperation = self::operation($spec, 'sign_file-sign-by-signer-uuid');
		self::assertArrayNotHasKey('idDocApproval', $signOperation['requestBody']['content']['application/json']['schema']['properties']);
		self::assertStringContainsString('?idDocApproval=true', $signOperation['description'] ?? '');
	}

	public function testAccountConfigValueUsesARequiredHeterogeneousJsonContract(): void {
		$spec = self::loadSpec('openapi-full.json');
		$operation = self::operation($spec, 'account-set-config');
		self::assertTrue($operation['requestBody']['required']);
		$schema = $operation['requestBody']['content']['application/json']['schema'];
		self::assertSame(['value'], $schema['required']);
		self::assertSame([['$ref' => '#/components/schemas/UserConfigValue']], $schema['properties']['value']['allOf']);

		$configValue = $spec['components']['schemas']['UserConfigValue'];
		$branches = $configValue['anyOf'] ?? $configValue['oneOf'] ?? [];
		self::assertContains('boolean', array_column($branches, 'type'));
		self::assertContains('string', array_column($branches, 'type'));
		self::assertContains('number', array_column($branches, 'type'));
		self::assertContains('object', array_column($branches, 'type'));
		self::assertContains('array', array_column($branches, 'type'));
		self::assertFalse($configValue['nullable'] ?? false);
	}

	/** @return array<string, array{string}> */
	public static function compoundPolicyOperations(): array {
		return [
			'system' => ['policy-set-system-compound'],
			'group' => ['policy-set-group-compound'],
			'own user' => ['policy-set-user-preference-compound'],
			'target user' => ['policy-set-user-policy-for-user-compound'],
		];
	}

	#[DataProvider('compoundPolicyOperations')]
	public function testCompoundPoliciesReferenceSharedValueMap(string $operationId): void {
		$spec = self::loadSpec('openapi-full.json');
		$values = self::requestProperty($spec, $operationId, 'values');
		self::assertSame([['$ref' => '#/components/schemas/PolicyValueMap']], $values['allOf']);
		self::assertArrayNotHasKey('default', $values);
		$map = $spec['components']['schemas']['PolicyValueMap'];
		self::assertSame('object', $map['type']);
		self::assertSame('#/components/schemas/EffectivePolicyValue', $map['additionalProperties']['$ref']);
	}

	public function testExistingUnmodifiedOperationsRemainExposed(): void {
		$spec = self::loadSpec('openapi-full.json');
		foreach (['account-me', 'account-set-config', 'certificate_policy-get-certificate-policy'] as $operationId) {
			self::assertNotEmpty(self::operation($spec, $operationId));
		}
	}
}
