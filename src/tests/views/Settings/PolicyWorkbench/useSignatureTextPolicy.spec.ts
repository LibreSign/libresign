/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const { policiesState } = vi.hoisted(() => ({
	policiesState: {
		policies: {} as Record<string, { effectiveValue?: unknown }>,
	},
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
		policiesState.policies = {}
	})

	it('reads canonical UI defaults without leaking effective render mode', () => {
		expect(getSignatureTextUiDefaults()).toEqual({
			template: '',
			templateFontSize: 9,
			signatureFontSize: 9,
			signatureWidth: 90,
			signatureHeight: 60,
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

		const { values } = useSignatureTextPolicy()

		expect(values.value).toEqual({
			template: 'Policy template',
			templateFontSize: 10.25,
			signatureFontSize: 13.75,
			signatureWidth: 101,
			signatureHeight: 66,
			renderMode: 'SIGNAME_AND_DESCRIPTION',
		})
	})

	it('falls back to policy UI defaults when no effective policy exists', () => {
		const { values } = useSignatureTextPolicy()

		expect(values.value).toEqual({
			template: '',
			templateFontSize: 9,
			signatureFontSize: 9,
			signatureWidth: 90,
			signatureHeight: 60,
			renderMode: 'GRAPHIC_AND_DESCRIPTION',
		})
	})
})