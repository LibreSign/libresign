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

/**
 * Golden operationId → functional tag map for every documented operation.
 *
 * Sourced from the union of openapi.json and openapi-administration.json on
 * this branch after the #9088 taxonomy was applied. Aggregate counts cannot
 * catch a swap between two operations, so each operationId is asserted here.
 *
 * @var array<string, string>
 */
const EXPECTED_OPERATION_TAGS = [
	'account-create-to-sign' => 'identity',
	'account-delete-pfx' => 'identity',
	'account-me' => 'identity',
	'account-read-pfx-data' => 'identity',
	'account-set-config' => 'identity',
	'account-signature-generate' => 'identity',
	'account-update-pfx-password' => 'identity',
	'account-update-settings' => 'identity',
	'account-upload-pfx' => 'identity',
	'admin-configure-check' => 'system',
	'admin-delete-certificate-policy' => 'certificates',
	'admin-generate-certificate-cfssl' => 'certificates',
	'admin-generate-certificate-open-ssl' => 'certificates',
	'admin-get-active-signings' => 'requests',
	'admin-get-geo-ip-config' => 'system',
	'admin-load-certificate' => 'certificates',
	'admin-save-certificate-policy' => 'certificates',
	'admin-save-geo-ip-config' => 'system',
	'admin-set-certificate-engine' => 'certificates',
	'admin-signature-background-delete' => 'appearance',
	'admin-signature-background-get' => 'appearance',
	'admin-signature-background-save' => 'appearance',
	'admin-update-oid' => 'certificates',
	'certificate_policy-get-certificate-policy' => 'certificates',
	'crl-check-certificate-status' => 'verification',
	'crl-get-revocation-list' => 'certificates',
	'crl_api-list' => 'certificates',
	'crl_api-revoke' => 'certificates',
	'file-add-file-to-envelope' => 'documents',
	'file-delete-file' => 'documents',
	'file-get-thumbnail' => 'documents',
	'file-get-thumbnail-by-file-id' => 'documents',
	'file-list' => 'documents',
	'file-save' => 'documents',
	'file-validate-binary' => 'verification',
	'file-validate-file-id' => 'verification',
	'file-validate-uuid' => 'verification',
	'file_element-create-visible-element' => 'appearance',
	'file_element-delete-visible-element' => 'appearance',
	'file_element-update-visible-element' => 'appearance',
	'file_progress-check-progress-by-uuid' => 'signing',
	'footer_template-get-footer-template' => 'appearance',
	'footer_template-preview-pdf' => 'appearance',
	'footer_template-save-footer-template' => 'appearance',
	'id_docs-add-files' => 'identity',
	'id_docs-delete' => 'identity',
	'id_docs-list-to-approval' => 'identity',
	'id_docs-list-unauthenticated-signer-documents' => 'identity',
	'identify-search' => 'identity',
	'notify-notification-dismiss' => 'notifications',
	'notify-signer' => 'notifications',
	'notify-signers' => 'notifications',
	'policy-clear-group' => 'policies',
	'policy-clear-user-policy-for-user' => 'policies',
	'policy-clear-user-preference' => 'policies',
	'policy-effective' => 'policies',
	'policy-get-group' => 'policies',
	'policy-get-system' => 'policies',
	'policy-get-user-policy-for-user' => 'policies',
	'policy-list-group-policies' => 'policies',
	'policy-list-user-policies' => 'policies',
	'policy-set-group' => 'policies',
	'policy-set-group-compound' => 'policies',
	'policy-set-system' => 'policies',
	'policy-set-system-compound' => 'policies',
	'policy-set-user-policy-for-user' => 'policies',
	'policy-set-user-policy-for-user-compound' => 'policies',
	'policy-set-user-preference' => 'policies',
	'policy-set-user-preference-compound' => 'policies',
	'request_signature-delete-signature-request' => 'requests',
	'request_signature-remove-signer' => 'requests',
	'request_signature-request-signature' => 'requests',
	'request_signature-update-signature-request' => 'requests',
	'setting-has-root-cert' => 'certificates',
	'sign_file-reject-by-file-id' => 'signing',
	'sign_file-reject-by-signer-uuid' => 'signing',
	'sign_file-request-code-by-file-id' => 'signing',
	'sign_file-request-code-by-signer-uuid' => 'signing',
	'sign_file-sign-by-file-id' => 'signing',
	'sign_file-sign-by-signer-uuid' => 'signing',
	'sign_file-sign-renew' => 'signing',
	'signature_elements-create-signature-element' => 'appearance',
	'signature_elements-delete-signature-element' => 'appearance',
	'signature_elements-get-signature-element' => 'appearance',
	'signature_elements-get-signature-elements' => 'appearance',
	'signature_elements-patch-signature-element' => 'appearance',
	'signature_elements-preview-signature-element' => 'appearance',
	'signature_stamp_preview-preview-pdf' => 'appearance',
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

/**
 * @param array<string, array{operationId: string, method: string, path: string, tags: list<string>}> $union
 * @return list<string>
 */
function collectGoldenMapErrors(array $union): array {
	$errors = [];
	$expected = EXPECTED_OPERATION_TAGS;
	ksort($expected);

	$mapCounts = array_fill_keys(APPROVED_TAGS, 0);
	foreach ($expected as $tag) {
		if (!isset($mapCounts[$tag])) {
			$mapCounts[$tag] = 0;
		}
		$mapCounts[$tag]++;
	}
	ksort($mapCounts);
	$expectedCounts = EXPECTED_UNION_COUNTS;
	ksort($expectedCounts);
	if ($mapCounts !== $expectedCounts) {
		$errors[] = 'EXPECTED_OPERATION_TAGS counts do not match EXPECTED_UNION_COUNTS: map '
			. json_encode($mapCounts) . ', counts ' . json_encode($expectedCounts);
	}

	$expectedTotal = array_sum($expectedCounts);
	if (count($expected) !== $expectedTotal) {
		$errors[] = 'EXPECTED_OPERATION_TAGS must list ' . $expectedTotal
			. ' operations, got ' . count($expected);
	}

	$missing = array_values(array_diff(array_keys($expected), array_keys($union)));
	$extra = array_values(array_diff(array_keys($union), array_keys($expected)));
	if ($missing !== []) {
		$errors[] = 'Union is missing operationIds from the golden map: ' . implode(', ', $missing);
	}
	if ($extra !== []) {
		$errors[] = 'Union has operationIds that are not in the golden map: ' . implode(', ', $extra);
	}

	foreach ($union as $id => $operation) {
		if (!isset($expected[$id])) {
			continue;
		}
		$actualTag = $operation['tags'][0] ?? '';
		if ($actualTag !== $expected[$id]) {
			$errors[] = "{$operation['method']} {$operation['path']} ({$id}) expected tag '{$expected[$id]}', got '{$actualTag}'";
		}
	}

	return $errors;
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
$errors = [...$errors, ...collectGoldenMapErrors($union)];
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
