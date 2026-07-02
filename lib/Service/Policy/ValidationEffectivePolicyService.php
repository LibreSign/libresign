<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;

final class ValidationEffectivePolicyService {
	public function __construct(
		private PolicyService $policyService,
	) {
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	public function appendEffectivePolicies(array $payload): array {
		$requesterUserId = $this->extractRequesterUserId($payload);
		$resolvedPolicyStates = $requesterUserId !== null
			? $this->policyService->resolveKnownPolicyStatesForUserId($requesterUserId)
			: $this->policyService->resolveKnownPolicyStates();

		$payload['effective_policies'] = [
			'policies' => $this->preferPolicySnapshotWhenAvailable($resolvedPolicyStates, $payload),
		];

		return $payload;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function extractRequesterUserId(array $payload): ?string {
		$requestedBy = $payload['requested_by'] ?? null;
		if (!is_array($requestedBy)) {
			return null;
		}

		$userId = $requestedBy['userId'] ?? null;
		if (!is_string($userId)) {
			return null;
		}

		$userId = trim($userId);

		return $userId !== '' ? $userId : null;
	}

	/**
	 * @param array<string, array<string, mixed>> $resolvedPolicyStates
	 * @param array<string, mixed> $payload
	 * @return array<string, array<string, mixed>>
	 */
	private function preferPolicySnapshotWhenAvailable(array $resolvedPolicyStates, array $payload): array {
		$metadata = $payload['metadata'] ?? null;
		if (!is_array($metadata)) {
			return $resolvedPolicyStates;
		}

		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return $resolvedPolicyStates;
		}

		$legalInformationSnapshot = $policySnapshot[LegalInformationPolicy::KEY] ?? null;
		if (!is_array($legalInformationSnapshot)) {
			return $resolvedPolicyStates;
		}

		$effectiveValue = $legalInformationSnapshot['effectiveValue'] ?? null;
		$sourceScope = $legalInformationSnapshot['sourceScope'] ?? null;
		if (!is_string($effectiveValue) || !is_string($sourceScope)) {
			return $resolvedPolicyStates;
		}

		$resolvedPolicyStates[LegalInformationPolicy::KEY] = array_merge(
			$resolvedPolicyStates[LegalInformationPolicy::KEY] ?? [
				'policyKey' => LegalInformationPolicy::KEY,
				'effectiveValue' => '',
				'inheritedValue' => null,
				'sourceScope' => 'system',
				'visible' => true,
				'editableByCurrentActor' => false,
				'allowedValues' => [],
				'canSaveAsUserDefault' => false,
				'canUseAsRequestOverride' => false,
				'preferenceWasCleared' => false,
				'blockedBy' => null,
				'groupCount' => 0,
				'userCount' => 0,
				'everyoneCount' => 0,
			],
			[
				'policyKey' => LegalInformationPolicy::KEY,
				'effectiveValue' => $effectiveValue,
				'sourceScope' => $sourceScope,
			],
		);

		return $resolvedPolicyStates;
	}
}
