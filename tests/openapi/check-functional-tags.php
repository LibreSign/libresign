<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Static check for the approved OpenAPI functional taxonomy.
 *
 * This script reads the committed generated specifications only. It does not
 * boot Nextcloud or touch a database.
 *
 * Usage: php tests/openapi/check-functional-tags.php
 */

const APPROVED_TAGS = [
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

/**
 * Target counts from the approved taxonomy (issue #9088).
 * Verified against the union of openapi.json and openapi-administration.json
 * because the extractor currently drops GET when GET+POST share a path that
 * is split across default and administration scopes.
 *
 * @var array<string, int>
 */
const EXPECTED_UNION_COUNTS = [
	'appearance' => 16,
	'certificates' => 12,
	'documents' => 6,
	'identity' => 14,
	'notifications' => 3,
	'policies' => 17,
	'requests' => 5,
	'signing' => 8,
	'system' => 3,
	'verification' => 4,
];

const LEGACY_TAGS = [
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
 * current extractor merge of the same path across scopes. Do not expand this
 * list to hide new drift.
 */
const KNOWN_FULL_SPEC_OMISSIONS = [
	'footer_template-get-footer-template',
];

const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

/**
 * @return list<array{operationId: string, method: string, path: string, tags: list<string>}>
 */
function loadOperations(string $path): array {
	if (!is_file($path)) {
		fwrite(STDERR, "Missing specification: {$path}\n");
		exit(1);
	}

	$decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($decoded) || !isset($decoded['paths']) || !is_array($decoded['paths'])) {
		fwrite(STDERR, "Specification has no paths object: {$path}\n");
		exit(1);
	}

	$operations = [];
	foreach ($decoded['paths'] as $route => $methods) {
		if (!is_array($methods)) {
			continue;
		}
		foreach ($methods as $method => $operation) {
			if (!in_array(strtolower((string)$method), HTTP_METHODS, true) || !is_array($operation)) {
				continue;
			}
			if (!isset($operation['operationId']) || !is_string($operation['operationId'])) {
				fwrite(STDERR, "Unclassified operation without operationId: {$method} {$route} in {$path}\n");
				exit(1);
			}
			$tags = $operation['tags'] ?? [];
			if (!is_array($tags)) {
				fwrite(STDERR, "Invalid tags for {$operation['operationId']} in {$path}\n");
				exit(1);
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

/**
 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
 * @return list<string>
 */
function collectErrors(string $label, array $operations): array {
	$errors = [];
	$seenIds = [];

	foreach ($operations as $operation) {
		$id = $operation['operationId'];
		$location = "{$label} {$operation['method']} {$operation['path']} ({$id})";

		if (isset($seenIds[$id])) {
			$errors[] = "Duplicate operationId {$id} in {$label}";
		}
		$seenIds[$id] = true;

		if (count($operation['tags']) !== 1) {
			$errors[] = "{$location} must have exactly one functional tag, got: "
				. json_encode($operation['tags']);
			continue;
		}

		$tag = $operation['tags'][0];
		if (in_array($tag, LEGACY_TAGS, true)) {
			$errors[] = "{$location} still uses legacy tag '{$tag}'";
			continue;
		}
		if (!in_array($tag, APPROVED_TAGS, true)) {
			$errors[] = "{$location} uses unclassified tag '{$tag}'";
		}
	}

	return $errors;
}

/**
 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
 * @return array<string, int>
 */
function countTags(array $operations): array {
	$counts = array_fill_keys(APPROVED_TAGS, 0);
	foreach ($operations as $operation) {
		if (count($operation['tags']) !== 1) {
			continue;
		}
		$tag = $operation['tags'][0];
		if (!isset($counts[$tag])) {
			$counts[$tag] = 0;
		}
		$counts[$tag]++;
	}
	ksort($counts);
	return $counts;
}

/**
 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
 * @return array<string, array{operationId: string, method: string, path: string, tags: list<string>}>
 */
function indexById(array $operations): array {
	$indexed = [];
	foreach ($operations as $operation) {
		$indexed[$operation['operationId']] = $operation;
	}
	return $indexed;
}

$root = dirname(__DIR__, 2);
$full = loadOperations($root . '/openapi-full.json');
$default = loadOperations($root . '/openapi.json');
$administration = loadOperations($root . '/openapi-administration.json');

$errors = [
	...collectErrors('openapi-full.json', $full),
	...collectErrors('openapi.json', $default),
	...collectErrors('openapi-administration.json', $administration),
];

$defaultIds = indexById($default);
$adminIds = indexById($administration);
$overlap = array_intersect_key($defaultIds, $adminIds);
if ($overlap !== []) {
	$errors[] = 'openapi.json and openapi-administration.json share operationIds: '
		. implode(', ', array_keys($overlap));
}

$union = $defaultIds + $adminIds;
ksort($union);
$unionOperations = array_values($union);
$unionCounts = countTags($unionOperations);
ksort($unionCounts);
$expected = EXPECTED_UNION_COUNTS;
ksort($expected);

if ($unionCounts !== $expected) {
	$errors[] = 'Union tag counts do not match the approved taxonomy: expected '
		. json_encode($expected) . ', got ' . json_encode($unionCounts);
}

$expectedTotal = array_sum($expected);
$actualTotal = array_sum($unionCounts);
if ($actualTotal !== $expectedTotal) {
	$errors[] = "Union must contain {$expectedTotal} operations, got {$actualTotal}";
}

$fullIds = indexById($full);
$missingFromFull = array_values(array_diff(array_keys($union), array_keys($fullIds)));
$extraInFull = array_values(array_diff(array_keys($fullIds), array_keys($union)));
$unexpectedMissing = array_values(array_diff($missingFromFull, KNOWN_FULL_SPEC_OMISSIONS));
$unexpectedAllowlist = array_values(array_diff(KNOWN_FULL_SPEC_OMISSIONS, $missingFromFull));

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

if ($errors !== []) {
	fwrite(STDERR, "OpenAPI functional tag check failed:\n- " . implode("\n- ", $errors) . "\n");
	exit(1);
}

echo "OpenAPI functional tags OK\n";
echo 'Union operations: ' . count($unionOperations) . "\n";
foreach ($unionCounts as $tag => $count) {
	echo "  {$tag}: {$count}\n";
}
echo 'openapi.json: ' . count($default) . "\n";
echo 'openapi-administration.json: ' . count($administration) . "\n";
echo 'openapi-full.json: ' . count($full) . "\n";
if ($missingFromFull !== []) {
	echo 'Known openapi-full.json omissions: ' . implode(', ', $missingFromFull) . "\n";
}
