/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { SignerSummaryRecord } from '../../../types/index'

import SignatureBox from '../../../components/PdfEditor/SignatureBox.vue'

const createSigner = (overrides: Partial<SignerSummaryRecord> = {}): SignerSummaryRecord => ({
	signRequestId: 0,
	displayName: '',
	email: '',
	signed: null,
	status: 0,
	statusText: '',
	...overrides,
})

const usernameToColorMock = vi.fn((_seed: string) => ({ r: 10, g: 20, b: 30 }))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string, params?: Record<string, string>) => {
		if (!params) {
			return text
		}

		return Object.entries(params).reduce((message, [key, value]) => {
			return message.replace(`{${key}}`, value)
		}, text)
	}),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/vue/functions/usernameToColor', () => ({
	usernameToColor: (seed: string) => usernameToColorMock(seed),
}))

describe('SignatureBox.vue', () => {
	it('computes the aria label from the label prop', () => {
		const wrapper = mount(SignatureBox, {
			props: {
				label: 'Ada Lovelace',
			},
		})

		expect(wrapper.attributes('aria-label')).toBe('Signature position for Ada Lovelace')
	})

	it('uses signer displayName as the color seed when available', () => {
		const wrapper = mount(SignatureBox, {
			props: {
				label: 'Fallback',
				signer: createSigner({ displayName: 'Grace Hopper' }),
			},
		})

		expect(usernameToColorMock).toHaveBeenCalledWith('Grace Hopper')
		expect(wrapper.vm.boxStyle).toEqual({
			borderColor: 'rgb(10, 20, 30)',
			backgroundColor: 'rgba(10, 20, 30, 0.12)',
		})
	})

	it('returns an empty style object when there is no seed', () => {
		const wrapper = mount(SignatureBox)

		expect(wrapper.vm.boxStyle).toEqual({})
	})
})
