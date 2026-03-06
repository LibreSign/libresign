/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
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

	function createWrapper(props: Record<string, unknown> = {}) {
		return mount(SignerSelect, {
			props,
			global: {
				stubs: {
					NcAvatar: true,
					NcSelect: {
						name: 'NcSelect',
						template: '<div><slot name="option" :option="{}" /><slot name="no-options" :search="\'\'" /></div>',
						props: ['modelValue'],
						emits: ['update:modelValue', 'search'],
					},
					NcIconSvgWrapper: true,
				},
			},
		})
	}

	it('injectIcons sets a visible label fallback from displayName or id', () => {
		const wrapper = createWrapper()
		const result = wrapper.vm.injectIcons([
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
		const wrapper = createWrapper({ method: 'email' })
		const result = wrapper.vm.injectIcons([
			{ id: 'user@example.com', displayName: 'User Email' },
		])

		expect(result[0].iconSvg).toBeUndefined()
	})

	it('injectIcons maps API icon classes to corresponding svg icons', () => {
		const wrapper = createWrapper()
		const result = wrapper.vm.injectIcons([
			{ id: 'leon@example.com', displayName: 'Leon Green', method: 'email', icon: 'icon-mail' },
			{ id: 'user01', displayName: 'user01', method: 'account', icon: 'icon-user' },
		])

		expect(result[0].iconSvg).toBeTruthy()
		expect(result[1].iconSvg).toBeTruthy()
	})

	it('async search populates options with readable labels', async () => {
		const wrapper = createWrapper({ method: 'account' })
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: [
						{ id: 'carol@example.com', displayName: 'Carol' },
					],
				},
			},
		})
		await wrapper.vm._asyncFind('car')

		expect(axiosGetMock).toHaveBeenCalled()
		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.vm.haveError).toBe(false)
		expect(wrapper.vm.options).toHaveLength(1)
		expect(wrapper.vm.options[0].label).toBe('Carol')
	})

	it('ignores stale async response when a newer search was triggered', async () => {
		const wrapper = createWrapper({ method: 'account' })
		let resolveFirst: ((value: any) => void) | undefined
		let resolveSecond: ((value: any) => void) | undefined

		axiosGetMock
			.mockImplementationOnce(() => new Promise((resolve) => {
				resolveFirst = resolve
			}))
			.mockImplementationOnce(() => new Promise((resolve) => {
				resolveSecond = resolve
			}))


		const firstCall = wrapper.vm._asyncFind('a')
		const secondCall = wrapper.vm._asyncFind('ab')

		resolveSecond?.({
			data: {
				ocs: {
					data: [{ id: 'user02', displayName: 'User 02' }],
				},
			},
		})
		await secondCall

		resolveFirst?.({
			data: {
				ocs: {
					data: [{ id: 'old@example.com', displayName: 'Old Result' }],
				},
			},
		})
		await firstCall

		expect(wrapper.vm.options).toHaveLength(1)
		expect(wrapper.vm.options[0].label).toBe('User 02')
	})

	it('clears stale options when method changes', () => {
		const wrapper = createWrapper()
		wrapper.vm.options = [{ id: 'legacy' }]
		wrapper.vm.haveError = true
		wrapper.vm.loading = true

		wrapper.vm.handleMethodChange()

		expect(wrapper.vm.options).toEqual([])
		expect(wrapper.vm.haveError).toBe(false)
		expect(wrapper.vm.loading).toBe(false)
	})

	it('option helpers safely handle undefined slot payload', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.getOptionLabel(undefined)).toBe('')
		expect(wrapper.vm.getOptionSubname(undefined)).toBe('')
		expect(wrapper.vm.getOptionIcon(undefined)).toBe('')

		const slotProps = { option: { displayName: 'Admin', subname: 'admin', iconSvg: '<svg>x</svg>' } }
		expect(wrapper.vm.getOptionLabel(slotProps)).toBe('Admin')
		expect(wrapper.vm.getOptionSubname(slotProps)).toBe('admin')
		expect(wrapper.vm.getOptionIcon(slotProps)).toBe('<svg>x</svg>')
	})
})
