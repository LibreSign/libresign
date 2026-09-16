<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ObserverProfile;

use OCA\Libresign\Db\File as FileEntity;

final class ObserverProfilePolicyValue {
	public static function normalize(mixed $value): bool {
		return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
	}

	public static function getSnapshotEffectiveValue(FileEntity $file): ?bool {
		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[ObserverProfilePolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return self::normalize($entry['effectiveValue']);
	}
}
