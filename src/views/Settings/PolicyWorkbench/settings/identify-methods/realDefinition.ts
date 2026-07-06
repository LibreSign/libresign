/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import IdentifyMethodsRuleEditor from './IdentifyMethodsRuleEditor.vue'

import { normalizeIdentifyMethodsPolicy, serializeIdentifyMethodsPolicy } from './model'
import type { EffectivePoliciesResponse, EffectivePolicyValue, IdentifyMethodsEffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

/**
 * Runtime guard for the backend identify_methods effective policy payload.
 *
 * @param value Candidate effective policy payload.
 */
function isIdentifyMethodsEffectivePolicyValue(value: unknown): value is IdentifyMethodsEffectivePolicyValue {
	if (!value || typeof value !== 'object' || Array.isArray(value)) {
		return false
	}

	const candidate = value as Record<string, unknown>
	return Array.isArray(candidate.factors)
}

/**
 * Reads identify_methods strictly from effective policies initial state.
 */
function getEffectiveIdentifyMethodsPolicyValue(): EffectivePolicyValue {
	const effectivePolicies = loadState<EffectivePoliciesResponse>('libresign', 'effective_policies', { policies: {} })
	const effectiveValue = effectivePolicies.policies?.identify_methods?.effectiveValue
	if (isIdentifyMethodsEffectivePolicyValue(effectiveValue)) {
		return effectiveValue
	}

	return effectiveValue ?? null
}

/**
 * Uses effective policy entries as the source for create-time seed values.
 */
function getCreateSeedEntries() {
	return normalizeIdentifyMethodsPolicy(getEffectiveIdentifyMethodsPolicyValue())
}

/**
 * Builds permissive defaults for the rule editor creation flow.
 */
function getInitialIdentifyMethods(): string {
	const normalized = getCreateSeedEntries()

	const permissiveDefaults = normalized.map((entry) => ({
		...entry,
		requirement: 'optional' as const,
	}))

	return serializeIdentifyMethodsPolicy(
		permissiveDefaults,
	)
}

export const identifyMethodsRealDefinition: RealPolicySettingDefinition = {
	key: 'identify_methods',
	// TRANSLATORS Policy title for configuring which identification factors are available to signers.
	title: t('libresign', 'Identification factors'),
	// TRANSLATORS Policy description explaining that these are the methods used to identify someone before signing.
	description: t('libresign', 'Ways to identify a person who will sign a document.'),
	supportedScopes: ['system', 'group', 'user'],
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	editor: IdentifyMethodsRuleEditor,
	createEmptyValue: () => getInitialIdentifyMethods(),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeIdentifyMethodsPolicy(normalizeIdentifyMethodsPolicy(value)),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeIdentifyMethodsPolicy(value)
		return normalized.some((entry) => entry.enabled)
	},
	isBaselineSeedable: (value: EffectivePolicyValue) => normalizeIdentifyMethodsPolicy(value).length > 0,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return getInitialIdentifyMethods()
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeIdentifyMethodsPolicy(value)
		if (normalized.length === 0) {
			// TRANSLATORS Summary shown when no explicit identification-factor rule is configured and runtime defaults are used.
			return t('libresign', 'Default runtime behavior')
		}

		const enabled = normalized.filter((entry) => entry.enabled)
		if (enabled.length === 0) {
			// TRANSLATORS Summary shown when all identification factors are disabled.
			return t('libresign', 'No enabled identification factor')
		}

		if (enabled.length <= 2) {
			return enabled.map((entry) => entry.friendly_name ?? entry.name).join(', ')
		}

		// TRANSLATORS Summary showing how many identification factors are enabled. {count} is the number of enabled factors.
		return t('libresign', '{count} enabled factors', { count: String(enabled.length) })
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own identification-factor rule.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the identification-factor rule defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
