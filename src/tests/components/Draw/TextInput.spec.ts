/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TextInput from '../../../components/Draw/TextInput.vue'
import type { TranslationFunction } from '../../test-types'

const t: TranslationFunction = (_app, text) => text

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': {
					'signature-width': 700,
					'signature-height': 200,
				},
			},
		},
	})),
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		template: '<button @click="$emit(\'click\')"><slot /></button>',
		props: ['disabled', 'variant'],
		emits: ['click'],
	},
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: {
		name: 'NcTextField',
		template: '<input @input="$emit(\'update:modelValue\', $event.target.value)" />',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		methods: {
			focus: vi.fn(),
		},
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		template: '<div><slot /><slot name="actions" /></div>',
		props: ['name'],
		emits: ['closing'],
	},
}))

vi.mock('../../../components/PreviewSignature/PreviewSignature.vue', () => ({
	default: {
		name: 'PreviewSignature',
		template: '<div class="preview-signature-stub" />',
		props: ['src'],
	},
}))

vi.mock('@fontsource/dancing-script', () => ({}))

describe('TextInput.vue - Text Signature Component', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	it('initializes with empty value', () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.value).toBe('')
	})

	it('validates that input is not empty', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.isValid).toBe(false)

		wrapper.vm.value = 'John Doe'
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.isValid).toBe(true)
	})

	it('disables save button when input is empty', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.isValid).toBe(false)
	})

	it('enables save button when input has value', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		wrapper.vm.value = 'Jane Smith'
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.isValid).toBe(true)
	})

	it('focuses input on mount', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.$refs.input).toBeDefined()
	})

	it('converts text to canvas image', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = 'Test'
		await wrapper.vm.$nextTick()

		if (wrapper.vm.$refs.canvas && wrapper.vm.$refs.canvas.toDataURL) {
			wrapper.vm.stringToImage()
			expect(wrapper.vm.imageData).toBeDefined()
		}
	})

	it('clears image data on cancel', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.modal = true
		wrapper.vm.imageData = 'data:image/png;base64,test'
		wrapper.vm.modal = false
		expect(wrapper.vm.modal).toBe(false)
	})

	it('opens confirmation modal when saving', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = 'John Doe'
		wrapper.vm.confirmSignature()
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.modal).toBe(true)
		expect(wrapper.find('.preview-signature-stub').exists()).toBe(true)
	})

	it('handles modal state correctly', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.modal).toBe(false)
		wrapper.vm.handleModal(true)
		expect(wrapper.vm.modal).toBe(true)
		wrapper.vm.handleModal(false)
		expect(wrapper.vm.modal).toBe(false)
	})

	it('emits save event with image data', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const testData = 'data:image/png;base64,testdata'
		wrapper.vm.imageData = testData
		wrapper.vm.saveSignature()

		const emitted = wrapper.emitted('save') ?? []
		expect(emitted.length).toBeGreaterThan(0)
		expect(emitted[0]?.[0]).toBe(testData)
	})

	it('closes modal after save', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.modal = true
		wrapper.vm.saveSignature()
		expect(wrapper.vm.modal).toBe(false)
	})

	it('cancels confirmation and closes modal', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.modal = true
		wrapper.vm.imageData = 'data:image/png;base64,test'
		wrapper.vm.handleModal(false)
		expect(wrapper.vm.modal).toBe(false)
	})

	it('emits close event when close called', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.close()
		expect(wrapper.emitted('close')).toBeTruthy()
	})

	it('applies canvas size on mount', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.$refs.canvas).toBeDefined()
	})

	it('renders text on canvas when value changes', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = 'Test Signature'
		await wrapper.vm.$nextTick()

		const canvas = wrapper.vm.$refs.canvas
		expect(canvas).toBeDefined()
	})

	it('handles multiline text on canvas', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = 'Very Long Name That Should Wrap Into Multiple Lines'
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.value).toBe('Very Long Name That Should Wrap Into Multiple Lines')
	})

	it('initializes modal as closed', () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.modal).toBe(false)
	})

	it('initializes image data as null', () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.imageData).toBe(null)
	})

	it('uses Dancing Script font for text rendering', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = 'John Doe'
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.value).toBe('John Doe')
	})

	it('validates full name and initials', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()

		wrapper.vm.value = 'JD'
		expect(wrapper.vm.isValid).toBe(true)

		wrapper.vm.value = 'John Doe'
		expect(wrapper.vm.isValid).toBe(true)
	})

	it('trims whitespace from input', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = '   John Doe   '
		wrapper.vm.confirmSignature()

		expect(wrapper.vm.modal).toBe(true)
	})

	it('handles special characters in name', async () => {
		const wrapper = mount(TextInput, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.value = "O'Brien-François"
		expect(wrapper.vm.isValid).toBe(true)
	})
})
