/* eslint-disable import/first */
/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const axiosPostMock = vi.fn()
const ensurePdfWorkerMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: (...args: unknown[]) => axiosPostMock(...args),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string) => path,
}))

vi.mock('../../../../../../helpers/pdfWorker', () => ({
	ensurePdfWorker: () => ensurePdfWorkerMock(),
}))

vi.mock('@libresign/pdf-elements', () => ({
	default: {
		name: 'PDFElements',
		props: ['initFiles', 'initFileNames', 'initialScale', 'showPageFooter'],
		template: '<div class="pdf-elements-stub" />',
	},
}))

import SignatureTextRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/SignatureTextRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../../../types/index'

describe('SignatureTextRuleEditor.vue', () => {
	beforeEach(() => {
		axiosPostMock.mockReset().mockResolvedValue({ data: new Blob(['preview'], { type: 'application/pdf' }) })
		ensurePdfWorkerMock.mockReset()
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	const asModelValue = (value: Record<string, unknown>): EffectivePolicyValue => JSON.stringify(value)

	it('requests the pdf preview endpoint and renders it with pdf-elements', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Preview {{SignerCommonName}}',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
			},
			global: {
				stubs: {
					NcButton: {
						name: 'NcButton',
						emits: ['click'],
						template: '<button class="button-stub" @click="$emit(\'click\')"><slot /></button>',
					},
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						template: '<span class="icon-stub" />',
					},
					NcLoadingIcon: {
						name: 'NcLoadingIcon',
						template: '<span class="loading-stub" />',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
					},
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
					},
				},
			},
		})

		await vi.advanceTimersByTimeAsync(250)
		await Promise.resolve()
		await Promise.resolve()

		expect(axiosPostMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/admin/signature-stamp/preview-pdf',
			expect.objectContaining({
				template: 'Preview {{SignerCommonName}}',
				templateFontSize: 9,
				signatureFontSize: 9,
				signatureWidth: 350,
				signatureHeight: 100,
				backgroundType: 'default',
				renderMode: 'default',
			}),
			expect.objectContaining({ responseType: 'blob' }),
		)
		expect(wrapper.find('.pdf-elements-stub').exists()).toBe(true)
		expect(wrapper.find('img').exists()).toBe(false)
	})

	it('keeps the template reset button visible once the template is customized', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Custom template',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
			},
			global: {
				stubs: {
					NcButton: {
						name: 'NcButton',
						emits: ['click'],
						template: '<button class="button-stub" @click="$emit(\'click\')"><slot /></button>',
					},
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						template: '<span class="icon-stub" />',
					},
					NcLoadingIcon: {
						name: 'NcLoadingIcon',
						template: '<span class="loading-stub" />',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
					},
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><button class="label-action-stub"><slot name="label-actions" /></button><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Reset to default')
	})
})