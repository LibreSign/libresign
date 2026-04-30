/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import LegalInformationRuleEditor from './LegalInformationRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import { normalizeLegalInformation } from './model'

export const legalInformationRealDefinition: RealPolicySettingDefinition = {
	key: 'legal_information',
	title: t('libresign', 'Legal information'),
	description: t('libresign', 'This information will appear on the validation page'),
	editor: LegalInformationRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => '',
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeLegalInformation(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return ''
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const content = normalizeLegalInformation(value).trim()
		if (content === '') {
			return t('libresign', 'Not configured')
		}

		const flattened = content.replace(/\s+/g, ' ')
		return flattened.length <= 60 ? flattened : `${flattened.slice(0, 57)}...`
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
