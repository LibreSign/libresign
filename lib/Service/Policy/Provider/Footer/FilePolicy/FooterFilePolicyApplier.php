<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Footer\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Service\Policy\AbstractFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCP\IUser;

class FooterFilePolicyApplier extends AbstractFilePolicyApplier {

	#[\Override]
	public function apply(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(FooterPolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(FooterPolicy::KEY, $user, $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	#[\Override]
	public function sync(FileEntity $file, array $data): void {
		$requestOverrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(FooterPolicy::KEY, $file->getUserId(), $requestOverrides)
			: $this->policyService->resolveForUserId(FooterPolicy::KEY, $file->getUserId(), $requestOverrides, $activeContext);
		$this->assertOverrideAllowed($requestOverrides, $resolvedPolicy);
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

	/** @return array<string, string> */
	private function getOverrides(array $data): array {
		return $this->extractSinglePolicyOverride($data, FooterPolicy::KEY);
	}

	/** @param array<string, string> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		$this->assertRequestOverrideAllowed($requestOverrides, $resolvedPolicy, 'Footer template override is blocked by %s.');
	}
}
