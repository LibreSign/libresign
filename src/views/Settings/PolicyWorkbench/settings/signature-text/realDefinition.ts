/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import SignatureTextRuleEditor from './SignatureTextRuleEditor.vue'
import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureTextPolicyConfig,
	serializeSignatureTextPolicyConfig,
} from './model'

export const signatureTextRealDefinition: RealPolicySettingDefinition = {
	key: 'signature_text',
	title: t('libresign', 'Signature text'),
	description: t('libresign', 'Configure signature text template, font sizes, dimensions, and rendering mode.'),
	editor: SignatureTextRuleEditor,
	editorProps: {
		preferenceAutoSave: true,
	},
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeSignatureTextPolicyConfig(getDefaultSignatureTextPolicyConfig()),
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		return serializeSignatureTextPolicyConfig(normalizeSignatureTextPolicyConfig(value))
	},
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeSignatureTextPolicyConfig(getDefaultSignatureTextPolicyConfig())
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeSignatureTextPolicyConfig(value)
		const parts: string[] = []

		if (normalized.template.trim()) {
			parts.push(t('libresign', 'Custom template'))
		}

		parts.push(`${normalized.signatureWidth}×${normalized.signatureHeight}px`)
		parts.push(`${t('libresign', 'Mode')}: ${normalized.renderMode}`)

		return parts.join(' • ')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
