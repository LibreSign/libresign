<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCP\IUser;

/**
 * Freezes the rejection rules that apply to a document when the signature
 * request is created, so a later policy change cannot alter the rules a signer
 * was told about.
 */
class SignatureRejectionFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(SignatureRejectionPolicy::KEY, $user)
			: $this->policyService->resolveForUser(SignatureRejectionPolicy::KEY, $user, [], $activeContext);
		$this->storeSignatureRejectionPolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(SignatureRejectionPolicy::KEY, $file->getUserId())
			: $this->policyService->resolveForUserId(SignatureRejectionPolicy::KEY, $file->getUserId(), [], $activeContext);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storeSignatureRejectionPolicySnapshot($file, $resolvedPolicy);

		if (($file->getMetadata() ?? []) !== $metadataBeforeUpdate) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	private function storeSignatureRejectionPolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy): void {
		parent::storePolicySnapshot(
			$file,
			$resolvedPolicy,
			SignatureRejectionPolicyValue::normalize($resolvedPolicy->getEffectiveValue()),
		);
	}
}
