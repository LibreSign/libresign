/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import Validation from '../../../views/Settings/Validation.vue'

const axiosGetMock = vi.fn()
const loadStateMock = vi.fn()
const fetchEffectivePoliciesMock = vi.fn(async () => {})
const getEffectiveValueMock = vi.fn(() => JSON.stringify({
	enabled: false,
	writeQrcodeOnFooter: true,
	validationSite: 'https://example.test/validation/',
	customizeFooterTemplate: true,
	footerTemplate: '',
	previewWidth: 595,
	previewHeight: 100,
	previewZoom: 100,
}))
const saveSystemPolicyMock = vi.fn(async () => ({ policyKey: 'add_footer' }))

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies: fetchEffectivePoliciesMock,
		getEffectiveValue: getEffectiveValueMock,
		saveSystemPolicy: saveSystemPolicyMock,
	}),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: unknown[]) => axiosGetMock(...args),
	},
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const appConfigSetValueMock = vi.fn()

const FooterTemplateEditorStub = {
	name: 'FooterTemplateEditor',
	props: {
		initialIsDefault: {
			type: Boolean,
			default: true,
		},
	},
	template: '<div class="footer-template-editor-stub" />',
	methods: {
		resetTemplateToDefault() {},
	},
}

describe('Settings/Validation.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
		vi.stubGlobal('OCP', {
			AppConfig: {
				setValue: appConfigSetValueMock,
			},
		})
	})

	function createWrapper() {
		return mount(Validation, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<section><slot /></section>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
					FooterTemplateEditor: FooterTemplateEditorStub,
				},
			},
		})
	}

	it('loads validation settings on mount', async () => {
		axiosGetMock
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		expect(axiosGetMock).toHaveBeenCalledTimes(1)
		expect(fetchEffectivePoliciesMock).toHaveBeenCalledTimes(1)
		expect(getEffectiveValueMock).toHaveBeenCalledWith('add_footer')
		expect(wrapper.vm.makeValidationUrlPrivate).toBe(true)
		expect(wrapper.vm.addFooter).toBe(false)
		expect(wrapper.vm.writeQrcodeOnFooter).toBe(true)
		expect(wrapper.vm.url).toBe('https://example.test/validation/')
		expect(wrapper.vm.isDefaultFooterTemplate).toBe(false)
		expect(wrapper.vm.customizeFooter).toBe(true)
	})

	it('falls back to the default validation URL placeholder', async () => {
		axiosGetMock.mockResolvedValue({ data: { ocs: { data: { data: '' } } } })
		getEffectiveValueMock.mockReturnValueOnce(JSON.stringify({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
			previewWidth: 595,
			previewHeight: 100,
			previewZoom: 100,
		}))

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.url).toBe(wrapper.vm.paternValidadeUrl)
	})

	it('trims and saves the typed validation URL', async () => {
		getEffectiveValueMock.mockReturnValueOnce(JSON.stringify({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: 'https://example.test/',
			customizeFooterTemplate: false,
			footerTemplate: '',
			previewWidth: 595,
			previewHeight: 100,
			previewZoom: 100,
		}))
		axiosGetMock
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		const input = wrapper.get('#validation_site')
		;(input.element as HTMLInputElement).value = '  https://custom.test/validation  '
		await input.trigger('input')

		expect(saveSystemPolicyMock).toHaveBeenCalled()
		const saveCall = saveSystemPolicyMock.mock.calls.at(-1) as [string, unknown, boolean] | undefined
		expect(saveCall?.[0]).toBe('add_footer')
		expect(saveCall?.[2]).toBe(false)
		const savedPayload = String(saveCall?.[1] ?? '')
		expect(savedPayload).toContain('https://custom.test/validation')
	})

	it('resets the footer template when customization is disabled', async () => {
		axiosGetMock
			.mockResolvedValue({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()
		wrapper.vm.addFooter = true
		wrapper.vm.customizeFooter = true
		await wrapper.vm.$nextTick()

		await wrapper.vm.onCustomizeFooterChange(false)

		expect(saveSystemPolicyMock).toHaveBeenCalled()
		const customizeCall = saveSystemPolicyMock.mock.calls.at(-1) as [string, unknown, boolean] | undefined
		expect(customizeCall?.[0]).toBe('add_footer')
		expect(wrapper.vm.isDefaultFooterTemplate).toBe(true)
	})

	it('persists the private validation URL toggle through the shared setter', async () => {
		axiosGetMock.mockResolvedValue({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		await wrapper.vm.onMakeValidationUrlPrivateChange(true)

		expect(appConfigSetValueMock).toHaveBeenCalledWith('libresign', 'make_validation_url_private', '1')
	})
})
