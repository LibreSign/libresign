/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'

import { getDefaultSignatureTextPolicyConfig } from '@/views/Settings/PolicyWorkbench/settings/signature-text/model'

import SignatureTextRuleEditor from '@/views/Settings/PolicyWorkbench/settings/signature-text/SignatureTextRuleEditor.vue'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue: unknown) => {
		if (key === 'effective_policies') {
			return {
				policies: {
					signature_stamp: {
						meta: {
							defaultSystemValue: JSON.stringify({
								template: 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}',
								template_font_size: 9.8,
								signature_font_size: 20,
								signature_width: 350,
								signature_height: 100,
								background_type: 'default',
								render_mode: 'default',
							}),
						},
					},
				},
			}
		}

		return defaultValue
	}),
}))

const { pdfElementsAutoReady } = vi.hoisted(() => ({
	pdfElementsAutoReady: {
		enabled: true,
	},
}))

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
	default: defineComponent({
		name: 'PDFElements',
		props: ['initFiles', 'initFileNames', 'initialScale', 'showPageFooter'],
		emits: ['pdf-elements:end-init'],
		setup(_props, { emit }) {
			if (pdfElementsAutoReady.enabled) {
				queueMicrotask(() => emit('pdf-elements:end-init', { docsCount: 1 }))
			}

			return () => h('div', { class: 'pdf-elements-stub' })
		},
	}),
}))

vi.mock('../../../../../../components/CodeEditor.vue', () => ({
	default: {
		name: 'CodeEditor',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<div class="code-editor-wrapper-stub"><slot name="label-actions" /><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
	},
}))

