<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use RuntimeException;

/**
 * Reads committed OpenAPI specifications and reports functional-tag violations.
 *
 * This helper does not boot Nextcloud or open a database connection.
 */
final class OpenApiFunctionalTags {
	public const APPROVED_TAGS = [
		'appearance',
		'certificates',
		'documents',
		'identity',
		'notifications',
		'policies',
		'requests',
		'signing',
		'system',
		'verification',
	];

	public const LEGACY_TAGS = [
		'account',
		'admin',
		'certificate_policy',
		'crl',
		'file',
		'file_element',
		'file_progress',
		'footer_template',
		'id_docs',
		'identify',
		'notify',
		'policy',
		'setting',
		'signature_elements',
		'signature_stamp_preview',
	];

	/**
	 * Operation present in openapi.json but omitted from openapi-full.json by the
	 * current extractor merge of the same path across scopes.
	 *
	 * @var list<string>
	 */
	public const KNOWN_FULL_SPEC_OMISSIONS = [
		'footer_template-get-footer-template',
	];

	private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

	/**
	 * @return list<array{operationId: string, method: string, path: string, tags: list<string>}>
	 */
	public static function loadOperations(string $path): array {
		if (!is_file($path)) {
			throw new RuntimeException("Missing specification: {$path}");
		}

		$decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($decoded) || !isset($decoded['paths']) || !is_array($decoded['paths'])) {
			throw new RuntimeException("Specification has no paths object: {$path}");
		}

		$operations = [];
		foreach ($decoded['paths'] as $route => $methods) {
			if (!is_array($methods)) {
				continue;
			}
			foreach ($methods as $method => $operation) {
				if (!in_array(strtolower((string)$method), self::HTTP_METHODS, true) || !is_array($operation)) {
					continue;
				}
				if (!isset($operation['operationId']) || !is_string($operation['operationId'])) {
					throw new RuntimeException("Unclassified operation without operationId: {$method} {$route} in {$path}");
				}
				$tags = $operation['tags'] ?? [];
				if (!is_array($tags)) {
					throw new RuntimeException("Invalid tags for {$operation['operationId']} in {$path}");
				}
				$operations[] = [
					'operationId' => $operation['operationId'],
					'method' => strtoupper((string)$method),
					'path' => (string)$route,
					'tags' => array_values(array_map(static fn (mixed $tag): string => (string)$tag, $tags)),
				];
			}
		}

		return $operations;
	}

	public static function routeKey(array $operation): string {
		return $operation['method'] . ' ' . $operation['path'];
	}

	/**
	 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
	 * @return array{
	 *   errors: list<string>,
	 *   byId: array<string, array{operationId: string, method: string, path: string, tags: list<string>}>,
	 *   byRoute: array<string, array{operationId: string, method: string, path: string, tags: list<string>}>
	 * }
	 */
	public static function indexOperations(string $label, array $operations): array {
		$errors = [];
		$byId = [];
		$byRoute = [];

		foreach ($operations as $operation) {
			$id = $operation['operationId'];
			$route = self::routeKey($operation);

			if (isset($byId[$id])) {
				$previous = $byId[$id];
				$errors[] = "Duplicate operationId {$id} in {$label}: "
					. self::routeKey($previous) . " and {$route}";
			} else {
				$byId[$id] = $operation;
			}

			if (isset($byRoute[$route])) {
				$previous = $byRoute[$route];
				$errors[] = "Duplicate {$route} in {$label}: {$previous['operationId']} and {$id}";
			} else {
				$byRoute[$route] = $operation;
			}
		}

		return [
			'errors' => $errors,
			'byId' => $byId,
			'byRoute' => $byRoute,
		];
	}

	/**
	 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
	 * @return list<string>
	 */
	public static function collectErrors(string $label, array $operations): array {
		$indexed = self::indexOperations($label, $operations);
		$errors = $indexed['errors'];

		foreach ($operations as $operation) {
			$id = $operation['operationId'];
			$location = "{$label} " . self::routeKey($operation) . " ({$id})";

			if (count($operation['tags']) !== 1) {
				$errors[] = "{$location} must have exactly one functional tag, got: "
					. json_encode($operation['tags']);
				continue;
			}

			$tag = $operation['tags'][0];
			if (in_array($tag, self::LEGACY_TAGS, true)) {
				$errors[] = "{$location} still uses legacy tag '{$tag}'";
				continue;
			}
			if (!in_array($tag, self::APPROVED_TAGS, true)) {
				$errors[] = "{$location} uses unclassified tag '{$tag}'";
			}
		}

		return $errors;
	}

	/**
	 * @param array{
	 *   byId: array<string, array{operationId: string, method: string, path: string, tags: list<string>}>,
	 *   byRoute: array<string, array{operationId: string, method: string, path: string, tags: list<string>}>
	 * } $default
	 * @param array{
	 *   byId: array<string, array{operationId: string, method: string, path: string, tags: list<string>}>,
	 *   byRoute: array<string, array{operationId: string, method: string, path: string, tags: list<string>}>
	 * } $administration
	 * @return list<string>
	 */
	public static function collectScopeOverlapErrors(array $default, array $administration): array {
		$errors = [];
		foreach (array_intersect_key($default['byId'], $administration['byId']) as $id => $operation) {
			$errors[] = 'openapi.json and openapi-administration.json share operationId '
				. $id . ' (' . self::routeKey($operation) . ')';
		}
		foreach (array_intersect_key($default['byRoute'], $administration['byRoute']) as $route => $operation) {
			$errors[] = 'openapi.json and openapi-administration.json share '
				. $route . ' (' . $operation['operationId'] . ')';
		}
		return $errors;
	}

	/**
	 * @param list<string> $unionIds
	 * @param list<string> $fullIds
	 * @return list<string>
	 */
	public static function collectFullSpecOmissions(array $unionIds, array $fullIds): array {
		$missingFromFull = array_values(array_diff($unionIds, $fullIds));
		$extraInFull = array_values(array_diff($fullIds, $unionIds));
		$unexpectedMissing = array_values(array_diff($missingFromFull, self::KNOWN_FULL_SPEC_OMISSIONS));
		$unexpectedAllowlist = array_values(array_diff(self::KNOWN_FULL_SPEC_OMISSIONS, $missingFromFull));
		$errors = [];
		if ($extraInFull !== []) {
			$errors[] = 'openapi-full.json contains operations missing from the scoped specs: '
				. implode(', ', $extraInFull);
		}
		if ($unexpectedMissing !== []) {
			$errors[] = 'openapi-full.json is missing unexpected operations: '
				. implode(', ', $unexpectedMissing);
		}
		if ($unexpectedAllowlist !== []) {
			$errors[] = 'KNOWN_FULL_SPEC_OMISSIONS lists operations that are now present in openapi-full.json: '
				. implode(', ', $unexpectedAllowlist);
		}
		return $errors;
	}
}
