/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type SignatureFooterPolicyConfig = {
	enabled: boolean
	writeQrcodeOnFooter: boolean
	validationSite: string
	customizeFooterTemplate: boolean
}

export function getDefaultSignatureFooterPolicyConfig(): SignatureFooterPolicyConfig {
	return {
		enabled: true,
		writeQrcodeOnFooter: true,
		validationSite: '',
		customizeFooterTemplate: false,
	}
}

function toBoolean(value: unknown, fallback: boolean): boolean {
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		return value === 1
	}

	if (typeof value === 'string') {
		return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase())
	}

	if (value === null || value === undefined) {
		return fallback
	}

	return Boolean(value)
}

function toStringValue(value: unknown): string {
	if (typeof value === 'string') {
		return value.trim()
	}

	if (typeof value === 'number' || typeof value === 'boolean') {
		return String(value)
	}

	return ''
}

export function normalizeSignatureFooterPolicyConfig(value: EffectivePolicyValue): SignatureFooterPolicyConfig {
	const defaults = getDefaultSignatureFooterPolicyConfig()

	if (typeof value === 'boolean' || typeof value === 'number') {
		return {
			...defaults,
			enabled: toBoolean(value, defaults.enabled),
		}
	}

	if (typeof value === 'string') {
		const trimmedValue = value.trim()
		if (trimmedValue === '') {
			return defaults
		}

		try {
			const parsedValue = JSON.parse(trimmedValue) as Record<string, unknown> | string | number | boolean | null
			if (parsedValue && typeof parsedValue === 'object') {
				return {
					enabled: toBoolean(parsedValue.enabled ?? parsedValue.addFooter, defaults.enabled),
					writeQrcodeOnFooter: toBoolean(parsedValue.writeQrcodeOnFooter ?? parsedValue.write_qrcode_on_footer, defaults.writeQrcodeOnFooter),
					validationSite: toStringValue(parsedValue.validationSite ?? parsedValue.validation_site),
					customizeFooterTemplate: toBoolean(parsedValue.customizeFooterTemplate ?? parsedValue.customize_footer_template, defaults.customizeFooterTemplate),
				}
			}

			if (typeof parsedValue === 'boolean' || typeof parsedValue === 'number' || typeof parsedValue === 'string') {
				return {
					...defaults,
					enabled: toBoolean(parsedValue, defaults.enabled),
				}
			}
		} catch {
			return {
				...defaults,
				enabled: toBoolean(trimmedValue, defaults.enabled),
			}
		}

		return defaults
	}

	return defaults
}

export function serializeSignatureFooterPolicyConfig(value: SignatureFooterPolicyConfig): EffectivePolicyValue {
	const normalizedValue: SignatureFooterPolicyConfig = {
		enabled: toBoolean(value.enabled, true),
		writeQrcodeOnFooter: toBoolean(value.writeQrcodeOnFooter, true),
		validationSite: toStringValue(value.validationSite),
		customizeFooterTemplate: toBoolean(value.customizeFooterTemplate, false),
	}

	return JSON.stringify(normalizedValue)
}
