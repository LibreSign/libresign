/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { signatureTextRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/realDefinition'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue: unknown) => {
		if (key === 'effective_policies') {
			return {
				policies: {
					signature_stamp: {
						meta: {
							defaultSystemValue: JSON.stringify({
								template: 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}',
								template_font_size: 9.8,
								signature_font_size: 20,
								signature_width: 350,
								signature_height: 100,
								background_type: 'default',
								render_mode: 'default',
							}),
						},
					},
				},
			}
		}

		return defaultValue
	}),
}))

const defaultTemplate = 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}'

describe('signatureTextRealDefinition', () => {
	it('exposes expected identity metadata', () => {
		expect(signatureTextRealDefinition.key).toBe('signature_stamp')
		expect(signatureTextRealDefinition.resolutionMode).toBe('precedence')
		expect(signatureTextRealDefinition.editorDialogLayout).toBe('wide')
	})

	it('creates default serialized value', () => {
		const value = String(signatureTextRealDefinition.createEmptyValue())
		const parsed = JSON.parse(value)

		expect(parsed).toEqual({
			template: defaultTemplate,
			template_font_size: 9.8,
			signature_font_size: 20,
			signature_width: 350,
			signature_height: 100,
			background_type: 'default',
			render_mode: 'default',
		})
	})

	it('normalizes draft values from mixed payload', () => {
		const normalized = signatureTextRealDefinition.normalizeDraftValue({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: '10.5',
			signature_font_size: '12',
			signature_width: '120',
			signature_height: '66',
			render_mode: 'graphic',
		}) as {
			signatureStampValue: string
			collectMetadataEnabled: boolean
		}
		const parsed = JSON.parse(normalized.signatureStampValue)

		expect(parsed).toEqual({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: 10.5,
			signature_font_size: 12,
			signature_width: 120,
			signature_height: 66,
			background_type: 'default',
			render_mode: 'graphic',
		})
		expect(normalized.collectMetadataEnabled).toBe(false)
	})

	it('returns defaults as fallback when not system scoped', () => {
		const fallback = String(signatureTextRealDefinition.getFallbackSystemDefault(null, 'group'))
		const parsed = JSON.parse(fallback)

		expect(parsed.render_mode).toBe('default')
		expect(parsed.signature_width).toBe(350)
	})

	it('prefers policy meta default over the current system value when available', () => {
		const systemPolicyValue = '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"custom","render_mode":"text"}'
		const policyMetaDefault = '{"template":"CANONICAL_TEMPLATE","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}'

		const fallback = signatureTextRealDefinition.getFallbackSystemDefault(systemPolicyValue, 'system', {
			meta: {
				defaultSystemValue: policyMetaDefault,
			},
		} as Parameters<typeof signatureTextRealDefinition.getFallbackSystemDefault>[2])

		expect(fallback).toBe(policyMetaDefault)
	})

	it('always allows selectable draft value', () => {
		expect(signatureTextRealDefinition.hasSelectableDraftValue('')).toBe(true)
		expect(signatureTextRealDefinition.hasSelectableDraftValue(null)).toBe(true)
	})

	it('preserves allow-child-override values', () => {
		expect(signatureTextRealDefinition.normalizeAllowChildOverride('system', true)).toBe(true)
		expect(signatureTextRealDefinition.normalizeAllowChildOverride('user', false)).toBe(false)
	})

	it('summarizes render mode and background labels', () => {
		const summary = signatureTextRealDefinition.summarizeValue({
			render_mode: 'text',
			background_type: 'custom',
		})

		expect(summary).toBe('Signer name + description • Custom background')
	})

	it('uses default labels when render mode/background are invalid', () => {
		const summary = signatureTextRealDefinition.summarizeValue({
			render_mode: 'invalid',
			background_type: 'invalid',
		})

		expect(summary).toBe('Signature + description • Default background')
	})

	it('formats override text for both states', () => {
		expect(signatureTextRealDefinition.formatAllowOverride(true)).toBe('Groups and accounts can set their own rule')
		expect(signatureTextRealDefinition.formatAllowOverride(false)).toBe('Groups and accounts must follow this value')
	})
})
