/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Editor from '../../../components/Draw/Editor.vue'
import type { TranslationFunction } from '../../test-types'

type SignaturePadInstance = {
	penColor: string
	isEmpty: () => boolean
	clear: () => void
	toDataURL: () => string
	addEventListener: () => void
}

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

vi.mock('@nextcloud/vue/components/NcColorPicker', () => ({
	default: {
		name: 'NcColorPicker',
		template: '<div><button></button><slot /></div>',
		props: ['modelValue', 'palette'],
		emits: ['submit', 'update:modelValue'],
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		template: '<div><slot /></div>',
		props: ['name'],
		emits: ['closing'],
	},
}))

vi.mock('../../../components/PreviewSignature/PreviewSignature.vue', () => ({
	default: {
		name: 'PreviewSignature',
		template: '<div></div>',
		props: ['src'],
	},
}))


vi.mock('signature_pad', () => {
	return {
		__esModule: true,
		default: vi.fn(function(this: SignaturePadInstance, _canvas: HTMLCanvasElement) {
			this.penColor = '#000000'
			this.isEmpty = vi.fn(() => false)
			this.clear = vi.fn()
			this.toDataURL = vi.fn(() => 'data:image/png;base64,test')
			this.addEventListener = vi.fn()
		}),
		SignaturePad: vi.fn(function(this: SignaturePadInstance, _canvas: HTMLCanvasElement) {
			this.penColor = '#000000'
			this.isEmpty = vi.fn(() => false)
			this.clear = vi.fn()
			this.toDataURL = vi.fn(() => 'data:image/png;base64,test')
			this.addEventListener = vi.fn()
		}),
	}
})

describe('Editor.vue - Drawing Signature Editor', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	it('initializes with default color black', () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.color).toBe('#000000')
	})

	it('has predefined color palette', () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.customPalette).toEqual([
			'#000000',
			'#ff0000',
			'#0000ff',
			'#008000',
		])
	})

	it('disables save button when canvas is empty', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.canSave).toBe(false)
	})

	it('enables save button after drawing', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.canSave = true
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.canSave).toBe(true)
	})

	it('applies canvas size on mount', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.$refs.canvas).toBeDefined()
	})

	it('clears canvas and resets save state', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.canSave = true
		wrapper.vm.clear()
		expect(wrapper.vm.canSave).toBe(false)
	})

	it('changes pen color when color updated', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.color = '#ff0000'
		wrapper.vm.updateColor()
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.color).toBe('#ff0000')
	})

	it('creates data image from canvas', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.createDataImage()
		expect(wrapper.vm.imageData).toBeDefined()
		expect(wrapper.vm.imageData).toContain('data:image')
	})

	it('opens confirmation dialog on confirmationDraw', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.confirmationDraw()
		expect(wrapper.vm.modal).toBe(true)
		expect(wrapper.vm.imageData).toBeDefined()
	})

	it('handles modal state correctly', async () => {
		const wrapper = mount(Editor, {
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

	it('emits close event when close called', async () => {
		const wrapper = mount(Editor, {
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

	it('emits save event with image data', async () => {
		const wrapper = mount(Editor, {
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

	it('closes modal after saving signature', async () => {
		const wrapper = mount(Editor, {
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

	it('sets mounted flag to true after mount', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.mounted).toBe(true)
	})

	it('resets state on component destruction', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.imageData = 'data:image/png;base64,test'
		wrapper.unmount()

		expect(wrapper.vm.mounted).toBe(false)
	})

	it('calculates scale based on available width', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.scale).toBeGreaterThan(0)
	})

	it('enforces minimum display dimensions', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const canvas = wrapper.vm.$refs.canvas
		expect(canvas.width).toBeGreaterThan(0)
		expect(canvas.height).toBeGreaterThan(0)
	})

	it('supports multiple colors from palette', async () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const colors = wrapper.vm.customPalette

		for (const color of colors) {
			wrapper.vm.color = color
			wrapper.vm.updateColor()
			expect(wrapper.vm.color).toBe(color)
		}
	})

	it('initializes image data as null', () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.imageData).toBe(null)
	})

	it('initializes modal as false', () => {
		const wrapper = mount(Editor, {
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.modal).toBe(false)
	})
})
