/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyState } from '../../types/index'
import { realDefinitions } from '../Settings/PolicyWorkbench/settings/realDefinitions'

const getWorkbenchDefinition = (policyKey: string) => {
	return realDefinitions[policyKey as keyof typeof realDefinitions] ?? null
}

const policySupportsPersonalPreference = (
	policy: { meta?: { supportsUserPreference?: boolean } } | null | undefined,
): boolean => {
	return policy?.meta?.supportsUserPreference !== false
}

const policySupportsDescendantRuleCreation = (
	policy: { meta?: { canCreateDescendantRules?: boolean } } | null | undefined,
): boolean => {
	return policy?.meta?.canCreateDescendantRules === true
}

const defaultCanRenderWorkbenchPolicyForGroupAdmin = (
	policy: Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault' | 'meta'> | null | undefined,
): boolean => {
	if (!policy) {
		return false
	}

	return policy.editableByCurrentActor === true
		|| policy.canSaveAsUserDefault === true
		|| policySupportsDescendantRuleCreation(policy)
}

export const isWorkbenchPolicyKey = (policyKey: string): boolean => {
	return getWorkbenchDefinition(policyKey) !== null
}

export const canRenderWorkbenchPolicyForGroupAdmin = (
	policyKey: string,
	policy: Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault' | 'meta'> | null | undefined,
): boolean => {
	const definition = getWorkbenchDefinition(policyKey)
	if (!definition) {
		return false
	}

	if (!policy) {
		return false
	}

	const explicitGroupAdminRenderDecision = definition
		?.groupAdminBehavior
		?.canRenderPolicy
		?.(policy)

	if (typeof explicitGroupAdminRenderDecision === 'boolean') {
		return explicitGroupAdminRenderDecision
	}

	return defaultCanRenderWorkbenchPolicyForGroupAdmin(policy)
}

export const canRenderPersonalPreferencePolicy = (
	policyKey: string,
	policy: EffectivePolicyState | null | undefined,
): boolean => {
	if (!getWorkbenchDefinition(policyKey)) {
		return false
	}

	if (!policy) {
		return false
	}

	if (!policySupportsPersonalPreference(policy)) {
		return false
	}

	return policy.canSaveAsUserDefault === true || policy.sourceScope === 'user'
}
