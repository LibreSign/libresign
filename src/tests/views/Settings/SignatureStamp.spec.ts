/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import SignatureStamp from '../../../views/Settings/SignatureStamp.vue'

const axiosGetMock = vi.fn()
const axiosPostMock = vi.fn()
const axiosPatchMock = vi.fn()
const axiosDeleteMock = vi.fn()
const clipboardWriteTextMock = vi.fn()
const subscribeMock = vi.fn()
const unsubscribeMock = vi.fn()

let stateOverrides: Record<string, unknown> = {}

vi.mock('debounce', () => ({
	default: vi.fn((fn: (...args: unknown[]) => unknown) => fn),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({ displayName: 'Jane Doe' })),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn((...args: unknown[]) => axiosGetMock(...args)),
		post: vi.fn((...args: unknown[]) => axiosPostMock(...args)),
		patch: vi.fn((...args: unknown[]) => axiosPatchMock(...args)),
		delete: vi.fn((...args: unknown[]) => axiosDeleteMock(...args)),
	},
}))

vi.mock('@nextcloud/event-bus', () => ({
	subscribe: vi.fn((...args: unknown[]) => subscribeMock(...args)),
	unsubscribe: vi.fn((...args: unknown[]) => unsubscribeMock(...args)),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue?: unknown) => {
		const defaults: Record<string, unknown> = {
			signature_background_type: 'default',
			effective_policies: {
				policies: {
					signature_text: {
						policyKey: 'signature_text',
						effectiveValue: JSON.stringify({
							template: 'Signed by {{ signerName }}',
							template_font_size: 10,
							signature_font_size: 18,
							signature_width: 180,
							signature_height: 90,
							render_mode: 'GRAPHIC_AND_DESCRIPTION',
						}),
						sourceScope: 'system',
						visible: true,
						editableByCurrentActor: true,
						allowedValues: [],
						canSaveAsUserDefault: true,
						canUseAsRequestOverride: true,
						preferenceWasCleared: false,
						blockedBy: null,
						groupCount: 0,
						userCount: 0,
					},
				},
			},
			signature_preview_zoom_level: 100,
			signature_text_template_error: '',
			signature_text_parsed: '<p>Signed by Jane Doe</p>',
			signature_available_variables: {
				'{{ signerName }}': 'Signer name',
			},
		}

		if (key in stateOverrides) {
			return stateOverrides[key]
		}

		return key in defaults ? defaults[key] : defaultValue
	}),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/vue/composables/useIsDarkTheme', () => ({
	useIsDarkTheme: vi.fn(() => false),
}))

vi.mock('@nextcloud/vue/directives/Linkify', () => ({
	default: {},
}))

