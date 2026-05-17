/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const axiosGetMock = vi.fn()
const generateOcsUrlMock = vi.fn((path: string) => path)
const confirmPasswordMock = vi.fn(() => Promise.resolve())
const fetchEffectivePoliciesMock = vi.fn(async () => {})
const getEffectiveValueMock = vi.fn(() => '["admin"]')
const saveSystemPolicyMock = vi.fn(async () => ({ policyKey: 'groups_request_sign' }))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGetMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (...args: unknown[]) => generateOcsUrlMock(...(args as [string])),
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: () => confirmPasswordMock(),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies: fetchEffectivePoliciesMock,
		getEffectiveValue: getEffectiveValueMock,
		saveSystemPolicy: saveSystemPolicyMock,
	}),
}))
let AllowedGroups: unknown

beforeAll(async () => {
	;({ default: AllowedGroups } = await import('../../../views/Settings/AllowedGroups.vue'))
})

describe('AllowedGroups', () => {
	beforeEach(() => {
		axiosGetMock.mockReset()
		generateOcsUrlMock.mockClear()
		confirmPasswordMock.mockClear()
		fetchEffectivePoliciesMock.mockClear()
		getEffectiveValueMock.mockClear()
		saveSystemPolicyMock.mockClear()
	})

	it('persists when adding and removing groups', async () => {
		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'admin', displayname: 'admin' },
									{ id: 'testGroup', displayname: 'testGroup' },
								],
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		const wrapper = mount(AllowedGroups as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcSelect: {
						name: 'NcSelect',
						props: ['modelValue'],
						emits: ['update:modelValue', 'search-change'],
						template: '<div class="nc-select-stub" />',
					},
				},
			},
		})
		await flushPromises()

		let select = wrapper.findComponent({ name: 'NcSelect' })

		select.vm.$emit('update:modelValue', [
			{ id: 'admin', displayname: 'admin' },
			{ id: 'testGroup', displayname: 'testGroup' },
		])
		await flushPromises()

		expect(saveSystemPolicyMock).toHaveBeenCalled()
		const firstPersistCall = saveSystemPolicyMock.mock.calls.at(-1) as [string, string, boolean] | undefined
		expect(firstPersistCall?.[0]).toBe('groups_request_sign')
		expect(firstPersistCall?.[1]).toBe('["admin","testGroup"]')

		select = wrapper.findComponent({ name: 'NcSelect' })

		select.vm.$emit('update:modelValue', [
			{ id: 'admin', displayname: 'admin' },
		])
		await flushPromises()

		const secondPersistCall = saveSystemPolicyMock.mock.calls.at(-1) as [string, string, boolean] | undefined
		expect(secondPersistCall?.[0]).toBe('groups_request_sign')
		expect(secondPersistCall?.[1]).toBe('["admin"]')
		expect(confirmPasswordMock).toHaveBeenCalledTimes(2)
	})

	it('saves special characters preserving policy serialization', async () => {
		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'admin', displayname: 'admin' },
									{ id: 'SÖ', displayname: 'SÖ' },
								],
							},
						},
					},
				})
			}

			if (url.includes('groups_request_sign')) {
				return Promise.resolve({ data: { ocs: { data: { data: '["admin"]' } } } })
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		const wrapper = mount(AllowedGroups as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcSelect: {
						name: 'NcSelect',
						props: ['modelValue'],
						emits: ['update:modelValue', 'search-change'],
						template: '<div class="nc-select-stub" />',
					},
				},
			},
		})
		await flushPromises()

		const select = wrapper.findComponent({ name: 'NcSelect' })
		select.vm.$emit('update:modelValue', [
			{ id: 'admin', displayname: 'admin' },
			{ id: 'SÖ', displayname: 'SÖ' },
		])
		await flushPromises()

		const lastCall = saveSystemPolicyMock.mock.calls.at(-1) as any
		expect(lastCall?.[0]).toBe('groups_request_sign')
		expect(lastCall?.[1]).toBe('["admin","SÖ"]')
	})
})
