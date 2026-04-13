<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Footer\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCP\IL10N;
use OCP\IUser;

class FooterFilePolicyApplier {
	public function __construct(
		private readonly PolicyService $policyService,
		private readonly FileService $fileService,
		private readonly IL10N $l10n,
	) {
	}

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

	/**
	 * @param array{policyActiveContext?: array<string,mixed>} $data
	 * @return array{type: string, id: string}|null
	 */
	private function extractActiveContext(array $data): ?array {
		if (!isset($data['policyActiveContext']) || !is_array($data['policyActiveContext'])) {
			return null;
		}

		$type = $data['policyActiveContext']['type'] ?? null;
		$id = $data['policyActiveContext']['id'] ?? null;
		if (!is_string($type) || !is_string($id) || $type === '' || $id === '') {
			return null;
		}

		return [
			'type' => $type,
			'id' => $id,
		];
	}

	/** @return array<string, string> */
	private function getOverrides(array $data): array {
		if (isset($data['policyOverrides']) && is_array($data['policyOverrides']) && array_key_exists(FooterPolicy::KEY, $data['policyOverrides'])) {
			return [FooterPolicy::KEY => $data['policyOverrides'][FooterPolicy::KEY]];
		}

		return [];
	}

	/** @param array<string, string> $requestOverrides */
	private function assertOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		if ($requestOverrides === [] || $resolvedPolicy->canUseAsRequestOverride()) {
			return;
		}

		$blockedBy = $resolvedPolicy->getBlockedBy() ?? $resolvedPolicy->getSourceScope();
		throw new LibresignException($this->l10n->t('Footer template override is blocked by %s.', [$blockedBy]), 422);
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
