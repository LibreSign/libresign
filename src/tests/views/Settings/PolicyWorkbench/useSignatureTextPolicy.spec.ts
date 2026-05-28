/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	getSignatureTextUiDefaults,
	useSignatureTextPolicy,
} from '../../../../views/Settings/PolicyWorkbench/settings/signature-text/useSignatureTextPolicy'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue: unknown) => {
		if (key === 'effective_policies') {
			return {
				policies: {
					signature_stamp: {
						effectiveValue: JSON.stringify({
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
			}
		}

		return defaultValue
	}),
}))

const { policiesState } = vi.hoisted(() => ({
	policiesState: {
		policies: {} as Record<string, { effectiveValue?: unknown }>,
	},
}))

vi.mock('../../../../store/policies', () => ({
	usePoliciesStore: () => policiesState,
}))

const defaultTemplate = 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}'

describe('useSignatureTextPolicy', () => {
	beforeEach(() => {
		policiesState.policies = {}
	})

	it('reads canonical UI defaults without leaking effective render mode', () => {
		expect(getSignatureTextUiDefaults()).toEqual({
			template: defaultTemplate,
			templateFontSize: 9.8,
			signatureFontSize: 20,
			signatureWidth: 350,
			signatureHeight: 100,
			backgroundType: 'default',
			renderMode: 'default',
		})
	})

	it('prefers and normalizes the effective policy payload when available', () => {
		policiesState.policies = {
			signature_stamp: {
				effectiveValue: JSON.stringify({
					template: 'Policy template',
					template_font_size: '10.25',
					signature_font_size: '13.75',
					signature_width: '101',
					signature_height: '66',
					background_type: 'deleted',
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
			backgroundType: 'deleted',
			renderMode: 'text',
		})
	})

	it('falls back to policy UI defaults when no effective policy exists', () => {
		const { values } = useSignatureTextPolicy()

		expect(values.value).toEqual({
			template: defaultTemplate,
			templateFontSize: 9.8,
			signatureFontSize: 20,
			signatureWidth: 350,
			signatureHeight: 100,
			backgroundType: 'default',
			renderMode: 'default',
		})
	})
})
