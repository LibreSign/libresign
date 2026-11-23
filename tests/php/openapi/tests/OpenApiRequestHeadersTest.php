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
	private const OCS_PREFIX = '/ocs/v2.php/apps/libresign';

	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'administration scope' => ['openapi-administration.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testCustomHeadersMatchActualMiddlewareConsumers(string $file): void {
		$expectedRoutes = self::discoverUuidConsumerRoutes();
		$expected = array_keys(array_filter(
			$expectedRoutes,
			static fn (string $scope): bool => $file === 'openapi-full.json'
				|| ($file === 'openapi-administration.json' ? $scope === 'administration' : $scope === 'default'),
		));

		$operations = OpenApiFunctionalTags::loadOperations(dirname(__DIR__, 4) . '/' . $file);
		$present = [];
		foreach ($operations as $operation) {
			$route = OpenApiFunctionalTags::routeKey($operation);
			$parameters = $operation['raw']['parameters'] ?? [];
			$headers = array_values(array_filter(
				$parameters,
				static fn (array $parameter): bool => ($parameter['in'] ?? null) === 'header'
					&& strtolower((string)($parameter['name'] ?? '')) === self::SIGN_REQUEST_HEADER,
			));
			self::assertCount(in_array($route, $expected, true) ? 1 : 0, $headers, "Unexpected sign-request header in {$file}: {$route}");
			if ($headers !== []) {
				$present[] = $route;
				self::assertSame(self::SIGN_REQUEST_HEADER, $headers[0]['name']);
				self::assertSame(self::DESCRIPTION, $headers[0]['description']);
				// A request parameter named uuid is also accepted by InjectionMiddleware.
				self::assertFalse($headers[0]['required'] ?? false, "The header can be replaced by a request UUID for {$route}");
				self::assertSame('string', $headers[0]['schema']['type'] ?? null);
			}
		}

		sort($present);
		sort($expected);
		self::assertSame($expected, $present, "Missing or unexpected sign-request UUID routes in {$file}");
	}

	/** @return array<string, 'default'|'administration'> */
	private static function discoverUuidConsumerRoutes(): array {
		$middlewareSource = file_get_contents(dirname(__DIR__, 4) . '/lib/Middleware/InjectionMiddleware.php');
		self::assertNotFalse($middlewareSource);
		$consumers = self::uuidConsumerAttributesInMiddleware($middlewareSource);

		$files = glob(dirname(__DIR__, 4) . '/lib/Controller/*Controller.php');
		self::assertNotFalse($files);

		$routes = [];
		foreach ($files as $file) {
			$source = file_get_contents($file);
			self::assertNotFalse($source);
			foreach (self::consumerRoutesInSource($source, $file, $consumers) as $route => $scope) {
				self::assertArrayNotHasKey($route, $routes, "Duplicate source route {$route}");
				$routes[$route] = $scope;
			}
		}
		return $routes;
	}

	/**
	 * Discover authorization attributes directly from the middleware's UUID paths.
	 * Attributes checked in handleUuid share its UUID lookup; beforeController's
	 * guards count only when their direct handler reads the request UUID.
	 *
	 * @return list<string>
	 */
	private static function uuidConsumerAttributesInMiddleware(string $source): array {
		$handleUuid = self::middlewareMethodSource($source, 'handleUuid');
		preg_match_all('/getAttributes\((\w+)::class\)/', $handleUuid, $matches);
		$consumers = $matches[1];

		$beforeController = self::middlewareMethodSource($source, 'beforeController');
		preg_match_all('/getAttributes\((\w+)::class\)\)\)\s*\{\s*\$this->(\w+)\(/', $beforeController, $guards, PREG_SET_ORDER);
		foreach ($guards as $guard) {
			if (str_contains(self::middlewareMethodSource($source, $guard[2]), '$this->getUuidFromRequest(')) {
				$consumers[] = $guard[1];
			}
		}

		self::assertNotEmpty($consumers, 'No UUID middleware consumers discovered');
		return array_values(array_unique($consumers));
	}

	private static function middlewareMethodSource(string $source, string $method): string {
		$declaration = '/^[ \t]*(?:public|protected|private)[ \t]+(?:static[ \t]+)?function[ \t]+' . preg_quote($method, '/') . '\s*\(/m';
		self::assertSame(1, preg_match($declaration, $source, $found, PREG_OFFSET_CAPTURE), "Missing middleware method {$method}");
		$start = $found[0][1];
		$bodyOffset = $start + strlen($found[0][0]);
		$following = substr($source, $bodyOffset);
		$nextMethod = '/^[ \t]*(?:public|protected|private)[ \t]+(?:static[ \t]+)?function[ \t]+[A-Za-z_]\w*\s*\(/m';
		if (preg_match($nextMethod, $following, $next, PREG_OFFSET_CAPTURE)) {
			return substr($source, $start, $bodyOffset - $start + $next[0][1]);
		}
		return substr($source, $start);
	}

	/**
	 * Read controller attributes, not RequestHeader declarations or generated OpenAPI.
	 * Every ApiRoute must be parsed so a new syntax cannot silently skip consumers.
	 *
	 * @param list<string> $consumers
	 * @return array<string, 'default'|'administration'>
	 */
	private static function consumerRoutesInSource(string $source, string $file, array $consumers): array {
		$matches = [];
		preg_match_all('/((?:^[ \t]*#\[[^\r\n]*\]\r?\n)+)[ \t]*public function [A-Za-z_]\w*/m', $source, $matches, PREG_SET_ORDER);
		$foundRoutes = 0;
		$routes = [];

		foreach ($matches as $match) {
			$attributes = $match[1];
			if (!str_contains($attributes, '#[ApiRoute(')) {
				continue;
			}
			$foundRoutes++;

			if (str_contains($attributes, 'SCOPE_IGNORE')) {
				continue;
			}
			$consumesUuid = false;
			foreach ($consumers as $attribute) {
				if (preg_match('/#\[' . preg_quote($attribute, '/') . '(?:\]|\()/', $attributes)) {
					$consumesUuid = true;
					break;
				}
			}
			if (!$consumesUuid) {
				continue;
			}

			self::assertSame(1, preg_match("/#\\[ApiRoute\\(verb:\\s*'([A-Z]+)',\\s*url:\\s*'([^']+)'/", $attributes, $route), "Cannot read ApiRoute in {$file}");
			// Routes already carrying {uuid} do not need a separate header.
			if (str_contains($route[2], '{uuid}')) {
				continue;
			}
			$key = $route[1] . ' ' . self::OCS_PREFIX . $route[2];
			self::assertArrayNotHasKey($key, $routes, "Duplicate route in {$file}: {$key}");
			$routes[$key] = str_contains($attributes, 'SCOPE_ADMINISTRATION') ? 'administration' : 'default';
		}

		self::assertSame(substr_count($source, '#[ApiRoute('), $foundRoutes, "Unparsed ApiRoute in {$file}; update the source discovery");
		return $routes;
	}

	public function testNewUuidConsumerIsDiscoveredWithoutUpdatingAnOperationList(): void {
		$source = <<<'PHP'
			<?php
			#[RequireSigner]
			#[OpenAPI(tags: ['signing'])]
			#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/future/{fileId}')]
			public function aNewEndpoint(): void {}
			#[RequireSigner]
			#[OpenAPI(tags: ['signing'])]
			#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/future/uuid/{uuid}')]
			public function routeAlreadyHasUuid(): void {}
			#[OpenAPI(tags: ['signing'])]
			#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/unrelated')]
			public function unrelatedEndpoint(): void {}
			PHP;
		$middleware = file_get_contents(dirname(__DIR__, 4) . '/lib/Middleware/InjectionMiddleware.php');
		self::assertNotFalse($middleware);
		self::assertSame(
			['POST /ocs/v2.php/apps/libresign/api/{apiVersion}/future/{fileId}' => 'default'],
			self::consumerRoutesInSource($source, 'synthetic controller', self::uuidConsumerAttributesInMiddleware($middleware)),
		);
	}

	public function testNewMiddlewareConsumerIsDiscoveredWithoutUpdatingAnAttributeList(): void {
		$middleware = <<<'PHP'
			<?php
			class ExampleMiddleware {
				public function beforeController(): void {
					if (!empty($reflectionMethod->getAttributes(FutureUuidGuard::class))) {
						$this->checkFutureUuid();
					}
					if (!empty($reflectionMethod->getAttributes(UnrelatedGuard::class))) {
						$this->checkOther();
					}
				}
				private function handleUuid(): void {
					if (!empty($reflectionMethod->getAttributes(OtherUuidGuard::class))) {
						$this->processUuid();
					}
				}
				private function checkFutureUuid(): void {
					$this->getUuidFromRequest();
				}
				private function checkOther(): void {}
			}
			PHP;
		self::assertSame(
			['OtherUuidGuard', 'FutureUuidGuard'],
			self::uuidConsumerAttributesInMiddleware($middleware),
		);
	}
}
