/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import { signatureTextRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/signature-text/realDefinition'

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
			render_mode: 'graphic',
		})
	})

	it('returns defaults as fallback when not system scoped', () => {
		const fallback = String(signatureTextRealDefinition.getFallbackSystemDefault(null, 'group'))
		const parsed = JSON.parse(fallback)

		expect(parsed.render_mode).toBe('default')
		expect(parsed.signature_width).toBe(90)
	})
})
