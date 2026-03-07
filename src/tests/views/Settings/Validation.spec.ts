/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import Validation from '../../../views/Settings/Validation.vue'

const axiosGetMock = vi.fn()
const loadStateMock = vi.fn()

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

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

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
		resetFooterTemplate() {},
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
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '0' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: 'https://example.test/validation/' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '0' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		expect(axiosGetMock).toHaveBeenCalledTimes(5)
		expect(wrapper.vm.makeValidationUrlPrivate).toBe(true)
		expect(wrapper.vm.addFooter).toBe(false)
		expect(wrapper.vm.writeQrcodeOnFooter).toBe(true)
		expect(wrapper.vm.url).toBe('https://example.test/validation/')
		expect(wrapper.vm.isDefaultFooterTemplate).toBe(false)
		expect(wrapper.vm.customizeFooter).toBe(true)
	})

	it('falls back to the default validation URL placeholder', async () => {
		axiosGetMock.mockResolvedValue({ data: { ocs: { data: { data: '' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.url).toBe(wrapper.vm.paternValidadeUrl)
	})

	it('trims and saves the typed validation URL', async () => {
		axiosGetMock
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: 'https://example.test/' } } } })
			.mockResolvedValueOnce({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		const input = wrapper.get('#validation_site')
		;(input.element as HTMLInputElement).value = '  https://custom.test/validation  '
		await input.trigger('input')

		expect(appConfigSetValueMock).toHaveBeenCalledWith('libresign', 'validation_site', 'https://custom.test/validation')
	})

	it('resets the footer template when customization is disabled', async () => {
		axiosGetMock.mockResolvedValue({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()
		wrapper.vm.addFooter = true
		wrapper.vm.customizeFooter = true
		await wrapper.vm.$nextTick()

		const footerEditor = wrapper.findComponent(FooterTemplateEditorStub)
		const resetFooterTemplateMock = vi.spyOn(footerEditor.vm, 'resetFooterTemplate')

		await wrapper.vm.onCustomizeFooterChange(false)

		expect(appConfigSetValueMock).toHaveBeenCalledWith('libresign', 'footer_template_is_default', '1')
		expect(wrapper.vm.isDefaultFooterTemplate).toBe(true)
		expect(resetFooterTemplateMock).toHaveBeenCalledTimes(1)
	})

	it('persists the private validation URL toggle through the shared setter', async () => {
		axiosGetMock.mockResolvedValue({ data: { ocs: { data: { data: '1' } } } })

		const wrapper = createWrapper()
		await flushPromises()

		await wrapper.vm.onMakeValidationUrlPrivateChange(true)

		expect(appConfigSetValueMock).toHaveBeenCalledWith('libresign', 'make_validation_url_private', '1')
	})
})
