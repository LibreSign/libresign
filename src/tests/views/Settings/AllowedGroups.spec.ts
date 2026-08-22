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
const getEffectiveValueMock = vi.fn(() => '{"allowGroups":["admin"],"denyGroups":[]}')
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

	it('renders managed-group guidance in the legacy settings copy', async () => {
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'admin', displayname: 'admin' },
						],
					},
				},
			},
		})

		const wrapper = mount(AllowedGroups as never, {
			global: {
				stubs: {
					NcSettingsSection: {
						name: 'NcSettingsSection',
						props: ['name', 'description'],
						template: '<div class="settings-section-stub" :data-name="name" :data-description="description"><slot /></div>',
					},
					NcSelect: {
						name: 'NcSelect',
						props: ['modelValue', 'ariaLabelCombobox'],
						emits: ['update:modelValue', 'search-change'],
						template: '<div class="nc-select-stub" :data-aria-label="ariaLabelCombobox" />',
					},
				},
			},
		})
		await flushPromises()

		expect(wrapper.find('.settings-section-stub').attributes('data-description')).toBe('Choose which groups are authorized to create signature requests. Delegated group admins may authorize only groups they manage. The default admin group always has this permission.')
		expect(wrapper.find('.nc-select-stub').attributes('data-aria-label')).toBe('Choose groups authorized to create signature requests. Delegated group admins may authorize only groups they manage.')
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
		expect(firstPersistCall?.[1]).toBe('{"allowGroups":["admin","testGroup"],"denyGroups":[]}')

		select = wrapper.findComponent({ name: 'NcSelect' })

		select.vm.$emit('update:modelValue', [
			{ id: 'admin', displayname: 'admin' },
		])
		await flushPromises()

		const secondPersistCall = saveSystemPolicyMock.mock.calls.at(-1) as [string, string, boolean] | undefined
		expect(secondPersistCall?.[0]).toBe('groups_request_sign')
		expect(secondPersistCall?.[1]).toBe('{"allowGroups":["admin"],"denyGroups":[]}')
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
		expect(lastCall?.[1]).toBe('{"allowGroups":["admin","SÖ"],"denyGroups":[]}')
	})

	it('preserves deny groups when updating allow list from legacy settings view', async () => {
		getEffectiveValueMock.mockReturnValue('{"allowGroups":["admin"],"denyGroups":["legal"]}')

		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'admin', displayname: 'admin' },
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
			{ id: 'finance', displayname: 'finance' },
		])
		await flushPromises()

		const lastCall = saveSystemPolicyMock.mock.calls.at(-1) as [string, string, boolean] | undefined
		expect(lastCall?.[1]).toBe('{"allowGroups":["admin","finance"],"denyGroups":["legal"]}')
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
		const loadingStates: boolean[] = []
		const loading = (state: boolean) => loadingStates.push(state)

		select.vm.$emit('search', 'fin', loading)
		await flushPromises()

		// The select's own spinner was toggled on then off...
		expect(loadingStates).toEqual([true, false])
		// ...while `loadingGroups` (and therefore `:disabled`) never fired during search.
		expect(vm.loadingGroups).toBe(false)
		expect(select.props('disabled')).toBe(false)

		// And the query still reached the backend.
		const searchCalls = axiosGetMock.mock.calls.filter((c: unknown[]) => String(c[0]).includes('cloud/groups/details'))
		expect(searchCalls.length).toBe(1)
		expect((searchCalls[0] as [string, { params: { search: string } }])[1].params.search).toBe('fin')
	})
})
