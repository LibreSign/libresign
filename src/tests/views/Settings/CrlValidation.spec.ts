/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

import CrlValidation from '../../../views/Settings/CrlValidation.vue'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

describe('CrlValidation.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
	})

	function createWrapper() {
		return mount(CrlValidation, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: true,
					NcNoteCard: true,
				},
			},
		})
	}

	it('loads both CRL flags from initial state', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'crl_external_validation_enabled') return false
			if (key === 'ldap_extension_available') return false
			return fallback
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.enabled).toBe(false)
		expect(wrapper.vm.ldapExtensionAvailable).toBe(false)
	})

	it('persists enabled CRL validation as 1', () => {
		const wrapper = createWrapper()

		wrapper.vm.enabled = true
		wrapper.vm.saveEnabled()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'crl_external_validation_enabled', '1')
	})

	it('persists disabled CRL validation as 0', () => {
		const wrapper = createWrapper()

		wrapper.vm.enabled = false
		wrapper.vm.saveEnabled()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'crl_external_validation_enabled', '0')
	})
})
