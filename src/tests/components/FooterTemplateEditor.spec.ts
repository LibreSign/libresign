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
		props: ['name', 'open', 'size'],
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
		expect(wrapper.getComponent({ name: 'NcDialog' }).props('size')).toBe('normal')
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

		wrapper.vm.previewWidth = 800
		wrapper.vm.previewHeight = 150
		await wrapper.vm.resetDimensions()

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

	it('updates PDF scale when zoom input changes', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		const previewRef = { scale: 1 }
		wrapper.vm.pdfPreview = previewRef
		wrapper.vm.zoomLevel = 140
		wrapper.vm.onZoomInput()

		expect(previewRef.scale).toBe(1.4)
	})

	it('persists custom dimensions and requests a new preview PDF', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.footerTemplate = 'Custom footer'
		wrapper.vm.previewWidth = 700
		wrapper.vm.previewHeight = 150

		wrapper.vm.saveDimensions()
		await flushPromises()

		expect(appConfigMock.setValue).toHaveBeenCalledWith('libresign', 'footer_preview_width', 700)
		expect(appConfigMock.setValue).toHaveBeenCalledWith('libresign', 'footer_preview_height', 150)
		expect(axiosPostMock).toHaveBeenLastCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/admin/footer-template',
			{
				template: 'Custom footer',
				width: 700,
				height: 150,
			},
			{ responseType: 'blob' },
		)
	})

	it('marks preview as loading and updates preview file when saving template', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.footerTemplate = 'Updated template'
		wrapper.vm.previewWidth = 595
		wrapper.vm.previewHeight = 100

		wrapper.vm.saveFooterTemplate()
		await flushPromises()

		expect(wrapper.vm.loadingPreview).toBe(true)
		expect(wrapper.vm.pdfPreviewFile).toBeInstanceOf(File)
		expect(axiosPostMock).toHaveBeenLastCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/admin/footer-template',
			{
				template: 'Updated template',
				width: 595,
				height: 100,
			},
			{ responseType: 'blob' },
		)
	})

	it('clears loading state when PDF preview initialization completes', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.loadingPreview = true
		wrapper.vm.containerHeight = 200

		wrapper.vm.onPdfReady()

		expect(wrapper.vm.loadingPreview).toBe(false)
		expect(wrapper.vm.containerHeight).toBe(null)
	})

	it('binds a non-zero min-height to the preview container before PDF initialization completes', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.pdfPreviewFile = new File(['pdf'], 'preview.pdf', { type: 'application/pdf' })
		await wrapper.vm.$nextTick()

		const previewContainer = wrapper.find('.footer-preview__pdf')
		expect(previewContainer.exists()).toBe(true)
		const minHeight = parseInt((previewContainer.element as HTMLElement).style.minHeight, 10)
		expect(minHeight).toBeGreaterThan(0)
	})

	describe('previewContainerMinHeight', () => {
		it.each([
			// containerHeight set → returned directly, previewHeight/zoomLevel irrelevant
			{ description: 'uses containerHeight directly when it is positive', containerHeight: 350, previewHeight: 120, zoomLevel: 100, expected: 350 },
			// invalid previewHeight → falls back to 160 floor
			{ description: 'returns the floor value when height is invalid', containerHeight: null, previewHeight: 0, zoomLevel: 100, expected: 160 },
			// 100 * 100 / 100 + 24 = 124 → clamped to 160 floor
			{ description: 'returns the floor value when the formula result is below the minimum', containerHeight: null, previewHeight: 100, zoomLevel: 100, expected: 160 },
			// 250 * 100 / 100 + 24 = 274 → above floor
			{ description: 'returns a value above the floor for larger dimensions', containerHeight: null, previewHeight: 250, zoomLevel: 100, expected: 274 },
			// 250 * 200 / 100 + 24 = 524 → grows with zoom
			{ description: 'grows proportionally with zoom level', containerHeight: null, previewHeight: 250, zoomLevel: 200, expected: 524 },
		])('$description', async ({ containerHeight, previewHeight, zoomLevel, expected }) => {
			const wrapper = createWrapper()
			await flushPromises()

			wrapper.vm.containerHeight = containerHeight
			wrapper.vm.previewHeight = previewHeight
			wrapper.vm.zoomLevel = zoomLevel
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.previewContainerMinHeight).toBe(expected)
		})
	})
})
