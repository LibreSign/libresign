<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ObserverProfile;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\PolicyService;

final class ObserverProfilePolicyService {
	public function __construct(
		private PolicyService $policyService,
	) {
	}

	public function isEnabled(?FileEntity $file = null): bool {
		if ($file instanceof FileEntity) {
			return $this->getSnapshotValue($file) ?? false;
		}

		return ObserverProfilePolicyValue::normalize(
			$this->policyService->resolve(ObserverProfilePolicy::KEY)->getEffectiveValue(),
		);
	}

	private function getSnapshotValue(?FileEntity $file): ?bool {
		if (!$file instanceof FileEntity) {
			return null;
		}

		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[ObserverProfilePolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return ObserverProfilePolicyValue::normalize($entry['effectiveValue']);
	}
}
