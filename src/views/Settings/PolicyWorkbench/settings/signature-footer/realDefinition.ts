/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import type { EffectivePoliciesResponse, EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import SignatureFooterRuleEditor from './SignatureFooterRuleEditor.vue'
import {
	getDefaultSignatureFooterPolicyConfig,
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
} from './model'

const effectivePolicies = loadState<EffectivePoliciesResponse>('libresign', 'effective_policies', { policies: {} })
const inheritedFooterPolicyConfig = normalizeSignatureFooterPolicyConfig(effectivePolicies.policies?.add_footer?.effectiveValue ?? null)

// TRANSLATORS Policy setting title for signature footer behavior.
const signatureFooterTitle = t('libresign', 'Signature footer')
// TRANSLATORS Policy setting description covering footer visibility, QR code, URL, and template options.
const signatureFooterDescription = t('libresign', 'Manage footer visibility, QR code behavior, validation URL, and footer template customization.')
// TRANSLATORS Summary label when signature footer feature is disabled.
const summaryDisabledLabel = t('libresign', 'Disabled')
// TRANSLATORS Summary label when signature footer feature is enabled.
const summaryEnabledLabel = t('libresign', 'Enabled')
// TRANSLATORS Summary label indicating QR code in footer is enabled.
const summaryQrCodeOnLabel = t('libresign', 'QR code on')
// TRANSLATORS Summary label indicating QR code in footer is disabled.
const summaryQrCodeOffLabel = t('libresign', 'QR code off')
// TRANSLATORS Summary label indicating custom validation URL is configured.
const summaryCustomUrlLabel = t('libresign', 'Custom URL')
// TRANSLATORS Summary label indicating custom footer template is configured.
const summaryCustomTemplateLabel = t('libresign', 'Custom template')
// TRANSLATORS Inheritance summary when lower scopes may define their own footer rules.
const allowChildOverrideSummary = t('libresign', 'Groups and accounts can set their own rule')
// TRANSLATORS Inheritance summary when lower scopes must follow parent footer rule.
const denyChildOverrideSummary = t('libresign', 'Groups and accounts must follow this value')

export const signatureFooterRealDefinition: RealPolicySettingDefinition = {
	key: 'add_footer',
	title: signatureFooterTitle,
	description: signatureFooterDescription,
	editor: SignatureFooterRuleEditor,
	editorProps: {
		inheritedTemplate: inheritedFooterPolicyConfig.footerTemplate,
		allowValidationSiteOverrideInUserScope: false,
		preferenceAutoSave: true,
	},
	resolveEditorProps: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => {
		const policyWithInherited = policy as (EffectivePolicyState & { inheritedValue?: EffectivePolicyValue }) | null
		if (policyWithInherited && Object.prototype.hasOwnProperty.call(policyWithInherited, 'inheritedValue')) {
			if (policyWithInherited.sourceScope === 'global' || policyWithInherited.sourceScope === 'system') {
				return baseEditorProps
			}

			const normalizedInherited = normalizeSignatureFooterPolicyConfig(policyWithInherited.inheritedValue ?? null)
			const resolvedTemplate = normalizedInherited.footerTemplate.trim() !== ''
				? normalizedInherited.footerTemplate
				: (baseEditorProps.inheritedTemplate as string | undefined) ?? ''
			return {
				...baseEditorProps,
				inheritedTemplate: resolvedTemplate,
			}
		}

		return baseEditorProps
	},
	editorDialogLayout: 'wide',
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeSignatureFooterPolicyConfig(getDefaultSignatureFooterPolicyConfig()),
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		return serializeSignatureFooterPolicyConfig(normalizeSignatureFooterPolicyConfig(value))
	},
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeSignatureFooterPolicyConfig(getDefaultSignatureFooterPolicyConfig())
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeSignatureFooterPolicyConfig(value)
		if (!normalized.enabled) {
			return summaryDisabledLabel
		}

		const summary: string[] = [summaryEnabledLabel]
		summary.push(normalized.writeQrcodeOnFooter ? summaryQrCodeOnLabel : summaryQrCodeOffLabel)
		if (normalized.validationSite) {
			summary.push(summaryCustomUrlLabel)
		}
		if (normalized.customizeFooterTemplate) {
			summary.push(summaryCustomTemplateLabel)
		}

		return summary.join(' • ')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? allowChildOverrideSummary
			: denyChildOverrideSummary,
}
