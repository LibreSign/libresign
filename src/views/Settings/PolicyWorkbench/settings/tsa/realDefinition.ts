/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import TsaRuleEditor from './TsaRuleEditor.vue'
import { DEFAULT_TSA_SETTINGS, normalizeTsaSettings, serializeTsaSettings } from './model'

export const tsaRealDefinition: RealPolicySettingDefinition = {
	key: 'tsa_settings',
	// TRANSLATORS Policy title for configuring trusted timestamps applied to digital signatures.
	title: t('libresign', 'Timestamp Authority'),
	// TRANSLATORS Short technical context label. TSA means Time-Stamp Authority.
	context: t('libresign', 'TSA'),
	// TRANSLATORS Policy description for TSA settings used during digital signing and long-term signature validation.
	description: t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.'),
	supportedScopes: ['system', 'group', 'user'],
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && (policy?.canSaveAsUserDefault === true || policy?.meta?.canCreateDescendantRules === true),
	},
	editor: TsaRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeTsaSettings(DEFAULT_TSA_SETTINGS),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeTsaSettings(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeTsaSettings(DEFAULT_TSA_SETTINGS)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const config = normalizeTsaSettings(value)
		if (!config.url) {
			// TRANSLATORS Policy summary meaning timestamp server usage is turned off.
			return t('libresign', 'Disabled')
		}

		if (config.auth_type === 'basic') {
			// TRANSLATORS Policy summary meaning TSA is enabled and uses HTTP Basic authentication.
			return t('libresign', 'Enabled • basic authentication')
		}

		// TRANSLATORS Policy summary meaning TSA is enabled without authentication details in this summary.
		return t('libresign', 'Enabled')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating child scopes may define their own TSA configuration.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must keep this TSA configuration.
			: t('libresign', 'Groups and accounts must follow this value'),
}
