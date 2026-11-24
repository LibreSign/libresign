<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\TestCase;

/**
 * A scoped CSC connection is administrated by a system, delegated group, or
 * explicitly delegated account actor. Its OCS API must remain documented in
 * the authenticated (not system-administrator-only) OpenAPI scope.
 */
final class ExternalSigningScopeOpenApiTest extends TestCase {
	private const ROUTE = '/ocs/v2.php/apps/libresign/api/{apiVersion}/admin/signing/integrations';

	/** @return array<string, mixed> */
	private static function api(): array {
		$content = file_get_contents(dirname(__DIR__, 3) . '/openapi.json');
		self::assertNotFalse($content);
		return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
	}

	public function testListAcceptsTheAuthorizedPolicyScope(): void {
		$api = self::api();
		self::assertArrayHasKey(self::ROUTE, $api['paths']);
		$operation = $api['paths'][self::ROUTE]['get'];
		$params = [];
		foreach ($operation['parameters'] as $param) {
			$params[$param['name']] = $param;
		}
		foreach (['scopeType' => 'system', 'scopeId' => ''] as $name => $default) {
			self::assertArrayHasKey($name, $params);
			self::assertSame('query', $params[$name]['in']);
			self::assertSame('string', $params[$name]['schema']['type']);
			self::assertSame($default, $params[$name]['schema']['default']);
		}
	}

	public function testCreateAcceptsBothScopeFieldsWithoutRequiringClientInput(): void {
		$api = self::api();
		$create = $api['paths'][self::ROUTE]['post'];
		$schema = $create['requestBody']['content']['application/json']['schema'];
		self::assertSame('string', $schema['properties']['scopeType']['type']);
		self::assertSame('string', $schema['properties']['scopeId']['type']);
		self::assertSame('system', $schema['properties']['scopeType']['default']);
		self::assertSame('', $schema['properties']['scopeId']['default']);
		self::assertNotContains('scopeType', $schema['required']);
		self::assertNotContains('scopeId', $schema['required']);
	}

	public function testIntegrationSummariesIncludeTheirScopeButNoSecrets(): void {
		$api = self::api();
		$summary = $api['components']['schemas']['ExternalIntegrationSummary'];
		foreach (['scopeType', 'scopeId'] as $name) {
			self::assertContains($name, $summary['required']);
			self::assertSame('string', $summary['properties'][$name]['type']);
		}
		self::assertArrayNotHasKey('secrets', $summary['properties']);
		self::assertArrayNotHasKey('accessToken', $summary['properties']);
	}
}
