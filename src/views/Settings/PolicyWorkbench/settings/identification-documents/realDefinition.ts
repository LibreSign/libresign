/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import IdentificationDocumentsRuleEditor from './IdentificationDocumentsRuleEditor.vue'

function resolveIdentificationDocuments(value: EffectivePolicyValue): boolean | null {
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		if (value === 1) {
			return true
		}

		if (value === 0) {
			return false
		}

		return null
	}

	if (typeof value === 'string') {
		const normalized = value.trim().toLowerCase()
		if (['1', 'true'].includes(normalized)) {
			return true
		}

		if (['0', 'false', ''].includes(normalized)) {
			return false
		}
	}

	return null
}

export const identificationDocumentsRealDefinition: RealPolicySettingDefinition = {
	key: 'identification_documents',
	title: t('libresign', 'Identification documents flow'),
	description: t('libresign', 'Control whether signers must submit identification documents for approval.'),
	supportedScopes: ['system', 'group'],
	editor: IdentificationDocumentsRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => false,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveIdentificationDocuments(value)
		return resolved ?? false
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveIdentificationDocuments(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return false
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveIdentificationDocuments(value)
		if (resolved === true) {
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups can set their own rule')
			: t('libresign', 'Groups must follow this value'),
}