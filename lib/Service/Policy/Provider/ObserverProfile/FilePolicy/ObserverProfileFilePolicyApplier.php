<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ObserverProfile\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicyValue;
use OCP\IUser;

final class ObserverProfileFilePolicyApplier extends AbstractFilePolicyApplier {
	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->extractSinglePolicyOverride(
			$data,
			ObserverProfilePolicy::KEY,
			ObserverProfilePolicyValue::normalize(...),
		);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(ObserverProfilePolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(ObserverProfilePolicy::KEY, $user, $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$this->storeObserverProfilePolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		// The value is frozen when the request is created.
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return false;
	}

	/** @param array<string, mixed> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, 'Observer profile override is blocked by %s.');
	}

	private function storeObserverProfilePolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy): void {
		parent::storePolicySnapshot(
			$file,
			$resolvedPolicy,
			ObserverProfilePolicyValue::normalize($resolvedPolicy->getEffectiveValue()),
		);
	}
}
