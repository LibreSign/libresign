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
			$snapshotValue = ObserverProfilePolicyValue::getSnapshotEffectiveValue($file);
			if ($snapshotValue === true) {
				return true;
			}
			if ($snapshotValue === false) {
				return $this->isLivePolicyEnabled();
			}

			return false;
		}

		return $this->isLivePolicyEnabled();
	}

	private function isLivePolicyEnabled(): bool {
		return ObserverProfilePolicyValue::normalize(
			$this->policyService->resolve(ObserverProfilePolicy::KEY)->getEffectiveValue(),
		);
	}
}
