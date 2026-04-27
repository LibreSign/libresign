/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import CollectMetadata from '../../../views/Settings/CollectMetadata.vue'

const emitMock = vi.fn()
const fetchEffectivePoliciesMock = vi.fn(async () => {})
const saveSystemPolicyMock = vi.fn(async () => ({ policyKey: 'collect_metadata' }))
const getEffectiveValueMock = vi.fn(() => '1')

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies: fetchEffectivePoliciesMock,
		saveSystemPolicy: saveSystemPolicyMock,
		getEffectiveValue: getEffectiveValueMock,
	}),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: (...args: unknown[]) => emitMock(...args),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

describe('CollectMetadata.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	function createWrapper() {
		return mount(CollectMetadata, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: true,
				},
			},
		})
	}

	it('loads enabled state from effective policies', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(fetchEffectivePoliciesMock).toHaveBeenCalledTimes(1)
		expect(getEffectiveValueMock).toHaveBeenCalledWith('collect_metadata')
		expect(wrapper.vm.collectMetadataEnabled).toBe(true)
	})

	it('persists the flag and emits a change event on success', async () => {
		const wrapper = createWrapper()

		wrapper.vm.collectMetadataEnabled = true
		await wrapper.vm.saveCollectMetadata()

		expect(saveSystemPolicyMock).toHaveBeenCalledWith('collect_metadata', true, false)

		expect(emitMock).toHaveBeenCalledWith('collect-metadata:changed', undefined)
	})
})
