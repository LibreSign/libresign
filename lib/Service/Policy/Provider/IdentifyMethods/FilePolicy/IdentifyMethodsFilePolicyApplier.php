<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentifyMethods\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use OCP\IUser;

class IdentifyMethodsFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$resolvedPolicy = $this->policyService->resolveForUser(IdentifyMethodsPolicy::KEY, $user, []);
		$this->storeIdentifyMethodsPolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$resolvedPolicy = $this->policyService->resolveForUserId(IdentifyMethodsPolicy::KEY, $file->getUserId(), []);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeIdentifyMethodsPolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($metadataChanged) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return false;
	}

	private function storeIdentifyMethodsPolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy): void {
		$normalized = IdentifyMethodsPolicyValue::normalize($resolvedPolicy->getEffectiveValue());
		parent::storePolicySnapshot($file, $resolvedPolicy, IdentifyMethodsPolicyValue::extractFactors($normalized));
	}
}
