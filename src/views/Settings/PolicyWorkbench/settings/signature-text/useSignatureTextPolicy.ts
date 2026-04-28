/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, type ComputedRef } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { usePoliciesStore } from '../../../../../store/policies'
import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureTextPolicyConfig,
	type SignatureTextPolicyConfig,
} from './model'

const SIGNATURE_TEXT_DEFAULTS = getDefaultSignatureTextPolicyConfig()

interface SignatureTextValues extends SignatureTextPolicyConfig {
	templateError: string
	parsed: string
}

export type SignatureTextUiDefaults = SignatureTextPolicyConfig

function readSignatureTextState(keys: {
	template: string
	templateFontSize: string
	signatureFontSize: string
	signatureWidth: string
	signatureHeight: string
	renderMode?: string
}, defaultRenderMode: string): SignatureTextPolicyConfig {
	return normalizeSignatureTextPolicyConfig({
		template: loadState<string>('libresign', keys.template, SIGNATURE_TEXT_DEFAULTS.template),
		template_font_size: loadState<number>('libresign', keys.templateFontSize, SIGNATURE_TEXT_DEFAULTS.templateFontSize),
		signature_font_size: loadState<number>('libresign', keys.signatureFontSize, SIGNATURE_TEXT_DEFAULTS.signatureFontSize),
		signature_width: loadState<number>('libresign', keys.signatureWidth, SIGNATURE_TEXT_DEFAULTS.signatureWidth),
		signature_height: loadState<number>('libresign', keys.signatureHeight, SIGNATURE_TEXT_DEFAULTS.signatureHeight),
		render_mode: keys.renderMode
			? loadState<string>('libresign', keys.renderMode, defaultRenderMode)
			: defaultRenderMode,
	})
}

export function getSignatureTextUiDefaults(): SignatureTextUiDefaults {
	return readSignatureTextState({
		template: 'default_signature_text_template',
		templateFontSize: 'default_template_font_size',
		signatureFontSize: 'default_signature_font_size',
		signatureWidth: 'default_signature_width',
		signatureHeight: 'default_signature_height',
	}, 'GRAPHIC_AND_DESCRIPTION')
}

export function useSignatureTextPolicy(): { values: ComputedRef<SignatureTextValues> } {
	const policiesStore = usePoliciesStore()

	const values = computed<SignatureTextValues>(() => {
		const signatureTextPolicy = policiesStore.policies.signature_text

		const policyValue = signatureTextPolicy?.effectiveValue
			? normalizeSignatureTextPolicyConfig(signatureTextPolicy.effectiveValue)
			: readSignatureTextState({
				template: 'signature_text_template',
				templateFontSize: 'template_font_size',
				signatureFontSize: 'signature_font_size',
				signatureWidth: 'signature_width',
				signatureHeight: 'signature_height',
				renderMode: 'signature_render_mode',
			}, 'GRAPHIC_AND_DESCRIPTION')

		// Only non-policy values come from loadState (error/parsing results)
		return {
			...policyValue,
			templateError: loadState<string>('libresign', 'signature_text_template_error', ''),
			parsed: loadState<string>('libresign', 'signature_text_parsed', ''),
		}
	})

	return { values }
}
