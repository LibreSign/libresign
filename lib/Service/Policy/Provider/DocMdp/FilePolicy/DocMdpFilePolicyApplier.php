<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\DocMdp\FilePolicy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCA\Libresign\Service\Policy\Provider\FilePolicy\Contract\IFilePolicyApplier;
use OCP\IL10N;
use OCP\IUser;

class DocMdpFilePolicyApplier implements IFilePolicyApplier {
	public function __construct(
		private readonly PolicyService $policyService,
		private readonly FileService $fileService,
		private readonly ?IL10N $l10n = null,
	) {
	}

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

	/** @return array<string, int> */
	private function getOverrides(array $data): array {
		if (isset($data['policyOverrides']) && is_array($data['policyOverrides']) && array_key_exists(DocMdpPolicy::KEY, $data['policyOverrides'])) {
			return [DocMdpPolicy::KEY => $data['policyOverrides'][DocMdpPolicy::KEY]];
		}

		return [];
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
