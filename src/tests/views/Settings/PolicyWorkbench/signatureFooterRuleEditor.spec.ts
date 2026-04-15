/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineComponent, nextTick } from 'vue'
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
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

vi.mock('../../../../helpers/pdfWorker', () => ({
	ensurePdfWorker: () => ensurePdfWorkerMock(),
}))

vi.mock('@libresign/pdf-elements', () => ({
	default: {
		name: 'PDFElements',
		props: ['initFiles', 'initFileNames', 'initialScale', 'showPageFooter'],
		template: '<div class="pdf-elements-stub" />',
	},
}))

import SignatureFooterRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/signature-footer/SignatureFooterRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../types/index'

describe('SignatureFooterRuleEditor.vue', () => {
	beforeEach(() => {
		axiosPostMock.mockReset().mockResolvedValue({ data: new Blob(['preview'], { type: 'application/pdf' }) })
		ensurePdfWorkerMock.mockReset()
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	const asModelValue = (value: Record<string, unknown>): EffectivePolicyValue => JSON.stringify(value)

	const parseEmittedValue = (value: unknown): Record<string, unknown> => {
		if (typeof value === 'string') {
			return JSON.parse(value) as Record<string, unknown>
		}

		return value as Record<string, unknown>
	}

	const createWrapper = (modelValue: EffectivePolicyValue, inheritedTemplate = '') => mount(SignatureFooterRuleEditor, {
		props: { modelValue, inheritedTemplate, showPreview: false },
		global: {
			stubs: {
				NcCheckboxRadioSwitch: {
					name: 'NcCheckboxRadioSwitch',
					props: ['modelValue'],
					emits: ['update:modelValue'],
					template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
				},
				NcTextField: {
					name: 'NcTextField',
					props: ['modelValue'],
					emits: ['update:modelValue'],
					template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
				CodeEditor: {
					name: 'CodeEditor',
					props: ['modelValue'],
					emits: ['update:modelValue'],
					template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
				},
			},
		},
	})

	it('disables QR code when footer is disabled', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
		}))

		await wrapper.find('.switch-stub').trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			enabled: false,
			writeQrcodeOnFooter: false,
		})
	})

	it('trims validation URL values before emitting update', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
		}))

		const input = wrapper.find('.text-field-stub')
		await input.setValue('  https://example.com/validate  ')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			validationSite: 'https://example.com/validate',
		})
	})

	it('hides validation URL field in user scope and sanitizes validationSite in emitted payload', async () => {
		const wrapper = mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: true,
					validationSite: 'https://internal.example/validation',
					customizeFooterTemplate: false,
					footerTemplate: '',
				}),
				editorScope: 'user',
				allowValidationSiteOverrideInUserScope: false,
				showPreview: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		expect(wrapper.find('.text-field-stub').exists()).toBe(false)

		await wrapper.findAll('.switch-stub')[1].trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			validationSite: '',
		})
	})

	it('keeps customize flag enabled and clears template when reset button is clicked', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
			footerTemplate: 'Custom footer',
		}))

		await wrapper.find('.button-stub').trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			customizeFooterTemplate: true,
			footerTemplate: '',
		})
	})

	it('hides template reset button when showTemplateResetButton is false', async () => {
		const wrapper = mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: true,
					validationSite: '',
					customizeFooterTemplate: true,
					footerTemplate: 'Custom footer',
				}),
				inheritedTemplate: 'Inherited footer',
				showTemplateResetButton: false,
				showPreview: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		await nextTick()
		expect(wrapper.find('.code-editor-header-stub .button-stub').exists()).toBe(false)
	})

	it('forwards template-changed event when template content changes', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
			footerTemplate: '',
		}))

		await wrapper.find('.code-editor-stub').setValue('Template from user')

		expect(wrapper.emitted('template-changed')).toBeTruthy()
	})

	it('can disable customize toggle after enabling it without stale overwrite', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
			footerTemplate: 'Custom footer',
		}))

		await wrapper.findAll('.switch-stub')[2].trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			customizeFooterTemplate: false,
			footerTemplate: '',
		})
	})

	it('hides reset button when customize is enabled but template is inherited (not modified)', () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
			footerTemplate: '',
		}), 'Inherited footer template')

		expect(wrapper.find('.button-stub').exists()).toBe(false)
	})

	it('shows reset button when customize is enabled with modified template', () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
			footerTemplate: 'Custom footer',
		}), 'Inherited footer template')

		expect(wrapper.find('.button-stub').exists()).toBe(true)
	})

	it('hides reset button when current template equals inherited template', () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
			footerTemplate: 'Inherited footer template',
		}), 'Inherited footer template')

		expect(wrapper.find('.button-stub').exists()).toBe(false)
	})

	it('hides reset button in user scope when current template equals inherited template', () => {
		const wrapper = mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: true,
					validationSite: '',
					customizeFooterTemplate: true,
					footerTemplate: 'Inherited footer template',
				}),
				inheritedTemplate: 'Inherited footer template',
				editorScope: 'user',
				showPreview: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		expect(wrapper.find('.button-stub').exists()).toBe(false)
	})

	it('restores inherited template in editor after reset through parent v-model', async () => {
		const Harness = defineComponent({
			components: { SignatureFooterRuleEditor },
			data: () => ({
				value: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: true,
					validationSite: '',
					customizeFooterTemplate: true,
					footerTemplate: 'Custom footer',
				}),
			}),
			template: '<SignatureFooterRuleEditor v-model="value" inherited-template="Inherited footer template" :show-preview="false" />',
		})

		const wrapper = mount(Harness, {
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		await wrapper.find('.button-stub').trigger('click')
		await nextTick()

		const editor = wrapper.find('.code-editor-stub')
		expect((editor.element as HTMLTextAreaElement).value).toBe('Inherited footer template')

		const model = parseEmittedValue((wrapper.vm as { value: EffectivePolicyValue }).value)
		expect(model).toMatchObject({
			customizeFooterTemplate: true,
			footerTemplate: '',
		})
	})

	it('shows preview when customize footer template is enabled', async () => {
		const wrapper = mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: true,
					validationSite: '',
					customizeFooterTemplate: false,
					footerTemplate: '',
				}),
				inheritedTemplate: 'Inherited footer template',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		await wrapper.setProps({
			modelValue: asModelValue({
				enabled: true,
				writeQrcodeOnFooter: true,
				validationSite: '',
				customizeFooterTemplate: true,
				footerTemplate: 'Custom footer',
			}),
		})
		await nextTick()
		await Promise.resolve()

		expect(axiosPostMock).toHaveBeenCalled()
		const preview = wrapper.find('.pdf-elements-stub')
		expect(preview.exists()).toBe(true)
		expect(ensurePdfWorkerMock).toHaveBeenCalled()
	})

	it('does not show preview when customize footer template is disabled', async () => {
		const wrapper = mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: true,
					validationSite: '',
					customizeFooterTemplate: false,
					footerTemplate: '',
				}),
				inheritedTemplate: 'Inherited footer template',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		await nextTick()

		expect(axiosPostMock).not.toHaveBeenCalled()
		expect(wrapper.find('.pdf-elements-stub').exists()).toBe(false)
	})

	it('uses persisted preview width and height to generate preview pdf', async () => {
		mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: false,
					validationSite: '',
					customizeFooterTemplate: true,
					footerTemplate: 'Custom footer',
					previewWidth: 720,
					previewHeight: 140,
					previewZoom: 100,
				}),
				inheritedTemplate: 'Inherited footer template',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		await nextTick()
		await Promise.resolve()

		expect(axiosPostMock).toHaveBeenCalled()
		expect(axiosPostMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/admin/footer-template/preview-pdf',
			expect.objectContaining({
				width: 720,
				height: 140,
			}),
			expect.objectContaining({ responseType: 'blob' }),
		)
	})

	it('persists preview zoom updates in emitted payload', async () => {
		const wrapper = mount(SignatureFooterRuleEditor, {
			props: {
				modelValue: asModelValue({
					enabled: true,
					writeQrcodeOnFooter: false,
					validationSite: '',
					customizeFooterTemplate: true,
					footerTemplate: 'Custom footer',
					previewWidth: 595,
					previewHeight: 100,
					previewZoom: 100,
				}),
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
					CodeEditor: {
						name: 'CodeEditor',
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<div class="code-editor-wrapper-stub"><div class="code-editor-header-stub"><slot name="label-actions" /></div><textarea class="code-editor-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})

		const previewInputs = wrapper.findAll('.text-field-stub')
		await previewInputs[0].setValue('140')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			previewZoom: 140,
		})
	})
})
