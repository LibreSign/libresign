/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import { canRenderPersonalPreferencePolicy } from '../../../../Preferences/personalPreferenceVisibility'
import type {
	RealPolicyPersonalPreferenceBehavior,
	RealPolicyPersonalPreferenceContext,
} from '../realTypes'
import {
	normalizeSignatureStampDraftValue,
	resolveCollectMetadataValue,
} from './model'

export const SIGNATURE_STAMP_POLICY_KEY = 'signature_stamp'
export const COLLECT_METADATA_POLICY_KEY = 'collect_metadata'

const getSignatureStampPolicy = (context: RealPolicyPersonalPreferenceContext): EffectivePolicyState | null => {
	return context.getPolicy(SIGNATURE_STAMP_POLICY_KEY)
}

const getCollectMetadataPolicy = (context: RealPolicyPersonalPreferenceContext): EffectivePolicyState | null => {
	return context.getPolicy(COLLECT_METADATA_POLICY_KEY)
}

const buildSignatureStampPreferenceValue = (context: RealPolicyPersonalPreferenceContext): EffectivePolicyValue => {
	return normalizeSignatureStampDraftValue(
		getSignatureStampPolicy(context)?.effectiveValue ?? null,
		resolveCollectMetadataValue(getCollectMetadataPolicy(context)?.effectiveValue, false),
	)
}

const canSaveMergedSignatureStampPreference = (context: RealPolicyPersonalPreferenceContext): boolean => {
	const signatureStampCanSave = getSignatureStampPolicy(context)?.canSaveAsUserDefault ?? false
	const collectMetadataPolicy = getCollectMetadataPolicy(context)
	const collectMetadataCanSave = collectMetadataPolicy
		? collectMetadataPolicy.canSaveAsUserDefault
		: true

	return signatureStampCanSave && collectMetadataCanSave
}

const hasSavedMergedSignatureStampPreference = (context: RealPolicyPersonalPreferenceContext): boolean => {
	return getSignatureStampPolicy(context)?.sourceScope === 'user'
		|| getCollectMetadataPolicy(context)?.sourceScope === 'user'
}

const normalizeSignatureStampPreferenceValue = (
	value: EffectivePolicyValue,
	context: RealPolicyPersonalPreferenceContext,
): ReturnType<typeof normalizeSignatureStampDraftValue> => {
	return normalizeSignatureStampDraftValue(
		value,
		resolveCollectMetadataValue(getCollectMetadataPolicy(context)?.effectiveValue, false),
	)
}

const buildMergedSignatureStampPolicy = (context: RealPolicyPersonalPreferenceContext): EffectivePolicyState | null => {
	const signatureStampPolicy = getSignatureStampPolicy(context)
	const collectMetadataPolicy = getCollectMetadataPolicy(context)

	if (!signatureStampPolicy && !collectMetadataPolicy) {
		return null
	}

	if (!signatureStampPolicy) {
		return collectMetadataPolicy
	}

	if (!collectMetadataPolicy) {
		return signatureStampPolicy
	}

	return {
		...signatureStampPolicy,
		canSaveAsUserDefault: canSaveMergedSignatureStampPreference(context),
		sourceScope: hasSavedMergedSignatureStampPreference(context)
			? 'user'
			: signatureStampPolicy.sourceScope,
		preferenceWasCleared: signatureStampPolicy.preferenceWasCleared || collectMetadataPolicy.preferenceWasCleared,
	}
}

const shouldRenderMergedSignatureStampPreference = (context: RealPolicyPersonalPreferenceContext): boolean => {
	return canRenderPersonalPreferencePolicy(
		SIGNATURE_STAMP_POLICY_KEY,
		buildMergedSignatureStampPolicy(context),
	)
}

export const signatureStampPersonalPreferenceBehavior: RealPolicyPersonalPreferenceBehavior = {
	shouldRender: (_policy, context) => shouldRenderMergedSignatureStampPreference(context),
	resolvePolicy: (_policy, context) => buildMergedSignatureStampPolicy(context),
	resolveSelectedValue: (_policy, context) => buildSignatureStampPreferenceValue(context),
	normalizeValue: (value, context) => normalizeSignatureStampPreferenceValue(value, context),
	getEffectiveValue: (_policy, context) => buildSignatureStampPreferenceValue(context),
	canSave: (_policy, context) => canSaveMergedSignatureStampPreference(context),
	hasSavedPreference: (_policy, context) => hasSavedMergedSignatureStampPreference(context),
	savePreference: async (value, context) => {
		const normalizedValue = normalizeSignatureStampPreferenceValue(value, context)
		await Promise.all([
			context.saveUserPreference(SIGNATURE_STAMP_POLICY_KEY, normalizedValue.signatureStampValue),
			context.saveUserPreference(COLLECT_METADATA_POLICY_KEY, normalizedValue.collectMetadataEnabled),
		])
	},
	clearPreference: async (context) => {
		await Promise.all([
			context.clearUserPreference(SIGNATURE_STAMP_POLICY_KEY),
			context.clearUserPreference(COLLECT_METADATA_POLICY_KEY),
		])
	},
}

export const collectMetadataPersonalPreferenceBehavior: RealPolicyPersonalPreferenceBehavior = {
	shouldRender: (policy, context) => {
		return !shouldRenderMergedSignatureStampPreference(context)
			&& canRenderPersonalPreferencePolicy(
				COLLECT_METADATA_POLICY_KEY,
				policy,
			)
	},
}
