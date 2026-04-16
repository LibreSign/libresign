/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import type { EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import SignatureFooterRuleEditor from './SignatureFooterRuleEditor.vue'
import {
	getDefaultSignatureFooterPolicyConfig,
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
} from './model'

export const signatureFooterRealDefinition: RealPolicySettingDefinition = {
	key: 'add_footer',
	title: t('libresign', 'Signature footer'),
	description: t('libresign', 'Manage footer visibility, QR code behavior, validation URL, and footer template customization.'),
	editor: SignatureFooterRuleEditor,
	editorProps: {
		inheritedTemplate: loadState<string>('libresign', 'footer_template', ''),
		allowValidationSiteOverrideInUserScope: false,
		preferenceAutoSave: true,
	},
	resolveEditorProps: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => {
		const policyWithInherited = policy as (EffectivePolicyState & { inheritedValue?: EffectivePolicyValue }) | null
		if (policyWithInherited && Object.prototype.hasOwnProperty.call(policyWithInherited, 'inheritedValue')) {
			const normalizedInherited = normalizeSignatureFooterPolicyConfig(policyWithInherited.inheritedValue ?? null)
			// Only override the base inheritedTemplate when the inherited policy level provides a non-empty
			// custom footer template. Otherwise fall back to the base (which carries the system-level
			// footer_template from loadState) so the user always sees the real default instead of blank.
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
			return t('libresign', 'Disabled')
		}

		const summary: string[] = [t('libresign', 'Enabled')]
		summary.push(normalized.writeQrcodeOnFooter ? t('libresign', 'QR code on') : t('libresign', 'QR code off'))
		if (normalized.validationSite) {
			summary.push(t('libresign', 'Custom URL'))
		}
		if (normalized.customizeFooterTemplate) {
			summary.push(t('libresign', 'Custom template'))
		}

		return summary.join(' • ')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
