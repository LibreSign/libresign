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
	// TRANSLATORS Policy title for custom legal information shown on the document validation page.
	title: t('libresign', 'Legal information'),
	// TRANSLATORS Policy description explaining that the configured legal information appears on the validation page.
	description: t('libresign', 'This information will appear on the validation page'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: LegalInformationRuleEditor,
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
			// TRANSLATORS Fallback policy summary shown when no legal information text is configured.
			return t('libresign', 'Not configured')
		}

		const flattened = content.replace(/\s+/g, ' ')
		return flattened.length <= 60 ? flattened : `${flattened.slice(0, 57)}...`
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own legal-information text.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the legal-information text defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
