/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import ExpirationRules from '../../../views/Settings/ExpirationRules.vue'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

describe('ExpirationRules.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(axios.get)
			.mockResolvedValueOnce({ data: { ocs: { data: { data: 120 } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: 30 } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: 365 } } } })
	})

	function createWrapper() {
		return mount(ExpirationRules, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcTextField: true,
					NcCheckboxRadioSwitch: true,
				},
			},
		})
	}

	it('loads maximum validity, renewal interval, and expiry from provisioning config', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.maximumValidity).toBe('120')
		expect(wrapper.vm.enableMaximumValidity).toBe(true)
		expect(wrapper.vm.renewalInterval).toBe('30')
		expect(wrapper.vm.enableRenewalInterval).toBe(true)
		expect(wrapper.vm.expiryInDays).toBe('365')
	})

	it('persists zero maximum validity when the toggle is disabled', () => {
		const wrapper = createWrapper()

		wrapper.vm.enableMaximumValidity = false
		wrapper.vm.maximumValidity = '180'
		wrapper.vm.saveMaximumValidity()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'maximum_validity', '0')
	})

	it('persists zero renewal interval when the toggle is disabled', () => {
		const wrapper = createWrapper()

		wrapper.vm.enableRenewalInterval = false
		wrapper.vm.renewalInterval = '45'
		wrapper.vm.saveRenewalInterval()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'renewal_interval', '0')
	})

	it('falls back to 365 days when expiry is empty before saving', () => {
		const wrapper = createWrapper()

		wrapper.vm.expiryInDays = ''
		wrapper.vm.saveExpiryInDays()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'expiry_in_days', '365')
	})
})
