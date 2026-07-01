<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Contract\IFilePolicyApplier;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCP\IL10N;

abstract class AbstractFilePolicyApplier implements IFilePolicyApplier {
	public function __construct(
		protected readonly PolicyService $policyService,
		protected readonly FileService $fileService,
		protected readonly ?IL10N $l10n = null,
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
			? $this->l10n->t($message, [$blockedBy])
			: vsprintf($message, [$blockedBy]);

		throw new LibresignException($translatedMessage, 422);
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