describe('SignatureTextRuleEditor.vue', () => {
	const defaultConfig = getDefaultSignatureTextPolicyConfig()
	const defaultTemplate = 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}'
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
		pdfElementsAutoReady.enabled = true
		window.localStorage.removeItem(zoomStorageKey)
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	const asModelValue = (value: object): string => JSON.stringify(value)

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

		expect(ensurePdfWorkerMock).toHaveBeenCalled()

		expect(axiosPostMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/signature-stamp/preview-pdf',
			expect.objectContaining({
				template: 'Preview {{SignerCommonName}}',
				templateFontSize: 9,
				signatureFontSize: 9,
				signatureWidth: 350,
				signatureHeight: 100,
				backgroundType: 'default',
				renderMode: 'GRAPHIC_AND_DESCRIPTION',
			}),
			expect.objectContaining({ responseType: 'blob' }),
		)
		expect(wrapper.find('.pdf-elements-stub').exists()).toBe(true)
		expect(wrapper.find('img').exists()).toBe(false)
	})

	it('shows a preview error message when preview request fails', async () => {
		axiosPostMock.mockRejectedValueOnce(new Error('Preview failed'))

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
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						template: '<span class="icon-stub" />',
					},
				},
			},
		})

		await vi.advanceTimersByTimeAsync(250)
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Unable to load preview. Please check the template and try again.')
		expect(wrapper.find('.pdf-elements-stub').exists()).toBe(false)
	})

	it('keeps a loading overlay visible until the pdf preview component finishes initializing', async () => {
		pdfElementsAutoReady.enabled = false

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

		expect(wrapper.find('.pdf-elements-stub').exists()).toBe(true)
		expect(wrapper.find('.ste__preview-loading-overlay').exists()).toBe(true)
		expect(wrapper.find('.loading-stub').exists()).toBe(true)

		wrapper.findComponent({ name: 'PDFElements' }).vm.$emit('pdf-elements:end-init', { docsCount: 1 })
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.find('.ste__preview-loading-overlay').exists()).toBe(false)
	})

	it('hydrates the canonical default template when the incoming draft is empty', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: '',
			},
			global: {
				stubs: {
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						template: '<span class="icon-stub" />',
					},
				},
			},
		})

		await vi.advanceTimersByTimeAsync(250)
		await Promise.resolve()
		await Promise.resolve()

		expect((wrapper.find('textarea.code-editor-stub').element as HTMLTextAreaElement).value).toBe(defaultTemplate)
		expect(axiosPostMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/signature-stamp/preview-pdf',
			expect.objectContaining({
				template: defaultTemplate,
			}),
			expect.objectContaining({ responseType: 'blob' }),
		)
	})

	it('hides reset actions while the rule matches the inherited defaults', async () => {
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

		await vi.advanceTimersByTimeAsync(250)
		await Promise.resolve()
		await Promise.resolve()

		const resetButtons = wrapper.findAll('.button-stub').filter((button) => /Reset to default|Reset defaults|Undo/.test(button.text()))
		expect(resetButtons).toHaveLength(0)
	})

	it('shows render mode reset when mode changes and hides it after undo', async () => {
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

		const modeButtons = wrapper.findAll('.ste__seg--modes .ste__seg-btn')
		const graphicButton = modeButtons.find((button) => button.text().includes('Signature only'))
		expect(graphicButton).toBeDefined()
		await graphicButton!.trigger('click')

		const undoButtons = wrapper.findAll('.button-stub').filter((button) => button.text().includes('Undo'))
		expect(undoButtons.length).toBeGreaterThan(0)

		await undoButtons[0].trigger('click')
		expect(wrapper.findAll('.button-stub').filter((button) => button.text().includes('Undo'))).toHaveLength(0)
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
				inheritedValue: asModelValue({
					template: 'Inherited template',
					template_font_size: 9.8,
					signature_font_size: 9.8,
					signature_width: 250,
					signature_height: 100,
					background_type: 'none',
					render_mode: 'description_only',
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

	it('updates template placeholders when collect metadata toggle changes', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: asModelValue({
					template: 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}',
					template_font_size: 9,
					signature_font_size: 9,
					signature_width: 350,
					signature_height: 100,
					background_type: 'default',
					render_mode: 'default',
				}),
				collectMetadataEnabled: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<label><input class="collect-metadata-switch" type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
					},
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

		await wrapper.find('.collect-metadata-switch').setValue(true)

		const updates = wrapper.emitted('update:modelValue')
		expect(updates).toBeTruthy()
		const lastPayload = updates!.at(-1)![0] as { signatureStampValue: string; collectMetadataEnabled: boolean }
		expect(lastPayload.collectMetadataEnabled).toBe(true)
		expect(lastPayload.signatureStampValue).toContain('{{SignerIP}}')
		expect(lastPayload.signatureStampValue).toContain('{{SignerUserAgent}}')

		await wrapper.find('.collect-metadata-switch').setValue(false)

		const updatesAfterDisable = wrapper.emitted('update:modelValue')
		const disabledPayload = updatesAfterDisable!.at(-1)![0] as { signatureStampValue: string; collectMetadataEnabled: boolean }
		expect(disabledPayload.collectMetadataEnabled).toBe(false)
		expect(disabledPayload.signatureStampValue).not.toContain('{{SignerIP}}')
		expect(disabledPayload.signatureStampValue).not.toContain('{{SignerUserAgent}}')
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

		expect((wrapper.find('textarea.code-editor-stub').element as HTMLTextAreaElement).value).toBe('Updated from parent')

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

		const templateInput = wrapper.find('textarea.code-editor-stub')
		expect(templateInput.exists()).toBe(true)

		await templateInput.setValue('Template alterado')
		expect((templateInput.element as HTMLTextAreaElement).value).toBe('Template alterado')

		const templateField = wrapper.findAll('.ste__group').find((group) => group.find('textarea.code-editor-stub').exists())
		expect(templateField).toBeDefined()

		const resetButton = templateField!.findAll('.button-stub').at(-1)
		expect(resetButton).toBeDefined()
		await resetButton!.trigger('click')

		expect((templateInput.element as HTMLTextAreaElement).value).toBe(inheritedConfig.template)
		expect(templateField!.findAll('.button-stub').filter((button) => button.text().includes('Undo'))).toHaveLength(0)
	})

	it('shows template reset action when composite draft has empty template and inherited template is non-empty', async () => {
		const wrapper = mount(SignatureTextRuleEditor, {
			props: {
				modelValue: {
					signatureStampValue: asModelValue({
						template: '',
						template_font_size: 9.8,
						signature_font_size: 9.8,
						signature_width: 350,
						signature_height: 100,
						background_type: 'default',
						render_mode: 'default',
					}),
					collectMetadataEnabled: false,
				},
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

		const templateInput = wrapper.find('textarea.code-editor-stub')
		expect(templateInput.exists()).toBe(true)
		expect((templateInput.element as HTMLTextAreaElement).value).toBe('')

		const templateResetButton = wrapper.find('button[aria-label="Reset to default"]')
		expect(templateResetButton.exists()).toBe(true)
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
		await Promise.resolve()

		const updates = wrapper.emitted('update:modelValue')
		expect(updates).toBeTruthy()
		const payload = updates!.at(-1)![0] as { signatureStampValue: string; collectMetadataEnabled: boolean }
		expect(payload.collectMetadataEnabled).toBe(false)
		expect(payload.signatureStampValue).toContain(`"background_type":"${inheritedConfig.backgroundType}"`)
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
		await Promise.resolve()

		const updates = wrapper.emitted('update:modelValue')
		expect(updates).toBeTruthy()
		const payload = updates!.at(-1)![0] as { signatureStampValue: string; collectMetadataEnabled: boolean }
		expect(payload.collectMetadataEnabled).toBe(false)
		expect(payload.signatureStampValue).toBe(JSON.stringify({
			template: inheritedConfig.template,
			template_font_size: inheritedConfig.templateFontSize,
			signature_font_size: inheritedConfig.signatureFontSize,
			signature_width: inheritedConfig.signatureWidth,
			signature_height: inheritedConfig.signatureHeight,
			background_type: inheritedConfig.backgroundType,
			render_mode: inheritedConfig.renderMode,
		}))
	})

	it('uses initial editor value as baseline when no inherited value is provided', async () => {
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
		expect(resetDefaultsButton).toBeUndefined()
	})
})
