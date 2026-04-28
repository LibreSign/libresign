/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { usePoliciesStore } from '../store/policies'

interface SignatureTextValues {
	template: string
	templateFontSize: number
	signatureFontSize: number
	signatureWidth: number
	signatureHeight: number
	renderMode: string
	templateError: string
	parsed: string
	defaultTemplate: string
	defaultTemplateFontSize: number
	defaultSignatureFontSize: number
	defaultSignatureWidth: number
	defaultSignatureHeight: number
}

export function useSignatureTextPolicy(): { values: ReturnType<typeof computed<SignatureTextValues>> } {
	const policiesStore = usePoliciesStore()

	const values = computed<SignatureTextValues>(() => {
		const signatureTextPolicy = policiesStore.policies.signature_text

		// If policy exists, use its effective value
		if (signatureTextPolicy?.value) {
			const value = typeof signatureTextPolicy.value === 'string'
				? JSON.parse(signatureTextPolicy.value)
				: signatureTextPolicy.value

			return {
				template: String(value.template ?? ''),
				templateFontSize: Number(value.template_font_size ?? 9.0),
				signatureFontSize: Number(value.signature_font_size ?? 9.0),
				signatureWidth: Number(value.signature_width ?? 90.0),
				signatureHeight: Number(value.signature_height ?? 60.0),
				renderMode: String(value.render_mode ?? 'default'),
				templateError: loadState<string>('libresign', 'signature_text_template_error', ''),
				parsed: loadState<string>('libresign', 'signature_text_parsed', ''),
				defaultTemplate: loadState<string>('libresign', 'default_signature_text_template', ''),
				defaultTemplateFontSize: loadState<number>('libresign', 'default_template_font_size', 9.0),
				defaultSignatureFontSize: loadState<number>('libresign', 'default_signature_font_size', 9.0),
				defaultSignatureWidth: loadState<number>('libresign', 'default_signature_width', 90.0),
				defaultSignatureHeight: loadState<number>('libresign', 'default_signature_height', 60.0),
			}
		}

		// Fallback to legacy loadState keys (for backward compatibility during transition)
		return {
			template: loadState<string>('libresign', 'signature_text_template', ''),
			templateFontSize: loadState<number>('libresign', 'template_font_size', 9.0),
			signatureFontSize: loadState<number>('libresign', 'signature_font_size', 9.0),
			signatureWidth: loadState<number>('libresign', 'signature_width', 90.0),
			signatureHeight: loadState<number>('libresign', 'signature_height', 60.0),
			renderMode: loadState<string>('libresign', 'signature_render_mode', 'default'),
			templateError: loadState<string>('libresign', 'signature_text_template_error', ''),
			parsed: loadState<string>('libresign', 'signature_text_parsed', ''),
			defaultTemplate: loadState<string>('libresign', 'default_signature_text_template', ''),
			defaultTemplateFontSize: loadState<number>('libresign', 'default_template_font_size', 9.0),
			defaultSignatureFontSize: loadState<number>('libresign', 'default_signature_font_size', 9.0),
			defaultSignatureWidth: loadState<number>('libresign', 'default_signature_width', 90.0),
			defaultSignatureHeight: loadState<number>('libresign', 'default_signature_height', 60.0),
		}
	})

	return { values }
}
