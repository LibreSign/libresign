/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'
import SignerSelect from '../../../components/Request/SignerSelect.vue'

const supportedIconNames = [
	'account',
	'email',
	'signal',
	'sms',
	'telegram',
	'whatsapp',
	'xmpp',
] as const

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

vi.mock('@nextcloud/l10n', () => createL10nMock())

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

	it('injectIcons trusts the API displayName/subname contract', () => {
		const wrapper = createWrapper()
		const result = wrapper.vm.injectIcons([
			{ identify: 'alice@example.com', isNoUser: false, shareType: 0, displayName: 'Alice Example', subname: 'alice@example.com', iconName: 'account' },
			{ identify: 'email@example.com', isNoUser: true, shareType: 4, displayName: 'Email User', subname: 'email@example.com', iconName: 'email' },
			{ identify: 'custom@example.com', isNoUser: true, shareType: 4, displayName: 'Custom Icon', subname: 'custom@example.com', iconName: 'unknown' as unknown as 'account' },
		])

		expect(wrapper.vm.getOptionKey(result[0])).toBe('alice@example.com')
		expect(result[0].displayName).toBe('Alice Example')
		expect(result[0].subname).toBe('alice@example.com')
		expect(result[1].displayName).toBe('Email User')
		expect(result[0].iconName).toBe('account')
		expect(result[1].iconName).toBe('email')
		expect(result[2].iconName).toBeUndefined()
	})

	it('onSelectedSignerChange emits a plain OpenAPI signer object', () => {
		const wrapper = createWrapper({ method: 'email' })
		const selected = wrapper.vm.injectIcons([
			{ identify: 'signer01@libresign.coop', isNoUser: true, shareType: 4, displayName: 'signer01@libresign.coop', subname: 'signer01@libresign.coop', method: 'email', iconName: 'email' },
		])[0]

		wrapper.vm.onSelectedSignerChange(selected)

		expect(wrapper.emitted('update:signer')?.at(-1)?.[0]).toEqual({
			identify: 'signer01@libresign.coop',
			isNoUser: true,
			shareType: 4,
			displayName: 'signer01@libresign.coop',
			subname: 'signer01@libresign.coop',
			method: 'email',
			iconName: 'email',
		})
	})

	it('getOptionKey uses identify as the select identity', () => {
		const wrapper = createWrapper({ method: 'email' })
		const selected = wrapper.vm.injectIcons([
			{ identify: 'manual@libresign.coop', isNoUser: true, shareType: 4, displayName: 'Manual', subname: 'manual@libresign.coop', method: 'email' },
		])[0]

		expect(wrapper.vm.getOptionKey(selected)).toBe('manual@libresign.coop')
	})

	it('injectIcons keeps backend icon keys as the contract', () => {
		const wrapper = createWrapper()
		const result = wrapper.vm.injectIcons([
			{ identify: 'leon@example.com', isNoUser: true, shareType: 4, displayName: 'Leon Green', subname: 'leon@example.com', method: 'email', iconName: 'email' },
			{ identify: 'user01', isNoUser: false, shareType: 0, displayName: 'user01', subname: 'user01@example.com', method: 'account', iconName: 'account' },
		])

		expect(result[0].iconName).toBe('email')
		expect(result[1].iconName).toBe('account')
	})

	it.each(supportedIconNames)('getOptionIcon resolves %s to an inline svg', (iconName) => {
		const wrapper = createWrapper()
		const mapped = wrapper.vm.injectIcons([
			{ identify: `${iconName}@example.com`, isNoUser: true, shareType: 4, displayName: iconName, subname: `${iconName}@example.com`, iconName },
		])[0]

		expect(mapped.iconName).toBe(iconName)
		expect(wrapper.vm.getOptionIcon({ option: mapped })).toContain('<svg')
	})

	it('async search populates options with readable labels', async () => {
		const wrapper = createWrapper({ method: 'account' })
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: [
						{ identify: 'carol@example.com', isNoUser: false, shareType: 0, displayName: 'Carol', subname: 'carol@example.com' },
					],
				},
			},
		})
		await wrapper.vm._asyncFind('car')

		expect(axiosGetMock).toHaveBeenCalled()
		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.vm.haveError).toBe(false)
		expect(wrapper.vm.options).toHaveLength(1)
		expect(wrapper.vm.options[0].displayName).toBe('Carol')
	})

	it('clears stale results immediately when search is blank', async () => {
		const wrapper = createWrapper({ method: 'account' })
		wrapper.vm.options = [{ identify: 'legacy@example.com', isNoUser: true, shareType: 4, displayName: 'Legacy', subname: 'legacy@example.com' }]
		wrapper.vm.loading = true

		await wrapper.vm._asyncFind('   ')

		expect(axiosGetMock).not.toHaveBeenCalled()
		expect(wrapper.vm.options).toEqual([])
		expect(wrapper.vm.loading).toBe(false)
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
					data: [{ identify: 'user02', isNoUser: false, shareType: 0, displayName: 'User 02', subname: 'user02@example.com' }],
				},
			},
		})
		await secondCall

		resolveFirst?.({
			data: {
				ocs: {
					data: [{ identify: 'old@example.com', isNoUser: true, shareType: 4, displayName: 'Old Result', subname: 'old@example.com' }],
				},
			},
		})
		await firstCall

		expect(wrapper.vm.options).toHaveLength(1)
		expect(wrapper.vm.options[0].displayName).toBe('User 02')
	})

	it('clears stale options when method changes', () => {
		const wrapper = createWrapper()
		wrapper.vm.options = [{ identify: 'legacy@example.com', isNoUser: true, shareType: 4, displayName: 'Legacy', subname: 'legacy@example.com' }]
		wrapper.vm.selectedSigner = wrapper.vm.options[0]
		wrapper.vm.haveError = true
		wrapper.vm.loading = true

		wrapper.vm.handleMethodChange()

		expect(wrapper.vm.options).toEqual([])
		expect(wrapper.vm.selectedSigner).toBe(null)
		expect(wrapper.vm.haveError).toBe(false)
		expect(wrapper.vm.loading).toBe(false)
	})

	it('option helpers safely handle undefined slot payload', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.getOptionLabel(undefined)).toBe('')
		expect(wrapper.vm.getOptionSubname(undefined)).toBe('')
		expect(wrapper.vm.getOptionIcon(undefined)).toBe('')

		const mapped = wrapper.vm.injectIcons([{ identify: 'admin', isNoUser: false, shareType: 0, displayName: 'Admin', subname: 'admin', iconName: 'account' }])[0]
		const slotProps = { option: mapped }
		expect(wrapper.vm.getOption(mapped)).toEqual(mapped)
		expect(wrapper.vm.getOptionLabel(slotProps)).toBe('Admin')
		expect(wrapper.vm.getOptionSubname(slotProps)).toBe('admin')
		expect(wrapper.vm.getOptionIcon(slotProps)).toContain('<svg')
	})
})
