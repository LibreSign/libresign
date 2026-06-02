/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import FooterTemplateEditor from '../../components/FooterTemplateEditor.vue'

const axiosGetMock = vi.fn()
const axiosPostMock = vi.fn()
const ensurePdfWorkerMock = vi.fn()

const appConfigMock = {
	deleteKey: vi.fn(),
	setValue: vi.fn(),
}

const clipboardWriteTextMock = vi.fn()

vi.mock('debounce', () => ({
	default: vi.fn((fn: (...args: unknown[]) => unknown) => fn),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn((...args: unknown[]) => axiosGetMock(...args)),
		post: vi.fn((...args: unknown[]) => axiosPostMock(...args)),
	},
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue: unknown) => {
		if (key === 'footer_preview_zoom_level') {
			return 100
		}
		if (key === 'footer_template_variables') {
			return {
				signerName: { description: 'Signer', type: 'string', example: 'Alice' },
			}
		}
		return defaultValue
	}),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('../../helpers/pdfWorker', () => ({
	ensurePdfWorker: vi.fn(() => ensurePdfWorkerMock()),
}))

vi.mock('@libresign/pdf-elements', () => ({
	default: {
		name: 'PDFElements',
		props: ['initialScale'],
		template: '<div class="pdf-elements-stub" />',
	},
}))

vi.mock('../../components/CodeEditor.vue', () => ({
	default: {
		name: 'CodeEditor',
		props: ['modelValue', 'label', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<textarea class="code-editor-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button class="nc-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		template: '<div class="nc-dialog-stub"><slot /><slot name="actions" /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcFormBoxButton', () => ({
	default: {
		name: 'NcFormBoxButton',
		emits: ['click'],
		template: '<button class="nc-form-box-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /><slot name="description" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: {
		name: 'NcLoadingIcon',
		template: '<span class="nc-loading-icon-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue', 'input'],
		template: '<input class="nc-text-field-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<i class="nc-icon-svg-wrapper-stub" />',
	},
}))

describe('FooterTemplateEditor.vue', () => {
	const createWrapper = () => mount(FooterTemplateEditor, {
		global: {
			directives: {
				linkify: {
					mounted: () => undefined,
					updated: () => undefined,
				},
			},
		},
	})

	beforeEach(() => {
		axiosGetMock.mockReset()
		axiosPostMock.mockReset()
		ensurePdfWorkerMock.mockReset()
		appConfigMock.deleteKey.mockReset()
		appConfigMock.setValue.mockReset()
		clipboardWriteTextMock.mockReset()

		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						template: 'Footer {{ signerName }}',
						preview_height: 120,
						preview_width: 640,
					},
				},
			},
		})
		axiosPostMock.mockResolvedValue({ data: new Blob(['pdf'], { type: 'application/pdf' }) })

		vi.stubGlobal('OCP', { AppConfig: appConfigMock })
		vi.stubGlobal('navigator', {
			clipboard: {
				writeText: clipboardWriteTextMock,
			},
		})
	})

	it('loads the saved footer template on mount and initializes the PDF worker', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(ensurePdfWorkerMock).toHaveBeenCalledTimes(1)
		expect(axiosGetMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/admin/footer-template')
		expect(wrapper.vm.footerTemplate).toBe('Footer {{ signerName }}')
		expect(wrapper.vm.previewWidth).toBe(640)
		expect(wrapper.vm.previewHeight).toBe(120)
	})

	it('copies template variables to the clipboard and marks them as copied', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.copyToClipboard('{{ signerName }}')

		expect(clipboardWriteTextMock).toHaveBeenCalledWith('{{ signerName }}')
		expect(wrapper.vm.isCopied('signerName')).toBe(true)
	})

	it('resets dimensions and clears the stored app config values', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.previewWidth = 700
		wrapper.vm.previewHeight = 150
		wrapper.vm.resetDimensions()

		expect(wrapper.vm.previewWidth).toBe(wrapper.vm.DEFAULT_PREVIEW_WIDTH)
		expect(wrapper.vm.previewHeight).toBe(wrapper.vm.DEFAULT_PREVIEW_HEIGHT)
		expect(appConfigMock.deleteKey).toHaveBeenCalledWith('libresign', 'footer_preview_width')
		expect(appConfigMock.deleteKey).toHaveBeenCalledWith('libresign', 'footer_preview_height')
	})

	it('updates zoom level through the zoom controls logic', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.changeZoomLevel(20)

		expect(wrapper.vm.zoomLevel).toBe(120)
	})
})
