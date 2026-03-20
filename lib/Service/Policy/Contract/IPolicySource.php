<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Contract;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;

interface IPolicySource {
	public function loadSystemPolicy(string $policyKey): ?PolicyLayer;

	/** @return list<PolicyLayer> */
	public function loadGroupPolicies(string $policyKey, PolicyContext $context): array;

	/** @return list<PolicyLayer> */
	public function loadCirclePolicies(string $policyKey, PolicyContext $context): array;

	public function loadUserPreference(string $policyKey, PolicyContext $context): ?PolicyLayer;

	public function loadRequestOverride(string $policyKey, PolicyContext $context): ?PolicyLayer;

	public function loadGroupPolicyConfig(string $policyKey, string $groupId): ?PolicyLayer;

	public function saveSystemPolicy(string $policyKey, mixed $value, bool $allowChildOverride = false): void;

	public function saveGroupPolicy(string $policyKey, string $groupId, mixed $value, bool $allowChildOverride): void;

	public function clearGroupPolicy(string $policyKey, string $groupId): void;

	public function saveUserPreference(string $policyKey, PolicyContext $context, mixed $value): void;

	public function clearUserPreference(string $policyKey, PolicyContext $context): void;
}
