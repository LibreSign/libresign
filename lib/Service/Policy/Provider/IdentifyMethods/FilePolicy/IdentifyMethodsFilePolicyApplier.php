<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentifyMethods\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Contract\IFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCP\IL10N;
use OCP\IUser;

class IdentifyMethodsFilePolicyApplier implements IFilePolicyApplier {
	public function __construct(
		private readonly PolicyService $policyService,
		private readonly FileService $fileService,
		private readonly IL10N $l10n,
	) {
	}

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$resolvedPolicy = $this->policyService->resolveForUser(IdentifyMethodsPolicy::KEY, $user, []);
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$resolvedPolicy = $this->policyService->resolveForUserId(IdentifyMethodsPolicy::KEY, $file->getUserId(), []);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($metadataChanged) {
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return false;
	}

	private function storePolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy): void {
		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? [];
		$policySnapshot[$resolvedPolicy->getPolicyKey()] = [
			'effectiveValue' => $resolvedPolicy->getEffectiveValue(),
			'sourceScope' => $resolvedPolicy->getSourceScope(),
		];
		$metadata['policy_snapshot'] = $policySnapshot;
		$file->setMetadata($metadata);
	}
}
