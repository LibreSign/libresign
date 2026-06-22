/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureFooterRuleEditor from './SignatureFooterRuleEditor.vue'

import type { EffectivePolicyMeta, EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import {
	getDefaultSignatureFooterPolicyConfig,
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
} from './model'

type FooterPolicyState = EffectivePolicyState & {
	inheritedValue?: EffectivePolicyValue
	meta?: EffectivePolicyMeta
}

/**
 * Resolves the canonical footer template baseline exposed through policy metadata.
 *
 * @param policy Effective policy state for the footer setting.
 * @param fallbackTemplate Template used when metadata is unavailable.
 */
function resolveSystemDefaultFooterTemplate(policy: FooterPolicyState | null, fallbackTemplate = ''): string {
	const normalizedDefault = normalizeSignatureFooterPolicyConfig(policy?.meta?.defaultSystemValue ?? null)
	if (normalizedDefault.footerTemplate.trim() !== '') {
		return normalizedDefault.footerTemplate
	}

	return fallbackTemplate
}

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
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	editor: SignatureFooterRuleEditor,
	editorProps: {
		inheritedTemplate: '',
		allowValidationSiteOverrideInUserScope: false,
		preferenceAutoSave: true,
	},
	resolveEditorProps: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => {
		const policyWithInherited = policy as FooterPolicyState | null
		const systemDefaultTemplate = resolveSystemDefaultFooterTemplate(
			policyWithInherited,
			(baseEditorProps.inheritedTemplate as string | undefined) ?? '',
		)

		if (policyWithInherited && Object.prototype.hasOwnProperty.call(policyWithInherited, 'inheritedValue')) {
			if (policyWithInherited.sourceScope === 'global' || policyWithInherited.sourceScope === 'system') {
				return {
					...baseEditorProps,
					inheritedTemplate: systemDefaultTemplate,
				}
			}

			const normalizedInherited = normalizeSignatureFooterPolicyConfig(policyWithInherited.inheritedValue ?? null)
			const resolvedTemplate = normalizedInherited.footerTemplate.trim() !== ''
				? normalizedInherited.footerTemplate
				: systemDefaultTemplate
			return {
				...baseEditorProps,
				inheritedTemplate: resolvedTemplate,
			}
		}

		return {
			...baseEditorProps,
			inheritedTemplate: systemDefaultTemplate,
		}
	},
	editorDialogLayout: 'wide',
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeSignatureFooterPolicyConfig(getDefaultSignatureFooterPolicyConfig()),
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		return serializeSignatureFooterPolicyConfig(normalizeSignatureFooterPolicyConfig(value))
	},
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (_policyValue: EffectivePolicyValue | null | undefined, _sourceScope?: string | null, policyState?: EffectivePolicyState | null) => {
		const footerPolicy = policyState as FooterPolicyState | null | undefined
		if (footerPolicy?.meta?.defaultSystemValue !== null && footerPolicy?.meta?.defaultSystemValue !== undefined) {
			return footerPolicy.meta.defaultSystemValue
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
