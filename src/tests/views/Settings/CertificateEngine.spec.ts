/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

import CertificateEngine from '../../../views/Settings/CertificateEngine.vue'

const loadStateMock = vi.fn()
const emitMock = vi.fn()
const saveCertificateEngineMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('@nextcloud/event-bus', () => ({
	emit: (...args: unknown[]) => emitMock(...args),
}))

vi.mock('../../../store/configureCheck.js', () => ({
	useConfigureCheckStore: vi.fn(() => ({
		saveCertificateEngine: (...args: unknown[]) => saveCertificateEngineMock(...args),
	})),
}))

describe('CertificateEngine.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
	})

	function createWrapper() {
		return mount(CertificateEngine, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcSelect: true,
				},
			},
		})
	}

	it('maps the initial state to the selected option', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'certificate_engine') return 'openssl'
			return fallback
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.selectedOption).toEqual({ id: 'openssl', label: 'OpenSSL' })
	})

	it('updates the selected engine through the computed setter', () => {
		const wrapper = createWrapper()

		wrapper.vm.selectedOption = { id: 'cfssl', label: 'CFSSL' }

		expect(wrapper.vm.selectedEngineId).toBe('cfssl')
	})

	it('emits the change event when the engine is saved successfully', async () => {
		saveCertificateEngineMock.mockResolvedValue({ success: true, engine: 'none' })
		const wrapper = createWrapper()

		await wrapper.vm.saveEngine({ id: 'none', label: 'I will not use root certificate' })

		expect(saveCertificateEngineMock).toHaveBeenCalledWith('none')
		expect(emitMock).toHaveBeenCalledWith('libresign:certificate-engine:changed', 'none')
	})

	it('does not emit the change event when saving fails', async () => {
		saveCertificateEngineMock.mockResolvedValue({ success: false, engine: 'openssl' })
		const wrapper = createWrapper()

		await wrapper.vm.saveEngine({ id: 'openssl', label: 'OpenSSL' })

		expect(emitMock).not.toHaveBeenCalled()
	})
})
