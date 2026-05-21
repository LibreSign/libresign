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

export interface SignatureStampDraftValue {
	signatureStampValue: string
	collectMetadataEnabled: boolean
}

// Default values must mirror SignatureTextPolicyValue::DEFAULT_* constants (PHP backend).
// If you change a value here, update the corresponding constant in that class too.
export const SIGNATURE_TEXT_DEFAULTS = Object.freeze({
	template: '',
	templateFontSize: 9.8,
	signatureFontSize: 20,
	signatureWidth: 350.0,
	signatureHeight: 100.0,
	backgroundType: 'default',
	renderMode: 'default',
})

const RUNTIME_TO_UI_RENDER_MODE: Record<string, string> = {
	GRAPHIC_AND_DESCRIPTION: 'default',
	SIGNAME_AND_DESCRIPTION: 'text',
	GRAPHIC_ONLY: 'graphic',
	DESCRIPTION_ONLY: 'description_only',
}

const UI_TO_RUNTIME_RENDER_MODE: Record<string, string> = {
	default: 'GRAPHIC_AND_DESCRIPTION',
	text: 'SIGNAME_AND_DESCRIPTION',
	graphic: 'GRAPHIC_ONLY',
	description_only: 'DESCRIPTION_ONLY',
}

function normalizeRenderMode(value: unknown): string {
	const raw = String(value ?? 'default').trim()
	if (raw in RUNTIME_TO_UI_RENDER_MODE) {
		return RUNTIME_TO_UI_RENDER_MODE[raw]
	}
	if (['default', 'text', 'graphic', 'description_only'].includes(raw)) {
		return raw
	}
	return 'default'
}

/**
 * Convert UI render mode to runtime format (for backend API calls)
 */
export function toRuntimeRenderMode(uiMode: unknown): string {
	const normalized = normalizeRenderMode(uiMode)
	return UI_TO_RUNTIME_RENDER_MODE[normalized] ?? 'GRAPHIC_AND_DESCRIPTION'
}

function normalizeBackgroundType(value: unknown): string {
	const raw = String(value ?? 'default').trim().toLowerCase()
	if (['default', 'custom', 'deleted'].includes(raw)) {
		return raw
	}
	return 'default'
}

export function getDefaultSignatureTextPolicyConfig(): SignatureTextPolicyConfig {
	return { ...SIGNATURE_TEXT_DEFAULTS }
}

export function serializeSignatureTextPolicyConfig(config: Partial<SignatureTextPolicyConfig>): string {
	return JSON.stringify({
		template: config.template ?? SIGNATURE_TEXT_DEFAULTS.template,
		template_font_size: config.templateFontSize ?? SIGNATURE_TEXT_DEFAULTS.templateFontSize,
		signature_font_size: config.signatureFontSize ?? SIGNATURE_TEXT_DEFAULTS.signatureFontSize,
		signature_width: config.signatureWidth ?? SIGNATURE_TEXT_DEFAULTS.signatureWidth,
		signature_height: config.signatureHeight ?? SIGNATURE_TEXT_DEFAULTS.signatureHeight,
		background_type: config.backgroundType ?? SIGNATURE_TEXT_DEFAULTS.backgroundType,
		render_mode: config.renderMode ?? SIGNATURE_TEXT_DEFAULTS.renderMode,
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
			template: String(obj.template ?? SIGNATURE_TEXT_DEFAULTS.template).trim(),
			templateFontSize: Number(obj.template_font_size ?? SIGNATURE_TEXT_DEFAULTS.templateFontSize),
			signatureFontSize: Number(obj.signature_font_size ?? SIGNATURE_TEXT_DEFAULTS.signatureFontSize),
			signatureWidth: Number(obj.signature_width ?? SIGNATURE_TEXT_DEFAULTS.signatureWidth),
			signatureHeight: Number(obj.signature_height ?? SIGNATURE_TEXT_DEFAULTS.signatureHeight),
			backgroundType: normalizeBackgroundType(obj.background_type),
			renderMode: normalizeRenderMode(obj.render_mode),
		}
	}

	return getDefaultSignatureTextPolicyConfig()
}

export function resolveCollectMetadataValue(rawValue: unknown, fallback = false): boolean {
	if (typeof rawValue === 'boolean') {
		return rawValue
	}

	if (typeof rawValue === 'number') {
		if (rawValue === 1) {
			return true
		}

		if (rawValue === 0) {
			return false
		}

		return fallback
	}

	if (typeof rawValue === 'string') {
		const normalized = rawValue.trim().toLowerCase()
		if (normalized === '1' || normalized === 'true') {
			return true
		}

		if (normalized === '0' || normalized === 'false' || normalized === '') {
			return false
		}
	}

	return fallback
}

export function normalizeSignatureStampDraftValue(
	rawValue: unknown,
	fallbackCollectMetadata = false,
): SignatureStampDraftValue {
	if (rawValue && typeof rawValue === 'object') {
		const candidate = rawValue as {
			signatureStampValue?: unknown
			collectMetadataEnabled?: unknown
		}

		if ('signatureStampValue' in candidate || 'collectMetadataEnabled' in candidate) {
			const signatureStampValue = serializeSignatureTextPolicyConfig(
				normalizeSignatureTextPolicyConfig(candidate.signatureStampValue ?? null),
			)
			const collectMetadataEnabled = resolveCollectMetadataValue(
				candidate.collectMetadataEnabled,
				fallbackCollectMetadata,
			)

			return {
				signatureStampValue,
				collectMetadataEnabled,
			}
		}
	}

	return {
		signatureStampValue: serializeSignatureTextPolicyConfig(normalizeSignatureTextPolicyConfig(rawValue)),
		collectMetadataEnabled: fallbackCollectMetadata,
	}
}
