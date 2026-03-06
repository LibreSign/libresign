/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import RootCertificateCfssl from '../../../views/Settings/RootCertificateCfssl.vue'

const axiosGetMock = vi.fn()
const axiosPostMock = vi.fn()
const showErrorMock = vi.fn()
const loadStateMock = vi.fn()
const subscribeMock = vi.fn()
const unsubscribeMock = vi.fn()
const checkSetupMock = vi.fn()
const cfsslBinariesOkMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: unknown[]) => axiosGetMock(...args),
		post: (...args: unknown[]) => axiosPostMock(...args),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: (...args: unknown[]) => showErrorMock(...args),
}))

vi.mock('@nextcloud/event-bus', () => ({
	subscribe: (...args: unknown[]) => subscribeMock(...args),
	unsubscribe: (...args: unknown[]) => unsubscribeMock(...args),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string, vars?: Record<string, string>) => {
		if (!vars) {
			return text
		}
		return text.replace(/{(\w+)}/g, (_match, key) => String(vars[key]))
	}),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('../../../helpers/certification', () => ({
	selectCustonOption: vi.fn(() => ({
		unwrap: () => ({ label: 'Country' }),
	})),
}))

vi.mock('../../../logger.js', () => ({
	default: {
		debug: vi.fn(),
	},
}))

vi.mock('../../../store/configureCheck.js', () => ({
	useConfigureCheckStore: vi.fn(() => ({
		items: [{ resource: 'cfssl-configure', status: 'success' }],
		isConfigureOk: vi.fn(() => true),
		cfsslBinariesOk: (...args: unknown[]) => cfsslBinariesOkMock(...args),
		checkSetup: (...args: unknown[]) => checkSetupMock(...args),
	})),
}))

describe('RootCertificateCfssl.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		cfsslBinariesOkMock.mockReturnValue(true)
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						generated: false,
						rootCert: {
							commonName: '',
							names: [],
						},
						cfsslUri: '',
						configPath: '',
					},
				},
			},
		})
		axiosPostMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						data: {
							generated: true,
							rootCert: {
								commonName: 'LibreSign Root',
								names: [{ id: 'C', value: 'BR' }],
							},
							cfsslUri: 'https://cfssl.example.test',
							configPath: '/tmp/cfssl.json',
						},
					},
				},
			},
		})
	})

	function createWrapper() {
		return mount(RootCertificateCfssl, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<section><slot /></section>' },
					NcDialog: { template: '<div><slot /><slot name="actions" /></div>' },
					NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
					NcTextField: { template: '<input />' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
					CertificateCustonOptions: { template: '<div />' },
					CertificatePolicy: { template: '<div />' },
				},
			},
		})
	}

	it('loads the CFSSL engine state and root certificate on mount', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'certificate_engine') return 'cfssl'
			return fallback
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.isThisEngine).toBe(true)
		expect(cfsslBinariesOkMock).toHaveBeenCalled()
		expect(axiosGetMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/certificate')
		expect(wrapper.vm.description).toBe('To generate new signatures, you must first generate the root certificate.')
	})

	it('requires a valid certificate policy only when the toggle is enabled', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.formDisabled = false
		wrapper.vm.toggleCertificatePolicy = false
		expect(wrapper.vm.canSave).toBe(true)

		wrapper.vm.toggleCertificatePolicy = true
		wrapper.vm.certificatePolicyValid = false
		expect(wrapper.vm.canSave).toBe(false)

		wrapper.vm.certificatePolicyValid = true
		expect(wrapper.vm.canSave).toBe(true)
	})

	it('resets the form when clearing a generated certificate', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.certificate = {
			rootCert: {
				commonName: 'LibreSign Root',
				names: [{ id: 'C', value: 'BR' }],
			},
			cfsslUri: 'https://cfssl.example.test',
			configPath: '/tmp/cfssl.json',
		}
		wrapper.vm.customData = true
		wrapper.vm.formDisabled = true
		wrapper.vm.modal = true

		wrapper.vm.clearAndShowForm()

		expect(wrapper.vm.certificate.rootCert.commonName).toBe('')
		expect(wrapper.vm.certificate.rootCert.names).toEqual([])
		expect(wrapper.vm.certificate.cfsslUri).toBe('')
		expect(wrapper.vm.certificate.configPath).toBe('')
		expect(wrapper.vm.customData).toBe(false)
		expect(wrapper.vm.formDisabled).toBe(false)
		expect(wrapper.vm.modal).toBe(false)
	})

	it('updates visibility and reloads data when the certificate engine changes', async () => {
		const wrapper = createWrapper()
		await flushPromises()
		axiosGetMock.mockClear()

		wrapper.vm.changeEngine('openssl')
		await flushPromises()
		expect(wrapper.vm.isThisEngine).toBe(false)
		expect(axiosGetMock).not.toHaveBeenCalled()

		wrapper.vm.changeEngine('cfssl')
		await flushPromises()

		expect(wrapper.vm.isThisEngine).toBe(true)
		expect(axiosGetMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/certificate')
	})

	it('generates the certificate and refreshes setup checks on success', async () => {
		const wrapper = createWrapper()
		await flushPromises()
		wrapper.vm.certificate.rootCert.commonName = 'LibreSign Root'
		wrapper.vm.customData = true
		wrapper.vm.certificate.cfsslUri = 'https://cfssl.example.test'

		await wrapper.vm.generateCertificate()
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/admin/certificate/cfssl',
			expect.objectContaining({
				rootCert: expect.objectContaining({ commonName: 'LibreSign Root' }),
				cfsslUri: 'https://cfssl.example.test',
			}),
		)
		expect(wrapper.vm.certificate.generated).toBe(true)
		expect(wrapper.vm.submitLabel).toBe('Generated certificate!')
		expect(checkSetupMock).toHaveBeenCalledTimes(1)
	})

	it('shows a user-facing error when generation fails', async () => {
		axiosPostMock.mockRejectedValue({
			response: {
				data: {
					ocs: {
						data: {
							message: 'CFSSL error',
						},
					},
				},
			},
		})

		const wrapper = createWrapper()
		await flushPromises()

		await wrapper.vm.generateCertificate()
		await flushPromises()

		expect(showErrorMock).toHaveBeenCalledWith('Could not generate certificate.\nCFSSL error')
		expect(wrapper.vm.submitLabel).toBe('Generate root certificate')
	})
})