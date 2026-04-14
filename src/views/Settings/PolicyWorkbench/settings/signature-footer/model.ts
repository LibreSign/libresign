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
	footerTemplate: string
	previewWidth: number
	previewHeight: number
	previewZoom: number
}

export type FooterTemplateSource = 'effective' | 'inherited'

export type FooterTemplateSourceOption = {
	value: FooterTemplateSource
	label: string
	policyValue: string
}

export type FooterTemplateSourceLabels = {
	mySavedTemplate: string
	configuredTemplate: string
	defaultTemplate: string
}

export type FooterTemplateSourcePolicyState = {
	effectiveValue: EffectivePolicyValue
	sourceScope?: string | null
	inheritedValue?: unknown
}

export function getDefaultSignatureFooterPolicyConfig(): SignatureFooterPolicyConfig {
	return {
		enabled: true,
		writeQrcodeOnFooter: true,
		validationSite: '',
		customizeFooterTemplate: false,
		footerTemplate: '',
		previewWidth: 595,
		previewHeight: 100,
		previewZoom: 100,
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

function toTemplateValue(value: unknown): string {
	if (typeof value === 'string') {
		return value
	}

	if (typeof value === 'number' || typeof value === 'boolean') {
		return String(value)
	}

	return ''
}

function toInteger(value: unknown, fallback: number): number {
	if (typeof value === 'number' && Number.isFinite(value)) {
		return Math.trunc(value)
	}

	if (typeof value === 'string' && value.trim() !== '') {
		const parsed = Number.parseInt(value, 10)
		if (Number.isFinite(parsed)) {
			return parsed
		}
	}

	return fallback
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
					footerTemplate: toTemplateValue(parsedValue.footerTemplate ?? parsedValue.footer_template),
					previewWidth: toInteger(parsedValue.previewWidth ?? parsedValue.preview_width, defaults.previewWidth),
					previewHeight: toInteger(parsedValue.previewHeight ?? parsedValue.preview_height, defaults.previewHeight),
					previewZoom: toInteger(parsedValue.previewZoom ?? parsedValue.preview_zoom, defaults.previewZoom),
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
		footerTemplate: toTemplateValue(value.footerTemplate),
		previewWidth: toInteger(value.previewWidth, 595),
		previewHeight: toInteger(value.previewHeight, 100),
		previewZoom: toInteger(value.previewZoom, 100),
	}

	if (!normalizedValue.customizeFooterTemplate) {
		normalizedValue.footerTemplate = ''
	}

	return JSON.stringify(normalizedValue)
}

function hasCustomFooterTemplate(config: SignatureFooterPolicyConfig): boolean {
	return config.enabled && config.customizeFooterTemplate && config.footerTemplate.trim() !== ''
}

function toSerializedPolicyValue(config: SignatureFooterPolicyConfig): string {
	const serialized = serializeSignatureFooterPolicyConfig(config)
	return typeof serialized === 'string' ? serialized : ''
}

export function buildFooterTemplateSourceOptions(
	policy: FooterTemplateSourcePolicyState | null | undefined,
	labels: FooterTemplateSourceLabels,
): FooterTemplateSourceOption[] {
	if (!policy) {
		return []
	}

	const options: FooterTemplateSourceOption[] = []
	const effectiveConfig = normalizeSignatureFooterPolicyConfig(policy.effectiveValue)
	const inheritedConfig = normalizeSignatureFooterPolicyConfig((policy.inheritedValue ?? policy.effectiveValue) as EffectivePolicyValue)
	const hasEffectiveTemplate = hasCustomFooterTemplate(effectiveConfig)
	const hasInheritedTemplate = hasCustomFooterTemplate(inheritedConfig)

	if (hasEffectiveTemplate) {
		const isUserOwn = policy.sourceScope === 'user'
		options.push({
			value: 'effective',
			label: isUserOwn ? labels.mySavedTemplate : labels.configuredTemplate,
			policyValue: toSerializedPolicyValue(effectiveConfig),
		})
	}

	if (hasInheritedTemplate) {
		const serializedInherited = toSerializedPolicyValue(inheritedConfig)
		if (!options.some(option => option.policyValue === serializedInherited)) {
			options.push({
				value: 'inherited',
				label: labels.defaultTemplate,
				policyValue: serializedInherited,
			})
		}
	}

	return options
}

export function resolveFooterPolicyPayloadForRequest(
	canChooseFooterTemplateAtRequestLevel: boolean,
	options: FooterTemplateSourceOption[],
	selectedSource: FooterTemplateSource,
): string | null {
	if (!canChooseFooterTemplateAtRequestLevel) {
		return null
	}

	const selectedOption = options.find(option => option.value === selectedSource)
	return selectedOption?.policyValue ?? null
}
