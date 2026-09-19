<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicyValue;
use OCP\IUser;

class SignerIpGeolocationFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(SignerIpGeolocationPolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(SignerIpGeolocationPolicy::KEY, $user, $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$this->storeSignerIpGeolocationPolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		if ($this->hasSnapshot($file)) {
			return;
		}

		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(SignerIpGeolocationPolicy::KEY, $file->getUserId(), $requestOverrides)
			: $this->policyService->resolveForUserId(SignerIpGeolocationPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeSignerIpGeolocationPolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($metadataChanged) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	/** @return array<string, array{mode: string}> */
	private function getOverrides(array $data): array {
		return $this->extractSinglePolicyOverride(
			$data,
			SignerIpGeolocationPolicy::KEY,
			static fn (mixed $value): array => SignerIpGeolocationPolicyValue::normalize($value),
		);
	}

	/** @param array<string, mixed> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, 'Signer IP geolocation flow override is blocked by %s.');
	}

	private function storeSignerIpGeolocationPolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy): void {
		parent::storePolicySnapshot(
			$file,
			$resolvedPolicy,
			SignerIpGeolocationPolicyValue::normalize($resolvedPolicy->getEffectiveValue()),
		);
	}

	private function hasSnapshot(FileEntity $file): bool {
		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return false;
		}

		$entry = $policySnapshot[SignerIpGeolocationPolicy::KEY] ?? null;
		return is_array($entry) && array_key_exists('effectiveValue', $entry);
	}
}
