/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import DocMdpScalarRuleEditor from './DocMdpScalarRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export function resolveDocMdpLevel(value: EffectivePolicyValue): number | null {
	if (typeof value === 'number' && value >= 0 && value <= 3) {
		return value
	}

	if (typeof value === 'string' && /^[0-3]$/.test(value)) {
		return Number(value)
	}

	return null
}

export const docMdpRealDefinition: RealPolicySettingDefinition = {
	key: 'docmdp',
	title: t('libresign', 'PDF certification'),
	context: t('libresign', 'DocMDP'),
	description: t('libresign', 'Control what changes are allowed after a document is signed.'),
	editor: DocMdpScalarRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => 0,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const level = resolveDocMdpLevel(value)
		return level ?? 0
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveDocMdpLevel(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return 0
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const level = resolveDocMdpLevel(value)
		switch (level) {
		case 0:
			return t('libresign', 'Disabled')
		case 1:
			return t('libresign', 'No changes allowed')
		case 2:
			return t('libresign', 'Form filling')
		case 3:
			return t('libresign', 'Form filling and annotations')
		default:
			return t('libresign', 'Not configured')
		}
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
