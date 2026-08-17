/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const axiosGetMock = vi.fn()
const axiosPostMock = vi.fn(() => Promise.resolve({ data: { ocs: { data: {} } } }))
const generateOcsUrlMock = vi.fn((path: string) => path)
const confirmPasswordMock = vi.fn(() => Promise.resolve())

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGetMock,
		post: axiosPostMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (...args: unknown[]) => generateOcsUrlMock(...(args as [string])),
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: () => confirmPasswordMock(),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

let AllowedGroups: unknown

beforeAll(async () => {
	;({ default: AllowedGroups } = await import('../../../views/Settings/AllowedGroups.vue'))
})

describe('AllowedGroups', () => {
	beforeEach(() => {
		axiosGetMock.mockReset()
		axiosPostMock.mockClear()
		generateOcsUrlMock.mockClear()
		confirmPasswordMock.mockClear()
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
						emits: ['update:modelValue', 'search'],
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

		expect(axiosPostMock).toHaveBeenCalledWith('apps/libresign/api/v1/admin/groups-request-sign/config', {
			groups: ['admin', 'testGroup'],
		})

		select = wrapper.findComponent({ name: 'NcSelect' })

		select.vm.$emit('update:modelValue', [
			{ id: 'admin', displayname: 'admin' },
		])
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalledWith('apps/libresign/api/v1/admin/groups-request-sign/config', {
			groups: ['admin'],
		})
		expect(confirmPasswordMock).toHaveBeenCalledTimes(2)
	})

	it('sends special characters through typed admin endpoint payload', async () => {
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
						emits: ['update:modelValue', 'search'],
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

		expect(axiosPostMock).toHaveBeenCalledWith('apps/libresign/api/v1/admin/groups-request-sign/config', {
			groups: ['admin', 'SÖ'],
		})
	})

	it('queries the backend when the user types in the group selector (issue #7988)', async () => {
		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'finance', displayname: 'finance' },
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
						// NcSelect re-exposes vue-select's native `search` event (see @nextcloud/vue).
						emits: ['update:modelValue', 'search'],
						template: '<div class="nc-select-stub" />',
					},
				},
			},
		})
		await flushPromises()

		// Ignore the initial onMounted load; observe only what typing triggers.
		axiosGetMock.mockClear()

		const select = wrapper.findComponent({ name: 'NcSelect' })
		// NcSelect's `search` event passes (query, loading); loading toggles its own spinner.
		select.vm.$emit('search', 'fin', () => {})
		await flushPromises()

		const searchCalls = axiosGetMock.mock.calls.filter((call: unknown[]) => String(call[0]).includes('cloud/groups/details'))
		expect(searchCalls.length).toBeGreaterThan(0)
		const lastSearch = searchCalls.at(-1) as [string, { params: { search: string } }] | undefined
		expect(lastSearch?.[1].params.search).toBe('fin')
	})

	it('keeps the group selector enabled while searching so it never loses focus (issue #7988)', async () => {
		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: { ocs: { data: { groups: [{ id: 'finance', displayname: 'finance' }] } } },
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
						// Expose disabled/loading so the test can assert the input stays enabled.
						props: ['modelValue', 'disabled', 'loading'],
						emits: ['update:modelValue', 'search'],
						template: '<div class="nc-select-stub" />',
					},
				},
			},
		})
		await flushPromises()

		// Ignore the initial onMounted load; observe only what typing triggers.
		axiosGetMock.mockClear()

		const select = wrapper.findComponent({ name: 'NcSelect' })
		const vm = wrapper.vm as unknown as { loadingGroups: boolean }

		// The loading state must be driven through NcSelect's own `search`-event
		// callback, not the reactive `loadingGroups`/`:disabled` binding — disabling
		// the focused input is exactly what dropped focus per keystroke (#7988).
		select.vm.$emit('search', 'fin')
		await flushPromises()

		// `loadingGroups` (and therefore `:disabled`) remains false during search so focus is kept.
		expect(vm.loadingGroups).toBe(false)
		expect(select.props('disabled')).toBe(false)

		// And the query still reached the backend.
		const searchCalls = axiosGetMock.mock.calls.filter((c: unknown[]) => String(c[0]).includes('cloud/groups/details'))
		expect(searchCalls.length).toBe(1)
		expect((searchCalls[0] as [string, { params: { search: string } }])[1].params.search).toBe('fin')
	})
})
