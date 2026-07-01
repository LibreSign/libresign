<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\DocMdp\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCP\IUser;

class DocMdpFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(DocMdpPolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(DocMdpPolicy::KEY, $user, $requestOverrides, $activeContext);
		$file->setDocmdpLevelEnum(DocMdpLevel::tryFrom((int)$resolvedPolicy->getEffectiveValue()) ?? DocMdpLevel::NOT_CERTIFIED);
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(DocMdpPolicy::KEY, $file->getUserId(), $requestOverrides)
			: $this->policyService->resolveForUserId(DocMdpPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
		$newLevel = DocMdpLevel::tryFrom((int)$resolvedPolicy->getEffectiveValue()) ?? DocMdpLevel::NOT_CERTIFIED;
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($file->getDocmdpLevelEnum() !== $newLevel || $metadataChanged) {
			$file->setDocmdpLevelEnum($newLevel);
			$this->fileService->update($file);
		}
	}

	#[\Override]
	public function supportsCoreFlowSync(): bool {
		return true;
	}

	/** @return array<string, int> */
	private function getOverrides(array $data): array {
		return $this->extractSinglePolicyOverride($data, DocMdpPolicy::KEY);
	}
}
