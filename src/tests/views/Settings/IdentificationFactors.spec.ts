/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createL10nMock } from '../../testHelpers/l10n.js'

import IdentificationFactors from '../../../views/Settings/IdentificationFactors.vue'

const useConfigureCheckStoreMock = vi.fn()

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('../../../store/configureCheck.js', () => ({
	useConfigureCheckStore: (...args: unknown[]) => useConfigureCheckStoreMock(...args),
}))

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

describe('IdentificationFactors.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		useConfigureCheckStoreMock.mockReturnValue({
			isNoneEngine: false,
			identifyMethods: [
				{
					name: 'email',
					friendly_name: 'Email',
					enabled: true,
					can_create_account: true,
					signatureMethods: {
						sms: { enabled: false, label: 'SMS' },
						email: { enabled: true, label: 'Email' },
					},
				},
				{
					name: 'phone',
					friendly_name: 'Phone',
					enabled: false,
					signatureMethods: {
						sms: { enabled: true, label: 'SMS' },
					},
				},
			],
		})
	})

	function createWrapper() {
		return mount(IdentificationFactors, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				},
			},
		})
	}

	it('initializes the selected signature method from the enabled option', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.identifyMethods[0].signatureMethodEnabled).toBe('email')
		expect(wrapper.vm.identifyMethods[1].signatureMethodEnabled).toBe('sms')
	})

	it('persists only enabled methods and removes display labels before saving', () => {
		const wrapper = createWrapper()

		wrapper.vm.save()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledTimes(1)
		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith(
			'libresign',
			'identify_methods',
			JSON.stringify([
				{
					name: 'email',
					friendly_name: 'Email',
					enabled: true,
					can_create_account: true,
					signatureMethods: {
						sms: { enabled: false },
						email: { enabled: true },
					},
					signatureMethodEnabled: 'email',
				},
			]),
		)
	})
})
