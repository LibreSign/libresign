<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCP\IL10N;
use OCP\IUser;

class FilePolicyApplier {

	public function __construct(
		private readonly PolicyService $policyService,
		private readonly FileService $fileService,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * Apply all policies to a freshly built FileEntity before the first insert.
	 */
	public function applyAll(FileEntity $file, array $data): void {
		$this->applySignatureFlow($file, $data);
		$this->applyDocMdpLevel($file, $data);
		$this->applyFooterPolicy($file, $data);
	}

	/**
	 * Re-evaluate and persist signature_flow + docmdp on an existing file.
	 * Use this when updating a file located by UUID.
	 */
	public function syncCoreFlowPolicies(FileEntity $file, array $data): void {
		$this->syncSignatureFlow($file, $data);
		$this->syncDocMdpLevel($file, $data);
	}

	/**
	 * Re-evaluate and persist all three policies on an existing file.
	 * Use this when updating a file located by node ID.
	 */
	public function syncAllPolicies(FileEntity $file, array $data): void {
		$this->syncSignatureFlow($file, $data);
		$this->syncDocMdpLevel($file, $data);
		$this->syncFooterPolicy($file, $data);
	}

	private function applySignatureFlow(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getSignatureFlowOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(
				SignatureFlowPolicy::KEY,
				$user,
				$requestOverrides,
			)
			: $this->policyService->resolveForUser(
				SignatureFlowPolicy::KEY,
				$user,
				$requestOverrides,
				$activeContext,
			);
		$this->assertSignatureFlowOverrideAllowed($requestOverrides, $resolvedPolicy);
		$file->setSignatureFlowEnum(SignatureFlow::from((string)$resolvedPolicy->getEffectiveValue()));
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	private function syncSignatureFlow(FileEntity $file, array $data): void {
		$requestOverrides = $this->getSignatureFlowOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(
				SignatureFlowPolicy::KEY,
				$file->getUserId(),
				$requestOverrides,
			)
			: $this->policyService->resolveForUserId(
				SignatureFlowPolicy::KEY,
				$file->getUserId(),
				$requestOverrides,
				$activeContext,
			);
		$this->assertSignatureFlowOverrideAllowed($requestOverrides, $resolvedPolicy);
		$newFlow = SignatureFlow::from((string)$resolvedPolicy->getEffectiveValue());
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($file->getSignatureFlowEnum() !== $newFlow || $metadataChanged) {
			$file->setSignatureFlowEnum($newFlow);
			$this->fileService->update($file);
		}
	}

	private function applyDocMdpLevel(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getDocMdpOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(
				DocMdpPolicy::KEY,
				$user,
				$requestOverrides,
			)
			: $this->policyService->resolveForUser(
				DocMdpPolicy::KEY,
				$user,
				$requestOverrides,
				$activeContext,
			);
		$file->setDocmdpLevelEnum(DocMdpLevel::tryFrom((int)$resolvedPolicy->getEffectiveValue()) ?? DocMdpLevel::NOT_CERTIFIED);
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	private function syncDocMdpLevel(FileEntity $file, array $data): void {
		$requestOverrides = $this->getDocMdpOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(
				DocMdpPolicy::KEY,
				$file->getUserId(),
				$requestOverrides,
			)
			: $this->policyService->resolveForUserId(
				DocMdpPolicy::KEY,
				$file->getUserId(),
				$requestOverrides,
				$activeContext,
			);
		$newLevel = DocMdpLevel::tryFrom((int)$resolvedPolicy->getEffectiveValue()) ?? DocMdpLevel::NOT_CERTIFIED;
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($file->getDocmdpLevelEnum() !== $newLevel || $metadataChanged) {
			$file->setDocmdpLevelEnum($newLevel);
			$this->fileService->update($file);
		}
	}

	private function applyFooterPolicy(FileEntity $file, array $data): void {
		$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
		$requestOverrides = $this->getFooterOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(
				FooterPolicy::KEY,
				$user,
				$requestOverrides,
			)
			: $this->policyService->resolveForUser(
				FooterPolicy::KEY,
				$user,
				$requestOverrides,
				$activeContext,
			);
		$this->assertFooterOverrideAllowed($requestOverrides, $resolvedPolicy);
		$this->storePolicySnapshot($file, $resolvedPolicy);
	}

	private function syncFooterPolicy(FileEntity $file, array $data): void {
		$requestOverrides = $this->getFooterOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUserId(
				FooterPolicy::KEY,
				$file->getUserId(),
				$requestOverrides,
			)
			: $this->policyService->resolveForUserId(
				FooterPolicy::KEY,
				$file->getUserId(),
				$requestOverrides,
				$activeContext,
			);
		$this->assertFooterOverrideAllowed($requestOverrides, $resolvedPolicy);
		$metadataBeforeUpdate = $file->getMetadata() ?? [];
		$this->storePolicySnapshot($file, $resolvedPolicy);
		$metadataChanged = ($file->getMetadata() ?? []) !== $metadataBeforeUpdate;

		if ($metadataChanged) {
			$this->fileService->update($file);
		}
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
	private function getSignatureFlowOverrides(array $data): array {
		if (isset($data['policyOverrides']) && is_array($data['policyOverrides']) && array_key_exists(SignatureFlowPolicy::KEY, $data['policyOverrides'])) {
			return [SignatureFlowPolicy::KEY => $data['policyOverrides'][SignatureFlowPolicy::KEY]];
		}

		return [];
	}

	/** @param array<string, string> $requestOverrides */
	private function assertSignatureFlowOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		if ($requestOverrides === [] || $resolvedPolicy->canUseAsRequestOverride()) {
			return;
		}

		$blockedBy = $resolvedPolicy->getBlockedBy() ?? $resolvedPolicy->getSourceScope();
		throw new LibresignException($this->l10n->t('Signature flow override is blocked by %s.', [$blockedBy]), 422);
	}

	/** @return array<string, int> */
	private function getDocMdpOverrides(array $data): array {
		if (isset($data['policyOverrides']) && is_array($data['policyOverrides']) && array_key_exists(DocMdpPolicy::KEY, $data['policyOverrides'])) {
			return [DocMdpPolicy::KEY => $data['policyOverrides'][DocMdpPolicy::KEY]];
		}

		return [];
	}

	/** @return array<string, string> */
	private function getFooterOverrides(array $data): array {
		if (isset($data['policyOverrides']) && is_array($data['policyOverrides']) && array_key_exists(FooterPolicy::KEY, $data['policyOverrides'])) {
			return [FooterPolicy::KEY => $data['policyOverrides'][FooterPolicy::KEY]];
		}

		return [];
	}

	/** @param array<string, string> $requestOverrides */
	private function assertFooterOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy): void {
		if ($requestOverrides === [] || $resolvedPolicy->canUseAsRequestOverride()) {
			return;
		}

		$blockedBy = $resolvedPolicy->getBlockedBy() ?? $resolvedPolicy->getSourceScope();
		throw new LibresignException($this->l10n->t('Footer template override is blocked by %s.', [$blockedBy]), 422);
	}
}
