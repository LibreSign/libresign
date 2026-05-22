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
	// TRANSLATORS Policy title controlling PDF certification permissions after signing.
	title: t('libresign', 'PDF certification'),
	// TRANSLATORS Technical context label. DocMDP is a PDF mechanism that defines permitted post-signature modifications.
	context: t('libresign', 'DocMDP'),
	// TRANSLATORS Policy description explaining that this rule defines what edits remain allowed after the first signature.
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
			// TRANSLATORS Policy summary meaning PDF certification restrictions are not applied.
			return t('libresign', 'Disabled')
		case 1:
			// TRANSLATORS Policy summary for strict DocMDP level where any modification is forbidden.
			return t('libresign', 'No changes allowed')
		case 2:
			// TRANSLATORS Policy summary for DocMDP level allowing only form field filling.
			return t('libresign', 'Form filling')
		case 3:
			// TRANSLATORS Policy summary for DocMDP level allowing form filling and annotations/comments.
			return t('libresign', 'Form filling and annotations')
		default:
			// TRANSLATORS Fallback summary when no valid DocMDP value is configured.
			return t('libresign', 'Not configured')
		}
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and user scopes can define their own DocMDP rule.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must follow this DocMDP value.
			: t('libresign', 'Groups and accounts must follow this value'),
}
