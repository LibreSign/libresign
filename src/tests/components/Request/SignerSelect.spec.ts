/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import SignerSelect from '../../../components/Request/SignerSelect.vue'

const { axiosGetMock } = vi.hoisted(() => ({
	axiosGetMock: vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGetMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

describe('SignerSelect.vue', () => {
	beforeEach(() => {
		axiosGetMock.mockReset()
	})

	it('injectIcons sets a visible label fallback from displayName or id', () => {
		const result = SignerSelect.methods.injectIcons.call({ method: 'all' }, [
			{ id: 'alice@example.com', displayName: 'Alice Example', subname: 'alice@example.com', iconSvg: 'svgAccount' },
			{ id: 'bob@example.com', subname: 'bob@example.com' },
			{ id: 'email@example.com', displayName: 'Email User', iconSvg: 'svgEmail' },
			{ id: 'custom@example.com', displayName: 'Custom Icon', iconSvg: '<svg>custom</svg>' },
		])

		expect(result[0].label).toBe('Alice Example')
		expect(result[1].label).toBe('bob@example.com')
		expect(result[0].iconSvg).not.toBe('svgAccount')
		expect(result[2].iconSvg).not.toBe('svgEmail')
		expect(result[3].iconSvg).toBeUndefined()
	})

	it('injectIcons does not infer icon when backend does not provide icon fields', () => {
		const result = SignerSelect.methods.injectIcons.call({ method: 'email' }, [
			{ id: 'user@example.com', displayName: 'User Email' },
		])

		expect(result[0].iconSvg).toBeUndefined()
	})

	it('injectIcons maps API icon classes to corresponding svg icons', () => {
		const result = SignerSelect.methods.injectIcons.call({ method: 'all' }, [
			{ id: 'leon@example.com', displayName: 'Leon Green', method: 'email', icon: 'icon-mail' },
			{ id: 'user01', displayName: 'user01', method: 'account', icon: 'icon-user' },
		])

		expect(result[0].iconSvg).toBeTruthy()
		expect(result[1].iconSvg).toBeTruthy()
	})

	it('async search populates options with readable labels', async () => {
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: [
						{ id: 'carol@example.com', displayName: 'Carol' },
					],
				},
			},
		})

		const context: any = {
			method: 'account',
			loading: false,
			haveError: false,
			options: [],
			injectIcons: SignerSelect.methods.injectIcons,
		}

		await SignerSelect.methods._asyncFind.call(context, 'car')

		expect(axiosGetMock).toHaveBeenCalled()
		expect(context.loading).toBe(false)
		expect(context.haveError).toBe(false)
		expect(context.options).toHaveLength(1)
		expect(context.options[0].label).toBe('Carol')
	})

	it('option helpers safely handle undefined slot payload', () => {
		const context: any = {
			getOption: SignerSelect.methods.getOption,
		}

		expect(SignerSelect.methods.getOptionLabel.call(context, undefined)).toBe('')
		expect(SignerSelect.methods.getOptionSubname.call(context, undefined)).toBe('')
		expect(SignerSelect.methods.getOptionIcon.call(context, undefined)).toBe('')

		const slotProps = { option: { displayName: 'Admin', subname: 'admin', iconSvg: '<svg>x</svg>' } }
		expect(SignerSelect.methods.getOptionLabel.call(context, slotProps)).toBe('Admin')
		expect(SignerSelect.methods.getOptionSubname.call(context, slotProps)).toBe('admin')
		expect(SignerSelect.methods.getOptionIcon.call(context, slotProps)).toBe('<svg>x</svg>')
	})
})
