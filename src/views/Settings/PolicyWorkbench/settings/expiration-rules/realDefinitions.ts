/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ExpiryInDaysRuleEditor from './ExpiryInDaysRuleEditor.vue'
import MaximumValidityRuleEditor from './MaximumValidityRuleEditor.vue'
import RenewalIntervalRuleEditor from './RenewalIntervalRuleEditor.vue'
import {
	DEFAULT_EXPIRY_IN_DAYS,
	DEFAULT_MAXIMUM_VALIDITY,
	DEFAULT_RENEWAL_INTERVAL,
	normalizeNonNegativeInt,
	normalizePositiveInt,
} from './model'

export const maximumValidityRealDefinition: RealPolicySettingDefinition = {
	key: 'maximum_validity',
	title: t('libresign', 'Maximum validity'),
	description: t('libresign', 'Maximum validity in seconds of a request to sign.'),
	editor: MaximumValidityRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => DEFAULT_MAXIMUM_VALIDITY,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeNonNegativeInt(value, DEFAULT_MAXIMUM_VALIDITY),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_MAXIMUM_VALIDITY
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeNonNegativeInt(value, DEFAULT_MAXIMUM_VALIDITY)
		if (normalized <= 0) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', '{value} seconds', { value: String(normalized) })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}

export const renewalIntervalRealDefinition: RealPolicySettingDefinition = {
	key: 'renewal_interval',
	title: t('libresign', 'Renewal interval'),
	description: t('libresign', 'Renewal interval in seconds of a subscription request. When accessing the link, you will be asked to renew the link.'),
	editor: RenewalIntervalRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => DEFAULT_RENEWAL_INTERVAL,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeNonNegativeInt(value, DEFAULT_RENEWAL_INTERVAL),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_RENEWAL_INTERVAL
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeNonNegativeInt(value, DEFAULT_RENEWAL_INTERVAL)
		if (normalized <= 0) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', '{value} seconds', { value: String(normalized) })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}

export const expiryInDaysRealDefinition: RealPolicySettingDefinition = {
	key: 'expiry_in_days',
	title: t('libresign', 'Expiration in days'),
	description: t('libresign', 'The length of time for which the generated certificate will be valid, in days.'),
	editor: ExpiryInDaysRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => DEFAULT_EXPIRY_IN_DAYS,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizePositiveInt(value, DEFAULT_EXPIRY_IN_DAYS),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_EXPIRY_IN_DAYS
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizePositiveInt(value, DEFAULT_EXPIRY_IN_DAYS)
		return t('libresign', '{value} days', { value: String(normalized) })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
