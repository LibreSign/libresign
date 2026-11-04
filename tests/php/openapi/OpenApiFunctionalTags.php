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

	public const EXPECTED_UNION_COUNTS = [
		'appearance' => 17,
		'certificates' => 12,
		'documents' => 6,
		'identity' => 14,
		'notifications' => 4,
		'policies' => 17,
		'requests' => 5,
		'signing' => 8,
		'system' => 3,
		'verification' => 4,
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

	public const KNOWN_FULL_SPEC_OMISSIONS = [
		'footer_template-get-footer-template',
	];

	public const EXPECTED_OPERATION_TAGS = [
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
		'notify-status' => 'notifications',
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
		'signature_elements-use-saved-signature-elements' => 'appearance',
		'signature_stamp_preview-preview-pdf' => 'appearance',
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

	/**
	 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
	 * @return list<string>
	 */
	public static function collectErrors(string $label, array $operations): array {
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
	 * @param list<array{operationId: string, method: string, path: string, tags: list<string>}> $operations
	 * @return array<string, int>
	 */
	public static function countTags(array $operations): array {
		$counts = array_fill_keys(self::APPROVED_TAGS, 0);
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
	public static function indexById(array $operations): array {
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
	public static function collectGoldenMapErrors(array $union): array {
		$errors = [];
		$expected = self::EXPECTED_OPERATION_TAGS;
		ksort($expected);

		$mapCounts = array_fill_keys(self::APPROVED_TAGS, 0);
		foreach ($expected as $tag) {
			if (!isset($mapCounts[$tag])) {
				$mapCounts[$tag] = 0;
			}
			$mapCounts[$tag]++;
		}
		ksort($mapCounts);
		$expectedCounts = self::EXPECTED_UNION_COUNTS;
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

	/**
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
