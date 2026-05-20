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
	key: 'signature_stamp',
	title: t('libresign', 'Signature stamp text'),
	description: t('libresign', 'Configure signature stamp template, dimensions, render mode, and background.'),
	editor: SignatureTextRuleEditor,
	editorDialogLayout: 'wide',
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
		const modeLabel = {
			default: t('libresign', 'Signature + description'),
			text: t('libresign', 'Signer name + description'),
			graphic: t('libresign', 'Signature only'),
			description_only: t('libresign', 'Description only'),
		}[normalized.renderMode] ?? t('libresign', 'Signature + description')

		const backgroundLabel = {
			default: t('libresign', 'Default background'),
			custom: t('libresign', 'Custom background'),
			deleted: t('libresign', 'No background'),
		}[normalized.backgroundType] ?? t('libresign', 'Default background')

		return `${modeLabel} • ${backgroundLabel}`
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
