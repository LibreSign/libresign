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
	title: t('libresign', 'Timestamp Authority (TSA)'),
	description: t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.'),
	supportedScopes: ['system'],
	editor: TsaRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeTsaSettings(DEFAULT_TSA_SETTINGS),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeTsaSettings(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeTsaSettings(DEFAULT_TSA_SETTINGS)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const config = normalizeTsaSettings(value)
		if (!config.url) {
			return t('libresign', 'Disabled')
		}

		if (config.auth_type === 'basic') {
			return t('libresign', 'Enabled • basic authentication')
		}

		return t('libresign', 'Enabled')
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
