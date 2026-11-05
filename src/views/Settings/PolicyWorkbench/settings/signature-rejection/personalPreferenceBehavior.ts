/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { usePoliciesStore } from '../../../../../store/policies'
import type { CompoundPolicyWriteValues, EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import { canRenderPersonalPreferencePolicy } from '../../../../Preferences/personalPreferenceVisibility'
import type {
	RealPolicyPersonalPreferenceBehavior,
	RealPolicyPersonalPreferenceContext,
} from '../realTypes'
import {
	composeSignatureRejectionValue,
	decomposeSignatureRejectionValue,
	SIGNATURE_REJECTION_ENABLED_KEY,
	SIGNATURE_REJECTION_POLICY_KEYS,
} from './model'

const composeEffectiveValue = (context: RealPolicyPersonalPreferenceContext): EffectivePolicyValue => {
	return composeSignatureRejectionValue(Object.fromEntries(
		SIGNATURE_REJECTION_POLICY_KEYS.map((key) => [key, context.getPolicy(key)?.effectiveValue]),
	))
}

const canSaveEveryKey = (context: RealPolicyPersonalPreferenceContext): boolean => {
	return SIGNATURE_REJECTION_POLICY_KEYS.every((key) => context.getPolicy(key)?.canSaveAsUserDefault === true)
}

const hasSavedAnyKey = (context: RealPolicyPersonalPreferenceContext): boolean => {
	return SIGNATURE_REJECTION_POLICY_KEYS.some((key) => context.getPolicy(key)?.sourceScope === 'user')
}

const resolveMergedPolicy = (context: RealPolicyPersonalPreferenceContext): EffectivePolicyState | null => {
	const parentPolicy = context.getPolicy(SIGNATURE_REJECTION_ENABLED_KEY)
	if (!parentPolicy) {
		return null
	}

	return {
		...parentPolicy,
		canSaveAsUserDefault: canSaveEveryKey(context),
		sourceScope: hasSavedAnyKey(context) ? 'user' : parentPolicy.sourceScope,
		preferenceWasCleared: SIGNATURE_REJECTION_POLICY_KEYS.some((key) => context.getPolicy(key)?.preferenceWasCleared === true),
	}
}

/**
 * The preferences screen edits the five values as one, while the generic path
 * writes one value to one key. Saving goes through the compound endpoint so the
 * backend validates the whole configuration before any key is written.
 */
export const signatureRejectionPersonalPreferenceBehavior: RealPolicyPersonalPreferenceBehavior = {
	shouldRender: (_policy, context) => canRenderPersonalPreferencePolicy(SIGNATURE_REJECTION_ENABLED_KEY, resolveMergedPolicy(context)),
	resolvePolicy: (_policy, context) => resolveMergedPolicy(context),
	resolveSelectedValue: (_policy, context) => composeEffectiveValue(context),
	getEffectiveValue: (_policy, context) => composeEffectiveValue(context),
	canSave: (_policy, context) => canSaveEveryKey(context),
	hasSavedPreference: (_policy, context) => hasSavedAnyKey(context),
	savePreference: async (value) => {
		const policiesStore = usePoliciesStore()
		const values: CompoundPolicyWriteValues = decomposeSignatureRejectionValue(value)

		await policiesStore.saveUserPreferenceCompound(SIGNATURE_REJECTION_ENABLED_KEY, values)
		await policiesStore.fetchEffectivePolicies()
	},
	clearPreference: async (context) => {
		await Promise.all(SIGNATURE_REJECTION_POLICY_KEYS.map((key) => context.clearUserPreference(key)))
	},
}
