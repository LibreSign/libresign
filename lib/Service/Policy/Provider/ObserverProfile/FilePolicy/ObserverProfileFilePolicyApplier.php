<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ObserverProfile\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\ParticipantRole;
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
		if (!$this->requestContainsObserver($data)) {
			return;
		}

		if ($this->getStoredSnapshotValue($file) === true) {
			return;
		}

		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$resolvedPolicy = $this->policyService->resolveForUser(ObserverProfilePolicy::KEY, $user, []);
		if (!ObserverProfilePolicyValue::normalize($resolvedPolicy->getEffectiveValue())) {
			return;
		}

		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeObserverProfilePolicySnapshot($file, $resolvedPolicy);
		if (($file->getMetadata() ?? []) !== $metadataBeforeUpdate) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
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

	/** @param array<string, mixed> $data */
	private function requestContainsObserver(array $data): bool {
		$signers = $data['signers'] ?? null;
		if (!is_array($signers)) {
			return false;
		}

		foreach ($signers as $signer) {
			if (!is_array($signer)) {
				continue;
			}

			$roleValue = $signer['participantRole'] ?? ParticipantRole::SIGNER->value;
			if ($roleValue === ParticipantRole::OBSERVER->value) {
				return true;
			}
		}

		return false;
	}

	private function getStoredSnapshotValue(FileEntity $file): ?bool {
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
