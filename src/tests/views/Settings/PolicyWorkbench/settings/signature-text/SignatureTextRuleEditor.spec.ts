/* eslint-disable import/first */
/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
import { getDefaultSignatureTextPolicyConfig } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/model'
import type { EffectivePolicyValue } from '../../../../../../types/index'

describe('SignatureTextRuleEditor.vue', () => {
	const defaultConfig = getDefaultSignatureTextPolicyConfig()
	const inheritedConfig = {
		template: 'Inherited signature template',
		templateFontSize: 11,
		signatureFontSize: 13,
		signatureWidth: 180,
		signatureHeight: 72,
		backgroundType: 'deleted',
		renderMode: 'text',
	}
	const zoomStorageKey = 'libresign.policy.signatureStamp.previewZoom'
	const serializedInheritedValue = JSON.stringify({
		template: inheritedConfig.template,
		template_font_size: inheritedConfig.templateFontSize,
		signature_font_size: inheritedConfig.signatureFontSize,
		signature_width: inheritedConfig.signatureWidth,
		signature_height: inheritedConfig.signatureHeight,
		background_type: inheritedConfig.backgroundType,
		render_mode: inheritedConfig.renderMode,
	})

	beforeEach(() => {
		axiosPostMock.mockReset().mockResolvedValue({ data: new Blob(['preview'], { type: 'application/pdf' }) })
		ensurePdfWorkerMock.mockReset()
		window.localStorage.removeItem(zoomStorageKey)
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
			'/apps/libresign/api/v1/signature-stamp/preview-pdf',
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

	it('shows reset defaults action in preview controls', async () => {
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

		expect(wrapper.text()).toContain('Reset defaults')
	})

	it('allows entering zoom manually, applies it to PDF scale, and persists it to localStorage', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Zoom test',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 90,
					signature_height: 60,
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
				},
			},
		})

		await vi.advanceTimersByTimeAsync(250)
		await Promise.resolve()
		await Promise.resolve()

		const zoomInput = wrapper.find('.ste__zoom-input')
		expect(zoomInput.exists()).toBe(true)
		await zoomInput.setValue('255')
		await zoomInput.trigger('keydown.enter')

		const pdfElements = wrapper.findComponent({ name: 'PDFElements' })
		expect(pdfElements.props('initialScale')).toBe(2.55)
		expect(window.localStorage.getItem(zoomStorageKey)).toBe('255')
		expect((zoomInput.element as HTMLInputElement).value).toBe('255')
	})

	it('restores persisted zoom on mount', async () => {
		window.localStorage.setItem(zoomStorageKey, '310')

		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Persisted zoom',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 90,
					signature_height: 60,
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
				},
			},
		})

		const zoomInput = wrapper.find('.ste__zoom-input')
		expect((zoomInput.element as HTMLInputElement).value).toBe('310')
	})

	it('synchronizes internal state when modelValue changes', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue(defaultConfig),
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
				},
			},
		})

		await wrapper.setProps({
			modelValue: asModelValue({
				template: 'Updated from parent',
				template_font_size: 11,
				signature_font_size: 12,
				signature_width: 222,
				signature_height: 77,
				background_type: 'deleted',
				render_mode: 'text',
			}),
		})

		expect((wrapper.find('textarea.ste__textarea').element as HTMLTextAreaElement).value).toBe('Updated from parent')

		const numericInputs = wrapper.findAll('input.ste__num-input')
		expect((numericInputs.at(0)!.element as HTMLInputElement).value).toBe('11')
		expect((numericInputs.at(1)!.element as HTMLInputElement).value).toBe('12')
		expect((numericInputs.at(2)!.element as HTMLInputElement).value).toBe('222')
		expect((numericInputs.at(3)!.element as HTMLInputElement).value).toBe('77')
		expect(wrapper.findAll('.ste__seg--background .ste__seg-btn').find((button) => button.text() === 'None')?.classes()).toContain('ste__seg-btn--active')
	})

	it('resets width to the inherited policy value', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Reset baseline test',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const widthField = wrapper.findAll('.ste__field').find((field) => field.text().includes('Width'))
		expect(widthField).toBeDefined()

		const widthInput = widthField!.find('input.ste__num-input')
		await widthInput.setValue('444')
		expect((widthInput.element as HTMLInputElement).value).toBe('444')

		await widthField!.find('.button-stub').trigger('click')
		expect((widthInput.element as HTMLInputElement).value).toBe(String(inheritedConfig.signatureWidth))
	})

	it('resets text font to the inherited policy value', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Reset font baseline',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const textFontField = wrapper.findAll('.ste__field').find((field) => field.text().includes('Text font'))
		expect(textFontField).toBeDefined()

		const textFontInput = textFontField!.find('input.ste__num-input')
		await textFontInput.setValue('18')
		await textFontField!.find('.button-stub').trigger('click')

		expect((textFontInput.element as HTMLInputElement).value).toBe(String(inheritedConfig.templateFontSize))
	})

	it('resets template to the inherited policy value', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Assinado com LibreSign\n{{SignerCommonName}}',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const templateInput = wrapper.find('textarea.ste__textarea')
		expect(templateInput.exists()).toBe(true)

		await templateInput.setValue('Template alterado')
		expect((templateInput.element as HTMLTextAreaElement).value).toBe('Template alterado')

		const templateField = wrapper.findAll('.ste__group').find((group) => group.find('textarea.ste__textarea').exists())
		expect(templateField).toBeDefined()

		const resetButton = templateField!.findAll('.button-stub').at(-1)
		expect(resetButton).toBeDefined()
		await resetButton!.trigger('click')

		expect((templateInput.element as HTMLTextAreaElement).value).toBe(inheritedConfig.template)
	})

	it('resets signer font to the inherited policy value in text mode', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Reset signer font baseline',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'text',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const signerFontField = wrapper.findAll('.ste__field').find((field) => field.text().includes('Sig font'))
		expect(signerFontField).toBeDefined()

		const signerFontInput = signerFontField!.find('input.ste__num-input')
		await signerFontInput.setValue('19')
		await signerFontField!.find('.button-stub').trigger('click')

		expect((signerFontInput.element as HTMLInputElement).value).toBe(String(inheritedConfig.signatureFontSize))
	})

	it('resets height to the inherited policy value', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Reset height baseline',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const heightField = wrapper.findAll('.ste__field').find((field) => field.text().includes('Height'))
		expect(heightField).toBeDefined()

		const heightInput = heightField!.find('input.ste__num-input')
		await heightInput.setValue('222')
		expect((heightInput.element as HTMLInputElement).value).toBe('222')

		await heightField!.find('.button-stub').trigger('click')
		expect((heightInput.element as HTMLInputElement).value).toBe(String(inheritedConfig.signatureHeight))
	})

	it('resets background to the inherited policy value', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Loaded custom template',
					template_font_size: 13,
					signature_font_size: 15,
					signature_width: 350,
					signature_height: 100,
					background_type: 'custom',
					render_mode: 'text',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const resetBackgroundButton = wrapper.find('button[aria-label="Reset background to default"]')
		expect(resetBackgroundButton).toBeDefined()

		await resetBackgroundButton.trigger('click')

		const updates = wrapper.emitted('update:modelValue')
		expect(updates?.at(-1)?.[0]).toContain(`"background_type":"${inheritedConfig.backgroundType}"`)
	})

	it('resets all signature stamp settings to the inherited policy value', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Loaded custom template',
					template_font_size: 13,
					signature_font_size: 15,
					signature_width: 350,
					signature_height: 100,
					background_type: 'deleted',
					render_mode: 'text',
				}),
				inheritedValue: serializedInheritedValue,
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
				},
			},
		})

		const resetDefaultsButton = wrapper.findAll('.button-stub').find((button) => button.text().includes('Reset defaults'))
		expect(resetDefaultsButton).toBeDefined()

		await resetDefaultsButton!.trigger('click')

		const updates = wrapper.emitted('update:modelValue')
		expect(updates?.at(-1)?.[0]).toBe(JSON.stringify({
			template: inheritedConfig.template,
			template_font_size: inheritedConfig.templateFontSize,
			signature_font_size: inheritedConfig.signatureFontSize,
			signature_width: inheritedConfig.signatureWidth,
			signature_height: inheritedConfig.signatureHeight,
			background_type: inheritedConfig.backgroundType,
			render_mode: inheritedConfig.renderMode,
		}))
	})

	it('falls back to instance defaults when no inherited value is provided', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Loaded custom template',
					template_font_size: 13,
					signature_font_size: 15,
					signature_width: 350,
					signature_height: 100,
					background_type: 'deleted',
					render_mode: 'text',
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
				},
			},
		})

		const resetDefaultsButton = wrapper.findAll('.button-stub').find((button) => button.text().includes('Reset defaults'))
		expect(resetDefaultsButton).toBeDefined()

		await resetDefaultsButton!.trigger('click')

		const updates = wrapper.emitted('update:modelValue')
		expect(updates?.at(-1)?.[0]).toBe(JSON.stringify({
			template: defaultConfig.template,
			template_font_size: defaultConfig.templateFontSize,
			signature_font_size: defaultConfig.signatureFontSize,
			signature_width: defaultConfig.signatureWidth,
			signature_height: defaultConfig.signatureHeight,
			background_type: defaultConfig.backgroundType,
			render_mode: defaultConfig.renderMode,
		}))
	})
})
