/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, type ComputedRef } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { usePoliciesStore } from '../../../../../store/policies'

// Defaults matching backend SignatureTextPolicyValue::DEFAULTS
const SIGNATURE_TEXT_DEFAULTS = {
	template: '',
	templateFontSize: 9.0,
	signatureFontSize: 9.0,
	signatureWidth: 90.0,
	signatureHeight: 60.0,
	renderMode: 'default',
}

interface SignatureTextValues {
	template: string
	templateFontSize: number
	signatureFontSize: number
	signatureWidth: number
	signatureHeight: number
	renderMode: string
	templateError: string
	parsed: string
}

export function useSignatureTextPolicy(): { values: ComputedRef<SignatureTextValues> } {
	const policiesStore = usePoliciesStore()

	const values = computed<SignatureTextValues>(() => {
		const signatureTextPolicy = policiesStore.policies.signature_text

		// Use policy value when present; otherwise fallback to existing initial state values.
		let policyValue = SIGNATURE_TEXT_DEFAULTS

		if (signatureTextPolicy?.effectiveValue) {
			const decoded = typeof signatureTextPolicy.effectiveValue === 'string'
				? JSON.parse(signatureTextPolicy.effectiveValue)
				: signatureTextPolicy.effectiveValue

			policyValue = {
				template: String(decoded.template ?? SIGNATURE_TEXT_DEFAULTS.template),
				templateFontSize: Number(decoded.template_font_size ?? SIGNATURE_TEXT_DEFAULTS.templateFontSize),
				signatureFontSize: Number(decoded.signature_font_size ?? SIGNATURE_TEXT_DEFAULTS.signatureFontSize),
				signatureWidth: Number(decoded.signature_width ?? SIGNATURE_TEXT_DEFAULTS.signatureWidth),
				signatureHeight: Number(decoded.signature_height ?? SIGNATURE_TEXT_DEFAULTS.signatureHeight),
				renderMode: String(decoded.render_mode ?? SIGNATURE_TEXT_DEFAULTS.renderMode),
			}
		} else {
			policyValue = {
				template: loadState<string>('libresign', 'signature_text_template', SIGNATURE_TEXT_DEFAULTS.template),
				templateFontSize: Number(loadState<number>('libresign', 'template_font_size', SIGNATURE_TEXT_DEFAULTS.templateFontSize)),
				signatureFontSize: Number(loadState<number>('libresign', 'signature_font_size', SIGNATURE_TEXT_DEFAULTS.signatureFontSize)),
				signatureWidth: Number(loadState<number>('libresign', 'signature_width', SIGNATURE_TEXT_DEFAULTS.signatureWidth)),
				signatureHeight: Number(loadState<number>('libresign', 'signature_height', SIGNATURE_TEXT_DEFAULTS.signatureHeight)),
				renderMode: String(loadState<string>('libresign', 'signature_render_mode', 'GRAPHIC_AND_DESCRIPTION')),
			}
		}

		// Only non-policy values come from loadState (error/parsing results)
		return {
			...policyValue,
			templateError: loadState<string>('libresign', 'signature_text_template_error', ''),
			parsed: loadState<string>('libresign', 'signature_text_parsed', ''),
		}
	})

	return { values }
}
