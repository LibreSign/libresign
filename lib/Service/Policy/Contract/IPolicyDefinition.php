<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Contract;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;

interface IPolicyDefinition {
	public function key(): string;

	public function resolutionMode(): string;

	public function getAppConfigKey(): string;

	public function getUserPreferenceKey(): string;

	public function normalizeValue(mixed $rawValue): mixed;

	public function validateValue(mixed $value, PolicyContext $context): void;

	/** @return list<mixed> */
	public function allowedValues(PolicyContext $context): array;

	public function defaultSystemValue(): mixed;

	/** @return array<string, mixed> */
	public function resolvedStateMeta(PolicyContext $context): array;

	/**
	 * Whether this policy supports being saved as a user personal preference.
	 * Returns false for administrative-only policies (e.g. groups_request_sign)
	 * that must never appear in the user preferences screen.
	 */
	public function supportsUserPreference(): bool;

	/**
	 * Whether group admins (subAdmins) are allowed to configure this policy
	 * at the group level. Returns false for system-only policies that must
	 * not be visible or editable by group admins.
	 */
	public function supportsGroupAdminConfiguration(): bool;

	/**
	 * Whether group-level rules for this policy should be filtered from counts and listings
	 * for the current non-system actor when they were created by a system administrator.
	 */
	public function shouldFilterVisibleGroupCountsForActor(PolicyContext $context, ?PolicyLayer $systemPolicy): bool;

	/**
	 * Whether the current actor may manage group-level rules for this policy.
	 *
	 * @param list<PolicyLayer> $groupLayers Group layers already applicable to the actor context.
	 */
	public function canCurrentActorManageGroupPolicy(PolicyContext $context, ?PolicyLayer $systemPolicy, array $groupLayers): bool;

	/**
	 * Whether the current actor may edit a system-created group rule for this policy.
	 */
	public function canCurrentActorEditSystemCreatedGroupPolicy(PolicyContext $context, ?PolicyLayer $systemPolicy, PolicyLayer $existingPolicy): bool;

	/**
	 * Whether this policy supports group-admin-delegated override rules.
	 * When true, a separate `__delegated_override` slot is used so that the
	 * system-created seed and the group-admin override coexist independently.
	 */
	public function supportsGroupAdminDelegation(): bool;

	/**
	 * Validate a proposed group-admin-delegated value against the parent
	 * system-created seed value.  Implementations should throw
	 * \InvalidArgumentException with a translatable message when the proposed
	 * value violates policy constraints.
	 *
	 * This hook is called only when the actor is a non-system-admin and the
	 * definition returns true from supportsGroupAdminDelegation().
	 */
	public function validateGroupAdminDelegatedValue(
		mixed $proposedNormalizedValue,
		mixed $parentSeedNormalizedValue,
		PolicyContext $context,
	): void;
}
