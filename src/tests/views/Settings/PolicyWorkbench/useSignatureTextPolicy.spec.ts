/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const { initialState, policiesState } = vi.hoisted(() => ({
	initialState: {} as Record<string, unknown>,
	policiesState: {
		policies: {} as Record<string, { effectiveValue?: unknown }>,
	},
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue?: unknown) => {
		return key in initialState ? initialState[key] : defaultValue
	}),
}))

vi.mock('../../../../store/policies', () => ({
	usePoliciesStore: () => policiesState,
}))

import {
	getSignatureTextUiDefaults,
	useSignatureTextPolicy,
} from '../../../../views/Settings/PolicyWorkbench/settings/signature-text/useSignatureTextPolicy'

describe('useSignatureTextPolicy', () => {
	beforeEach(() => {
		for (const key of Object.keys(initialState)) {
			delete initialState[key]
		}

		policiesState.policies = {}
	})

	it('reads canonical UI defaults without leaking effective render mode', () => {
		Object.assign(initialState, {
			default_signature_text_template: 'Default template',
			default_template_font_size: '11.5',
			default_signature_font_size: '14.5',
			default_signature_width: '120',
			default_signature_height: '80',
			signature_render_mode: 'DESCRIPTION_ONLY',
		})

		expect(getSignatureTextUiDefaults()).toEqual({
			template: 'Default template',
			templateFontSize: 11.5,
			signatureFontSize: 14.5,
			signatureWidth: 120,
			signatureHeight: 80,
			renderMode: 'GRAPHIC_AND_DESCRIPTION',
		})
	})

	it('prefers and normalizes the effective policy payload when available', () => {
		policiesState.policies = {
			signature_text: {
				effectiveValue: JSON.stringify({
					template: 'Policy template',
					template_font_size: '10.25',
					signature_font_size: '13.75',
					signature_width: '101',
					signature_height: '66',
					render_mode: 'SIGNAME_AND_DESCRIPTION',
				}),
			},
		}
		Object.assign(initialState, {
			signature_text_template_error: 'Template error',
			signature_text_parsed: '<p>Parsed</p>',
		})

		const { values } = useSignatureTextPolicy()

		expect(values.value).toEqual({
			template: 'Policy template',
			templateFontSize: 10.25,
			signatureFontSize: 13.75,
			signatureWidth: 101,
			signatureHeight: 66,
			renderMode: 'SIGNAME_AND_DESCRIPTION',
			templateError: 'Template error',
			parsed: '<p>Parsed</p>',
		})
	})

	it('falls back to legacy initial state values when no effective policy exists', () => {
		Object.assign(initialState, {
			signature_text_template: 'Legacy template',
			template_font_size: '12.5',
			signature_font_size: '16.5',
			signature_width: '155',
			signature_height: '95',
			signature_render_mode: 'DESCRIPTION_ONLY',
			signature_text_template_error: '',
			signature_text_parsed: '<p>Legacy parsed</p>',
		})

		const { values } = useSignatureTextPolicy()

		expect(values.value).toEqual({
			template: 'Legacy template',
			templateFontSize: 12.5,
			signatureFontSize: 16.5,
			signatureWidth: 155,
			signatureHeight: 95,
			renderMode: 'DESCRIPTION_ONLY',
			templateError: '',
			parsed: '<p>Legacy parsed</p>',
		})
	})
})