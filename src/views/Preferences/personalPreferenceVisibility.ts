/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyState } from '../../types/index'

// Keep in sync with the keys exported by Policy Workbench realDefinitions.
const WORKBENCH_SUPPORTED_POLICY_KEYS = new Set([
	'groups_request_sign',
	'identification_documents',
	'identify_methods',
	'signature_flow',
	'envelope_enabled',
	'add_footer',
	'signature_stamp',
	'show_confetti_after_signing',
	'collect_metadata',
	'legal_information',
	'expiry_in_days',
	'maximum_validity',
	'reminder_settings',
	'signature_hash_algorithm',
	'docmdp',
	'tsa_settings',
	'crl_external_validation_enabled',
	'default_user_folder',
	'make_validation_url_private',
	'signing_mode',
])

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
	return WORKBENCH_SUPPORTED_POLICY_KEYS.has(policyKey)
}

export const canRenderWorkbenchPolicyForGroupAdmin = (
	policyKey: string,
	policy: Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault' | 'meta'> | null | undefined,
): boolean => {
	if (!WORKBENCH_SUPPORTED_POLICY_KEYS.has(policyKey)) {
		return false
	}

	if (!policy) {
		return false
	}

	return defaultCanRenderWorkbenchPolicyForGroupAdmin(policy)
}

export const canRenderPersonalPreferencePolicy = (
	policyKey: string,
	policy: EffectivePolicyState | null | undefined,
): boolean => {
	if (!WORKBENCH_SUPPORTED_POLICY_KEYS.has(policyKey)) {
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
