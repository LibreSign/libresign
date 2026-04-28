/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const axiosGetMock = vi.fn()
const fetchEffectivePoliciesMock = vi.fn(async () => {})
const getEffectiveValueMock = vi.fn((policyKey: string) => {
	if (policyKey === 'identification_documents') {
		return true
	}

	if (policyKey === 'approval_group') {
		return []
	}

	return null
})
const saveSystemPolicyMock = vi.fn(async (_policyKey: string, value: string) => ({ effectiveValue: value }))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: unknown[]) => axiosGetMock(...args),
	},
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies: fetchEffectivePoliciesMock,
		getEffectiveValue: getEffectiveValueMock,
		saveSystemPolicy: saveSystemPolicyMock,
	}),
}))

let IdentificationDocuments: unknown

beforeAll(async () => {
	;({ default: IdentificationDocuments } = await import('../../../views/Settings/IdentificationDocuments.vue'))
})

describe('IdentificationDocuments', () => {
	beforeEach(() => {
		axiosGetMock.mockReset()
		fetchEffectivePoliciesMock.mockClear()
		getEffectiveValueMock.mockClear()
		saveSystemPolicyMock.mockClear()
	})

	it('saves groups on update:modelValue', async () => {
		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'grpA', displayname: 'Group A' },
								],
							},
						},
					},
				})
			}
			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		const wrapper = mount(IdentificationDocuments as never, {
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

		const ncSelect = wrapper.findComponent({ name: 'NcSelect' })
		ncSelect.vm.$emit('update:modelValue', [{ id: 'grpA', displayname: 'Group A' }])
		await flushPromises()

		expect(saveSystemPolicyMock).toHaveBeenCalledWith('approval_group', '["grpA"]', false)
	})
})
