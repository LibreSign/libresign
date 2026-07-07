/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import SignatureTextPreviewSection from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/SignatureTextPreviewSection.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

const globalStubs = {
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'ariaLabel'],
		template: '<button class="nc-button-stub" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
		emits: ['click'],
	},
	NcIconSvgWrapper: true,
	NcLoadingIcon: { template: '<div class="loading-stub" />' },
	PdfElements: { template: '<div class="pdf-elements-stub" />' },
}

const baseProps = {
	id: 'preview',
	signatureWidth: 350,
	signatureHeight: 100,
	previewZoomInput: '100',
	previewFrameStyle: { width: '350px', height: '100px' },
	previewScale: 1,
	pdfPreviewFile: null,
	previewLoading: false,
	previewError: '',
	previewRenderKey: 'key-1',
}

describe('SignatureTextPreviewSection.vue', () => {
	it('shows the empty preview placeholder when no PDF preview is available', () => {
		const wrapper = mount(SignatureTextPreviewSection, {
			props: baseProps,
			global: { stubs: globalStubs },
		})

		expect(wrapper.text()).toContain('Preview will appear here')
	})

	it('shows the error placeholder when preview generation fails', () => {
		const wrapper = mount(SignatureTextPreviewSection, {
			props: {
				...baseProps,
				previewError: 'Preview failed',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.text()).toContain('Preview failed')
	})

	it('emits zoom and reset actions from the preview toolbar', async () => {
		const wrapper = mount(SignatureTextPreviewSection, {
			props: baseProps,
			global: { stubs: globalStubs },
		})

		const buttons = wrapper.findAll('.nc-button-stub')
		await buttons[0]?.trigger('click')
		await wrapper.find('.ste__zoom-btn').trigger('click')
		await wrapper.findAll('.ste__zoom-btn')[1]?.trigger('click')

		expect(wrapper.emitted('reset-defaults')).toBeTruthy()
		expect(wrapper.emitted('change-zoom')?.[0]?.[0]).toBe(-10)
		expect(wrapper.emitted('change-zoom')?.[1]?.[0]).toBe(10)
	})

	it('renders the PDF preview stub and emits zoom input lifecycle events', async () => {
		const file = new File(['pdf'], 'preview.pdf', { type: 'application/pdf' })
		const wrapper = mount(SignatureTextPreviewSection, {
			props: {
				...baseProps,
				pdfPreviewFile: file,
			},
			global: { stubs: globalStubs },
		})

		const input = wrapper.find('.ste__zoom-input')
		await input.setValue('125')
		await input.trigger('input')
		await input.trigger('blur')

		expect(wrapper.emitted('zoom-input')).toBeTruthy()
		expect(wrapper.emitted('commit-zoom-input')).toBeTruthy()
	})
})
