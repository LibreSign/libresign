/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyState } from '../../types/index'

// Keep in sync with the keys exported by Policy Workbench realDefinitions.
const PREFERENCE_SUPPORTED_POLICY_KEYS = new Set([
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

function defaultCanRenderWorkbenchPolicyForGroupAdmin(
	policy: Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault'> | null | undefined,
): boolean {
	if (!policy) {
		return false
	}

	return policy.editableByCurrentActor === true || policy.canSaveAsUserDefault === true
}

export function isWorkbenchPolicyKey(policyKey: string): boolean {
	return PREFERENCE_SUPPORTED_POLICY_KEYS.has(policyKey)
}

export function canRenderWorkbenchPolicyForGroupAdmin(
	policyKey: string,
	policy: Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault'> | null | undefined,
): boolean {
	if (!PREFERENCE_SUPPORTED_POLICY_KEYS.has(policyKey)) {
		return false
	}

	if (!policy) {
		return false
	}

	return defaultCanRenderWorkbenchPolicyForGroupAdmin(policy)
}

export function canRenderPersonalPreferencePolicy(
	policyKey: string,
	policy: EffectivePolicyState | null | undefined,
): boolean {
	if (!PREFERENCE_SUPPORTED_POLICY_KEYS.has(policyKey)) {
		return false
	}

	if (!policy) {
		return false
	}

	return policy.canSaveAsUserDefault === true || policy.sourceScope === 'user'
}
