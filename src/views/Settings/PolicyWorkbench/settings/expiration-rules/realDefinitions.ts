/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ExpiryInDaysRuleEditor from './ExpiryInDaysRuleEditor.vue'
import RequestExpirationRuleEditor from './RequestExpirationRuleEditor.vue'
import RenewalIntervalRuleEditor from './RenewalIntervalRuleEditor.vue'
import {
	DEFAULT_EXPIRY_IN_DAYS,
	DEFAULT_MAXIMUM_VALIDITY,
	DEFAULT_RENEWAL_INTERVAL,
	hasValidRequestExpirationCombination,
	normalizeNonNegativeInt,
	normalizePositiveInt,
	normalizeRequestExpirationDraftValue,
	summarizeRequestExpirationDraftValue,
} from './model'

export const maximumValidityRealDefinition: RealPolicySettingDefinition = {
	key: 'maximum_validity',
	// TRANSLATORS Policy title for signature request expiration configuration.
	title: t('libresign', 'Signing request expiration'),
	// TRANSLATORS Policy description explaining how long signing requests remain available.
	description: t('libresign', 'Define how long a signing request can remain available.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && (policy?.canSaveAsUserDefault === true || policy?.meta?.canCreateDescendantRules === true),
	},
	editor: RequestExpirationRuleEditor,
	supportedScopes: ['system', 'group', 'user'],
	createEmptyValue: () => normalizeRequestExpirationDraftValue(DEFAULT_MAXIMUM_VALIDITY),
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeRequestExpirationDraftValue(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => hasValidRequestExpirationCombination(value),
	normalizeAllowChildOverride: (scope, allowChildOverride: boolean, context) => {
		if (context?.viewMode === 'group-admin' && scope === 'group') {
			return false
		}

		return allowChildOverride
	},
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeRequestExpirationDraftValue(policyValue)
		}

		return normalizeRequestExpirationDraftValue(DEFAULT_MAXIMUM_VALIDITY)
	},
	summarizeValue: (value: EffectivePolicyValue) => summarizeRequestExpirationDraftValue(value, t),
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and account levels may define their own expiration rules.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must use this expiration value.
			: t('libresign', 'Groups and accounts must follow this value'),
}

export const renewalIntervalRealDefinition: RealPolicySettingDefinition = {
	key: 'renewal_interval',
	// TRANSLATORS Policy title for signer access/session renewal.
	title: t('libresign', 'Signer access renewal'),
	// TRANSLATORS Policy description. Interval is in seconds and renews signer access/session only within the signing request maximum validity.
	description: t('libresign', 'Define the interval in seconds for renewing signer access/session while the signing request is within its configured maximum validity.'),
	editor: RenewalIntervalRuleEditor,
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
			// TRANSLATORS Summary meaning automatic renewal interval enforcement is disabled.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Summary value. {value} is renewal interval in seconds.
		return t('libresign', '{value} seconds', { value: String(normalized) })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and account levels may set their own renewal interval.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must keep this renewal interval.
			: t('libresign', 'Groups and accounts must follow this value'),
}

export const expiryInDaysRealDefinition: RealPolicySettingDefinition = {
	key: 'expiry_in_days',
	// TRANSLATORS Policy title for certificate validity duration measured in days.
	title: t('libresign', 'Signer certificate validity'),
	// TRANSLATORS Policy description for the default validity of signer certificates created by LibreSign, distinct from signing request expiration.
	description: t('libresign', 'Define the default validity, in days, for signer certificates created by LibreSign. This setting does not control how long a signing request remains available.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && (policy?.canSaveAsUserDefault === true || policy?.meta?.canCreateDescendantRules === true),
	},
	editor: ExpiryInDaysRuleEditor,
	supportedScopes: ['system', 'group', 'user'],
	createEmptyValue: () => DEFAULT_EXPIRY_IN_DAYS,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizePositiveInt(value, DEFAULT_EXPIRY_IN_DAYS),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (scope, allowChildOverride: boolean, context) => {
		if (context?.viewMode === 'group-admin' && scope === 'group') {
			return false
		}

		return allowChildOverride
	},
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_EXPIRY_IN_DAYS
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizePositiveInt(value, DEFAULT_EXPIRY_IN_DAYS)
		// TRANSLATORS Summary value. {value} is number of days before generated certificate expiration.
		return t('libresign', '{value} days', { value: String(normalized) })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating child scopes may define their own certificate validity duration.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must use this certificate validity duration.
			: t('libresign', 'Groups and accounts must follow this value'),
}
