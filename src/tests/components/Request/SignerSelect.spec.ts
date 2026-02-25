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
		const result = SignerSelect.methods.injectIcons.call({}, [
			{ id: 'alice@example.com', displayName: 'Alice Example', subname: 'alice@example.com' },
			{ id: 'bob@example.com', subname: 'bob@example.com' },
		])

		expect(result[0].label).toBe('Alice Example')
		expect(result[1].label).toBe('bob@example.com')
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
})
