<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\OrganizationSeals\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\OrganizationSeal\OrganizationSealStore;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicy;
use OCA\Libresign\Service\Policy\Provider\OrganizationSeals\OrganizationSealsPolicyValue;
use OCP\AppFramework\Http;
use OCP\IUser;
use OCP\Server;

class OrganizationSealsFilePolicyApplier extends AbstractFilePolicyApplier {
	private ?OrganizationSealStore $sealStore = null;

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getOverrides($data);
		$this->assertSealsExist($requestOverrides);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(OrganizationSealsPolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(OrganizationSealsPolicy::KEY, $user, $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$requestOverrides = $this->getOverrides($data);
		if ($this->isPolicySnapshotFrozen($file, OrganizationSealsPolicy::KEY)) {
			// A client may send the stored value along an unrelated update, which changes nothing.
			if ($requestOverrides !== [] && $requestOverrides[OrganizationSealsPolicy::KEY] !== $this->getStoredValue($file)) {
				throw new LibresignException(
					// TRANSLATORS Error shown when someone tries to change the organization seals of a signature request after the signing flow already started.
					$this->l10n?->t('The organization seals cannot be changed after the signing flow has started.')
						?? 'The organization seals cannot be changed after the signing flow has started.',
					Http::STATUS_UNPROCESSABLE_ENTITY,
				);
			}
			return;
		}

		$this->assertSealsExist($requestOverrides);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(OrganizationSealsPolicy::KEY, $file->getUserId(), $requestOverrides)
			: $this->policyService->resolveForUserId(OrganizationSealsPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);

		if (($file->getMetadata() ?? []) !== $metadataBeforeUpdate) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	public function setSealStore(OrganizationSealStore $sealStore): void {
		$this->sealStore = $sealStore;
	}

	/** @return array<string, string> */
	private function getOverrides(array $data): array {
		return $this->extractSinglePolicyOverride(
			$data,
			OrganizationSealsPolicy::KEY,
			static fn (mixed $value): string => OrganizationSealsPolicyValue::encode($value),
		);
	}

	/** @param array<string, string> $requestOverrides */
	private function assertSealsExist(array $requestOverrides): void {
		if ($requestOverrides === []) {
			return;
		}

		$sealStore = $this->sealStore ??= Server::get(OrganizationSealStore::class);
		foreach (OrganizationSealsPolicyValue::decode($requestOverrides[OrganizationSealsPolicy::KEY]) as $id) {
			if (!$sealStore->exists($id)) {
				throw new LibresignException(
					// TRANSLATORS Error shown when a signature request asks for an organization seal that the administrator has not configured.
					$this->l10n?->t('Unknown organization seal.') ?? 'Unknown organization seal.',
					Http::STATUS_UNPROCESSABLE_ENTITY,
				);
			}
		}
	}

	private function getStoredValue(FileEntity $file): string {
		$metadata = $file->getMetadata() ?? [];
		return OrganizationSealsPolicyValue::encode($metadata['policy_snapshot'][OrganizationSealsPolicy::KEY]['effectiveValue'] ?? []);
	}

	/** @param array<string, string> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, 'Organization seals override is blocked by %s.');
	}
}
