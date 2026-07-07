<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue;
use OCP\IUser;

class IdentificationDocumentsFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(IdentificationDocumentsPolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(IdentificationDocumentsPolicy::KEY, $user, $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$this->storeIdentificationDocumentsPolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(IdentificationDocumentsPolicy::KEY, $file->getUserId(), $requestOverrides)
			: $this->policyService->resolveForUserId(IdentificationDocumentsPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeIdentificationDocumentsPolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($metadataChanged) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	/** @return array<string, array{enabled: bool, approvers: list<string>}> */
	private function getOverrides(array $data): array {
		return $this->extractSinglePolicyOverride(
			$data,
			IdentificationDocumentsPolicy::KEY,
			static fn (mixed $value): array => IdentificationDocumentsPolicyValue::normalize($value, false),
		);
	}

	/** @param array<string, mixed> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, 'Identification documents flow override is blocked by %s.');
	}

	private function storeIdentificationDocumentsPolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy): void {
		parent::storePolicySnapshot(
			$file,
			$resolvedPolicy,
			IdentificationDocumentsPolicyValue::normalize($resolvedPolicy->getEffectiveValue(), false),
		);
	}
}
