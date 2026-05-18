/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface SignatureTextPolicyConfig {
	template: string
	templateFontSize: number
	signatureFontSize: number
	signatureWidth: number
	signatureHeight: number
	backgroundType: string
	renderMode: string
}

export function getDefaultSignatureTextPolicyConfig(): SignatureTextPolicyConfig {
	return {
		template: '',
		templateFontSize: 9.0,
		signatureFontSize: 9.0,
		signatureWidth: 90.0,
		signatureHeight: 60.0,
		backgroundType: 'default',
		renderMode: 'default',
	}
}

export function serializeSignatureTextPolicyConfig(config: Partial<SignatureTextPolicyConfig>): string {
	return JSON.stringify({
		template: config.template ?? '',
		template_font_size: config.templateFontSize ?? 9.0,
		signature_font_size: config.signatureFontSize ?? 9.0,
		signature_width: config.signatureWidth ?? 90.0,
		signature_height: config.signatureHeight ?? 60.0,
		background_type: config.backgroundType ?? 'default',
		render_mode: config.renderMode ?? 'default',
	})
}

export function normalizeSignatureTextPolicyConfig(rawValue: unknown): SignatureTextPolicyConfig {
	let obj: Record<string, unknown> | null = null

	// Parse JSON string if needed
	if (typeof rawValue === 'string') {
		try {
			obj = JSON.parse(rawValue) as Record<string, unknown>
		} catch {
			return getDefaultSignatureTextPolicyConfig()
		}
	} else if (typeof rawValue === 'object' && rawValue !== null) {
		obj = rawValue as Record<string, unknown>
	}

	if (obj) {
		return {
			template: String(obj.template ?? '').trim(),
			templateFontSize: Number(obj.template_font_size ?? 9.0),
			signatureFontSize: Number(obj.signature_font_size ?? 9.0),
			signatureWidth: Number(obj.signature_width ?? 90.0),
			signatureHeight: Number(obj.signature_height ?? 60.0),
			backgroundType: String(obj.background_type ?? 'default').trim(),
			renderMode: String(obj.render_mode ?? 'default'),
		}
	}

	return getDefaultSignatureTextPolicyConfig()
}
