/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (_app: string, key: string, fallback: unknown) => {
		if (key === 'effective_policies') {
			return {
				policies: {
					signature_stamp: {
						effectiveValue: '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"default","render_mode":"default"}',
					},
				},
			}
		}

		return fallback
	},
}))

import { signatureTextRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/realDefinition'

describe('signatureTextRealDefinition', () => {
	it('creates default serialized value', () => {
		const value = String(signatureTextRealDefinition.createEmptyValue())
		const parsed = JSON.parse(value)

		expect(parsed).toEqual({
			template: '',
			template_font_size: 9,
			signature_font_size: 9,
			signature_width: 90,
			signature_height: 60,
			background_type: 'default',
			render_mode: 'default',
		})
	})

	it('normalizes draft values from mixed payload', () => {
		const normalized = String(signatureTextRealDefinition.normalizeDraftValue({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: '10.5',
			signature_font_size: '12',
			signature_width: '120',
			signature_height: '66',
			render_mode: 'graphic',
		}))
		const parsed = JSON.parse(normalized)

		expect(parsed).toEqual({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: 10.5,
			signature_font_size: 12,
			signature_width: 120,
			signature_height: 66,
			background_type: 'default',
			render_mode: 'graphic',
		})
	})

	it('returns defaults as fallback when not system scoped', () => {
		const fallback = String(signatureTextRealDefinition.getFallbackSystemDefault(null, 'group'))
		const parsed = JSON.parse(fallback)

		expect(parsed.render_mode).toBe('default')
		expect(parsed.signature_width).toBe(90)
	})

	it('seeds base editorProps inheritedValue from effective policies initial state', () => {
		expect(signatureTextRealDefinition.editorProps).toMatchObject({
			inheritedValue: '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"default","render_mode":"default"}',
		})
	})

	it('falls back to base inheritedValue when policy has no inheritedValue property', () => {
		const baseEditorProps = {
			inheritedValue: '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"default","render_mode":"default"}',
		}
		const policy = {
			effectiveValue: 'parallel',
		} as unknown as Parameters<NonNullable<typeof signatureTextRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureTextRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		expect(resolved).toEqual(baseEditorProps)
	})

	it('uses inherited policy payload when provided', () => {
		const baseEditorProps = {
			inheritedValue: '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"default","render_mode":"default"}',
		}
		const policy = {
			sourceScope: 'group',
			inheritedValue: '{"template":"GROUP_TEMPLATE","template_font_size":12,"signature_font_size":14,"signature_width":210,"signature_height":88,"background_type":"deleted","render_mode":"text"}',
		} as unknown as Parameters<NonNullable<typeof signatureTextRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureTextRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		expect(resolved).toMatchObject({
			inheritedValue: '{"template":"GROUP_TEMPLATE","template_font_size":12,"signature_font_size":14,"signature_width":210,"signature_height":88,"background_type":"deleted","render_mode":"text"}',
		})
	})

	it('keeps base inheritedValue when policy source is system', () => {
		const baseEditorProps = {
			inheritedValue: '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"default","render_mode":"default"}',
		}
		const policy = {
			sourceScope: 'system',
			inheritedValue: '{"template":"IGNORED_TEMPLATE","template_font_size":15,"signature_font_size":16,"signature_width":333,"signature_height":111,"background_type":"deleted","render_mode":"text"}',
		} as unknown as Parameters<NonNullable<typeof signatureTextRealDefinition.resolveEditorProps>>[0]

		const resolved = signatureTextRealDefinition.resolveEditorProps?.(policy, baseEditorProps)

		expect(resolved).toEqual(baseEditorProps)
	})

	it('keeps base inheritedValue when policy is null', () => {
		const baseEditorProps = {
			inheritedValue: '{"template":"SYSTEM_TEMPLATE","template_font_size":10,"signature_font_size":11,"signature_width":150,"signature_height":65,"background_type":"default","render_mode":"default"}',
		}

		const resolved = signatureTextRealDefinition.resolveEditorProps?.(null, baseEditorProps)

		expect(resolved).toEqual(baseEditorProps)
	})
})