<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\DocMdp;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use OCP\IUser;

/**
 * Resolve certification without depending on FileService or a file policy applier.
 */
class LevelResolver {
	public function __construct(
		private PolicyService $policyService,
	) {
	}

	/**
	 * The level of a frozen request comes from its snapshot. A legacy frozen
	 * request without a recorded level returns null for configuration fallback.
	 */
	public function resolveLevel(?FileEntity $file, array $data, ?FileMapper $fileMapper = null): ?DocMdpLevel {
		if ($file !== null && FileStatusService::isPolicySnapshotFrozen($file, $fileMapper?->findEnvelope($file))) {
			$policySnapshot = ($file->getMetadata() ?? [])['policy_snapshot'] ?? null;
			return is_array($policySnapshot) && array_key_exists(DocMdpPolicy::KEY, $policySnapshot)
				? $file->getDocmdpLevelEnum()
				: null;
		}

		$overrides = $this->getOverrides($data);
		$activeContext = $this->extractActiveContext($data);
		if ($file === null) {
			$user = ($data['userManager'] ?? null) instanceof IUser ? $data['userManager'] : null;
			$resolved = $activeContext === null
				? $this->policyService->resolveForUser(DocMdpPolicy::KEY, $user, $overrides)
				: $this->policyService->resolveForUser(DocMdpPolicy::KEY, $user, $overrides, $activeContext);
		} else {
			$resolved = $activeContext === null
				? $this->policyService->resolveForUserId(DocMdpPolicy::KEY, $file->getUserId(), $overrides)
				: $this->policyService->resolveForUserId(DocMdpPolicy::KEY, $file->getUserId(), $overrides, $activeContext);
		}

		return DocMdpLevel::tryFrom((int)$resolved->getEffectiveValue()) ?? DocMdpLevel::NOT_CERTIFIED;
	}

	/** @return array<string, mixed> */
	private function getOverrides(array $data): array {
		if (!isset($data['policyOverrides']) || !is_array($data['policyOverrides'])
			|| !array_key_exists(DocMdpPolicy::KEY, $data['policyOverrides'])) {
			return [];
		}
		return [DocMdpPolicy::KEY => $data['policyOverrides'][DocMdpPolicy::KEY]];
	}

	/** @return array{type: string, id: string}|null */
	private function extractActiveContext(array $data): ?array {
		$context = $data['policyActiveContext'] ?? null;
		if (!is_array($context)) {
			return null;
		}
		$type = $context['type'] ?? null;
		$id = $context['id'] ?? null;
		if (!is_string($type) || !is_string($id) || $type === '' || $id === '') {
			return null;
		}
		return ['type' => $type, 'id' => $id];
	}
}
