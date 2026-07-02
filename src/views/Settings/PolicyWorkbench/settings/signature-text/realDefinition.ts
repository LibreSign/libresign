/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureTextRuleEditor from './SignatureTextRuleEditor.vue'

import type { EffectivePolicyMeta, EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureStampDraftValue,
	normalizeSignatureTextPolicyConfig,
	serializeSignatureTextPolicyConfig,
} from './model'
import { signatureStampPersonalPreferenceBehavior } from './personalPreferenceBehavior'

type SignatureTextPolicyState = EffectivePolicyState & {
	inheritedValue?: EffectivePolicyValue
	meta?: EffectivePolicyMeta
}

/**
 * Resolves the canonical signature stamp baseline exposed through policy metadata.
 *
 * @param policyState Effective policy state for the signature stamp setting.
 */
function resolveSignatureStampSystemDefault(policyState?: SignatureTextPolicyState | null): EffectivePolicyValue | null {
	if (policyState?.meta?.defaultSystemValue !== null && policyState?.meta?.defaultSystemValue !== undefined) {
		return policyState.meta.defaultSystemValue
	}

	return null
}

// TRANSLATORS Policy setting title for signature stamp text configuration.
const signatureStampTextTitle = t('libresign', 'Signature stamp text')
// TRANSLATORS Policy setting description for signature stamp template and rendering options.
const signatureStampTextDescription = t('libresign', 'Configure signature stamp template, dimensions, render mode, and background.')
// TRANSLATORS Summary option for render mode combining signature image and descriptive text.
const summaryRenderModeDefault = t('libresign', 'Signature + description')
// TRANSLATORS Summary option for render mode using signer name and description only.
const summaryRenderModeText = t('libresign', 'Signer name + description')
// TRANSLATORS Summary option for render mode using signature image only.
const summaryRenderModeGraphic = t('libresign', 'Signature only')
// TRANSLATORS Summary option for render mode using description text only.
const summaryRenderModeDescriptionOnly = t('libresign', 'Description only')
// TRANSLATORS Summary option indicating system default background for signature stamp.
const summaryBackgroundDefault = t('libresign', 'Default background')
// TRANSLATORS Summary option indicating custom background image is configured.
const summaryBackgroundCustom = t('libresign', 'Custom background')
// TRANSLATORS Summary option indicating no background is used.
const summaryBackgroundDeleted = t('libresign', 'No background')
// TRANSLATORS Inheritance summary when lower scopes may define their own rules.
const allowChildOverrideSummary = t('libresign', 'Groups and accounts can set their own rule')
// TRANSLATORS Inheritance summary when lower scopes must follow parent rule.
const denyChildOverrideSummary = t('libresign', 'Groups and accounts must follow this value')

export const signatureTextRealDefinition: RealPolicySettingDefinition = {
	key: 'signature_stamp',
	title: signatureStampTextTitle,
	description: signatureStampTextDescription,
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	personalPreferenceBehavior: signatureStampPersonalPreferenceBehavior,
	editor: SignatureTextRuleEditor,
	editorProps: {},
	resolveEditorProps: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => {
		const policyWithInherited = policy as SignatureTextPolicyState | null
		if (!policyWithInherited || !Object.prototype.hasOwnProperty.call(policyWithInherited, 'inheritedValue')) {
			return baseEditorProps
		}

		if (policyWithInherited.sourceScope === 'global' || policyWithInherited.sourceScope === 'system') {
			return baseEditorProps
		}

		return {
			...baseEditorProps,
			inheritedValue: serializeSignatureTextPolicyConfig(normalizeSignatureTextPolicyConfig(policyWithInherited.inheritedValue ?? null)),
		}
	},
	editorDialogLayout: 'wide',
	createEmptyValue: () => serializeSignatureTextPolicyConfig(getDefaultSignatureTextPolicyConfig()),
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeSignatureStampDraftValue(value)
		return {
			signatureStampValue: serializeSignatureTextPolicyConfig(normalizeSignatureTextPolicyConfig(normalized.signatureStampValue)),
			collectMetadataEnabled: normalized.collectMetadataEnabled,
		}
	},
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null, policyState?: EffectivePolicyState | null) => {
		const metaDefault = resolveSignatureStampSystemDefault(policyState as SignatureTextPolicyState | null | undefined)
		if (metaDefault !== null) {
			return metaDefault
		}

		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeSignatureTextPolicyConfig(getDefaultSignatureTextPolicyConfig())
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalizedDraftValue = normalizeSignatureStampDraftValue(value)
		const normalized = normalizeSignatureTextPolicyConfig(normalizedDraftValue.signatureStampValue)
		const modeLabel = {
			default: summaryRenderModeDefault,
			text: summaryRenderModeText,
			graphic: summaryRenderModeGraphic,
			description_only: summaryRenderModeDescriptionOnly,
		}[normalized.renderMode] ?? summaryRenderModeDefault

		const backgroundLabel = {
			default: summaryBackgroundDefault,
			custom: summaryBackgroundCustom,
			deleted: summaryBackgroundDeleted,
		}[normalized.backgroundType] ?? summaryBackgroundDefault

		return `${modeLabel} • ${backgroundLabel}`
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? allowChildOverrideSummary
			: denyChildOverrideSummary,
}
