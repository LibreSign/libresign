<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\FileStatusService;
use OCA\Libresign\Service\Policy\Contract\IFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;

abstract class AbstractFilePolicyApplier implements IFilePolicyApplier {
	public function __construct(
		protected readonly PolicyService $policyService,
		protected readonly FileService $fileService,
		protected readonly ?IL10N $l10n = null,
		protected readonly ?FileMapper $fileMapper = null,
	) {
	}

	/**
	 * @param array{policyActiveContext?: array<string,mixed>} $data
	 * @return array{type: string, id: string}|null
	 */
	protected function extractActiveContext(array $data): ?array {
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

	/**
	 * @param callable(mixed):mixed|null $normalizer
	 * @return array<string, mixed>
	 */
	protected function extractSinglePolicyOverride(array $data, string $policyKey, ?callable $normalizer = null): array {
		if (!isset($data['policyOverrides']) || !is_array($data['policyOverrides']) || !array_key_exists($policyKey, $data['policyOverrides'])) {
			return [];
		}

		$value = $data['policyOverrides'][$policyKey];
		if ($normalizer !== null) {
			$value = $normalizer($value);
		}

		return [$policyKey => $value];
	}

	/** @param array<string, mixed> $requestOverrides */
	protected function assertRequestOverrideAllowed(array $requestOverrides, ResolvedPolicy $resolvedPolicy, string $message): void {
		if ($requestOverrides === [] || $resolvedPolicy->canUseAsRequestOverride()) {
			return;
		}

		$blockedBy = $resolvedPolicy->getBlockedBy() ?? $resolvedPolicy->getSourceScope();
		$translatedMessage = $this->l10n instanceof IL10N
			// TRANSLATORS Error shown when a signature-request setting is blocked by a higher-level LibreSign policy. The placeholder receives the scope that blocked the override.
			? $this->l10n->t($message, [$blockedBy])
			: vsprintf($message, [$blockedBy]);

		throw new LibresignException($translatedMessage, 422);
	}

	/**
	 * Whether the request no longer accepts new values for these policies.
	 *
	 * A request freezes the first time it enters the signing flow and stays
	 * frozen if it later returns to DRAFT, and a document freezes with its
	 * envelope. A request saved before the freeze was recorded has no marker, so
	 * it is frozen while its status says it was sent.
	 */
	protected function isPolicySnapshotFrozen(FileEntity $file, string $policyKey, string ...$otherPolicyKeys): bool {
		foreach ([$policyKey, ...$otherPolicyKeys] as $policyKey) {
			if ($this->policyService->getRequestLifecycle($policyKey) !== PolicySpec::LIFECYCLE_REQUEST_SNAPSHOT) {
				throw new \LogicException(sprintf('The %s policy is not stored on the request, so it cannot be frozen.', $policyKey));
			}
		}

		$envelope = $this->findEnvelope($file);

		return FileStatusService::isPolicySnapshotFrozen($file)
			|| ($envelope !== null && FileStatusService::isPolicySnapshotFrozen($envelope));
	}

	private function findEnvelope(FileEntity $file): ?FileEntity {
		$parentId = $file->getParentFileId();
		if ($parentId === null || $this->fileMapper === null) {
			return null;
		}

		try {
			return $this->fileMapper->getById($parentId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	protected function storePolicySnapshot(FileEntity $file, ResolvedPolicy $resolvedPolicy, mixed $effectiveValue = null): void {
		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? [];
		$policySnapshot[$resolvedPolicy->getPolicyKey()] = [
			'effectiveValue' => $effectiveValue ?? $resolvedPolicy->getEffectiveValue(),
			'sourceScope' => $resolvedPolicy->getSourceScope(),
		];
		$metadata['policy_snapshot'] = $policySnapshot;
		$file->setMetadata($metadata);
	}
}