vi.mock('../../../components/CodeEditor.vue', () => ({
	default: {
		name: 'CodeEditor',
		props: ['modelValue', 'label', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<textarea class="code-editor-stub" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button class="nc-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'value'],
		emits: ['update:modelValue'],
		template: '<div class="nc-checkbox-radio-switch-stub"><slot /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		props: ['open', 'name'],
		emits: ['update:open'],
		template: '<div class="nc-dialog-stub"><slot /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcFormBoxButton', () => ({
	default: {
		name: 'NcFormBoxButton',
		emits: ['click'],
		template: '<button class="nc-form-box-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /><slot name="description" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<i class="nc-icon-svg-wrapper-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: {
		name: 'NcLoadingIcon',
		template: '<span class="nc-loading-icon-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: {
		name: 'NcNoteCard',
		template: '<div class="nc-note-card-stub"><slot /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({
	default: {
		name: 'NcSettingsSection',
		template: '<section class="nc-settings-section-stub"><slot /></section>',
	},
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue', 'keydown.enter', 'blur'],
		template: '<input class="nc-text-field-stub" />',
	},
}))

describe('SignatureStamp.vue', () => {
	const appConfigMock = {
		setValue: vi.fn(),
	}

	const createWrapper = () => mount(SignatureStamp)

	beforeEach(() => {
		stateOverrides = {}
		axiosGetMock.mockReset()
		axiosPostMock.mockReset()
		axiosPatchMock.mockReset()
		axiosDeleteMock.mockReset()
		clipboardWriteTextMock.mockReset()
		subscribeMock.mockReset()
		unsubscribeMock.mockReset()
		appConfigMock.setValue.mockReset()

		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						signature_available_variables: { '{{ signerName }}': 'Signer name' },
						default_signature_text_template: 'Updated default',
						parsed: '<p>Updated</p>',
						templateFontSize: 12,
						signatureFontSize: 20,
					},
				},
			},
		})

		axiosPostMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_text',
							effectiveValue: '{"template":"Updated template"}',
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							allowedValues: [],
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		axiosPatchMock.mockResolvedValue({ data: { ocs: { data: {} } } })
		axiosDeleteMock.mockResolvedValue({ data: { ocs: { data: {} } } })

		vi.stubGlobal('OCP', { AppConfig: appConfigMock })
		vi.stubGlobal('navigator', {
			clipboard: {
				writeText: clipboardWriteTextMock,
			},
		})
	})

	it('loads preview state from initial settings', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.renderMode).toBe('GRAPHIC_AND_DESCRIPTION')
		expect(wrapper.vm.backgroundUrl).toBe('/ocs/v2.php/apps/libresign/api/v1/admin/signature-background')
		expect(wrapper.vm.displayPreview).toBe(true)
		expect(wrapper.vm.signatureImageUrl).toContain('text=Signature%20image%20here')
	})

	it('hides preview when background is deleted and description text is empty', () => {
		stateOverrides = {
			signature_background_type: 'deleted',
			signature_text_parsed: '',
		}

		const wrapper = createWrapper()
		wrapper.vm.renderMode = 'DESCRIPTION_ONLY'

		expect(wrapper.vm.displayPreview).toBe(false)
	})

	it('copies available variables to the clipboard and marks them as copied', async () => {
		vi.useFakeTimers()
		const wrapper = createWrapper()

		wrapper.vm.copyToClipboard('{{ signerName }}')

		expect(clipboardWriteTextMock).toHaveBeenCalledWith('{{ signerName }}')
		expect(wrapper.vm.isCopied('{{ signerName }}')).toBe(true)

		vi.advanceTimersByTime(2000)
		expect(wrapper.vm.copiedVariable).toBeNull()
		vi.useRealTimers()
	})

	it('saves the template and synchronizes normalized font sizes from the API response', async () => {
		const wrapper = createWrapper()
		wrapper.vm.signatureTextTemplate = 'Updated template'
		wrapper.vm.templateFontSize = 11
		wrapper.vm.signatureFontSize = 19

		await wrapper.vm.saveTemplate()
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/policies/system/signature_text', {
			allowChildOverride: false,
			value: JSON.stringify({
				template: 'Updated template',
				template_font_size: 11,
				signature_font_size: 19,
				signature_width: 180,
				signature_height: 90,
				render_mode: 'default',
			}),
		})
		expect(axiosGetMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/admin/signature-text', {
			params: {
				template: 'Updated template',
			},
		})
		expect(wrapper.vm.parsed).toBe('<p>Updated</p>')
		expect(wrapper.vm.templateFontSize).toBe(12)
		expect(wrapper.vm.signatureFontSize).toBe(20)
		expect(wrapper.vm.templateSaved).toBe(true)
	})

	it('uploads and removes the background image', async () => {
		vi.spyOn(Date, 'now').mockReturnValue(123456)
		const wrapper = createWrapper()
		const event = {
			target: {
				files: [new File(['png'], 'signature.png', { type: 'image/png' })],
			},
		} as unknown as Event

		await wrapper.vm.onChangeBackground(event)
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalled()
		expect(wrapper.vm.backgroundType).toBe('custom')
		expect(wrapper.vm.backgroundUrl).toContain('?t=123456')

		await wrapper.vm.removeBackground()
		await flushPromises()

		expect(axiosDeleteMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/admin/signature-background', {
			data: {
				setting: undefined,
				value: 'backgroundColor',
			},
		})
		expect(wrapper.vm.backgroundType).toBe('deleted')
		expect(wrapper.vm.backgroundUrl).toBe('')
	})

	it('resets render mode and preview dimensions back to defaults', async () => {
		const wrapper = createWrapper()
		wrapper.vm.renderMode = 'GRAPHIC_ONLY'
		wrapper.vm.signatureWidth = 250
		wrapper.vm.signatureHeight = 130

		await wrapper.vm.resetRenderMode()
		await wrapper.vm.resetSignatureWidth()
		await wrapper.vm.resetSignatureHeight()

		expect(wrapper.vm.renderMode).toBe('GRAPHIC_AND_DESCRIPTION')
		expect(wrapper.vm.signatureWidth).toBe(90)
		expect(wrapper.vm.signatureHeight).toBe(60)
	})

	it('regression: signature dimension fields bind min="1" matching the backend SIGNATURE_DIMENSION_MINIMUM', () => {
		const wrapper = createWrapper()

		const textFields = wrapper.findAllComponents({ name: 'NcTextField' })
		const widthField = textFields.find(c => c.props('label') === 'Default signature width')
		const heightField = textFields.find(c => c.props('label') === 'Default signature height')

		expect(widthField).toBeDefined()
		expect(heightField).toBeDefined()
		expect(widthField!.attributes('min')).toBe('1')
		expect(heightField!.attributes('min')).toBe('1')
	})

	it('regression: shows API error when saveTemplate receives a 400 for invalid signature box size', async () => {
		axiosPostMock.mockRejectedValue({
			response: {
				data: {
					ocs: {
						data: {
							message: 'Invalid signature box size. Width and height must be at least 1.',
						},
					},
				},
			},
		})
		const wrapper = createWrapper()

		await wrapper.vm.saveTemplate()
		await flushPromises()

		expect(wrapper.vm.errorMessageTemplate).toContain(
			'Invalid signature box size. Width and height must be at least 1.',
		)
		expect(wrapper.vm.parsed).toBe('')
		expect(wrapper.vm.templateSaved).toBe(false)
	})
})
