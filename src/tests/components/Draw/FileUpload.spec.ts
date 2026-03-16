/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FileUpload from '../../../components/Draw/FileUpload.vue'

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
		template: '<button @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
		props: ['disabled', 'variant', 'wide', 'ariaLabel', 'title'],
		emits: ['click'],
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		template: '<div><slot /><slot name="actions" /></div>',
		props: ['name', 'contentClasses'],
		emits: ['closing'],
	},
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: {
		name: 'NcTextField',
		template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
		props: ['modelValue', 'label', 'disabled', 'type', 'min', 'max', 'step'],
		emits: ['update:modelValue'],
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<span class="icon-stub" />',
		props: ['path', 'size'],
	},
}))

vi.mock('vue-advanced-cropper', () => ({
	Cropper: {
		name: 'Cropper',
		template: '<div class="cropper-stub" />',
		props: ['src', 'defaultSize', 'stencilProps', 'imageRestriction'],
		emits: ['change'],
		methods: {
			zoom: vi.fn(),
			move: vi.fn(),
			getResult: vi.fn(() => ({
				visibleArea: { width: 200, height: 80, left: 0, top: 0 },
				image: { width: 400, height: 160 },
			})),
		},
	},
}))

describe('FileUpload.vue - Uploaded signature flow', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.stubGlobal('ResizeObserver', class {
			observe = vi.fn()
			disconnect = vi.fn()
		})
	})

	function mountComponent() {
		return mount(FileUpload)
	}

	it('initializes stencil dimensions from capabilities', () => {
		const wrapper = mountComponent()

		expect(wrapper.vm.stencilBaseWidth).toBe(700)
		expect(wrapper.vm.stencilBaseHeight).toBe(200)
		expect(wrapper.vm.defaultStencilSize).toEqual({ width: 700, height: 200 })
	})

	it('scales the default stencil size to fit the cropper container', async () => {
		const wrapper = mountComponent()

		wrapper.vm.containerWidth = 374
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.defaultStencilSize).toEqual({ width: 350, height: 100 })
	})

	it('loads the selected file as a data URL', () => {
		const wrapper = mountComponent()
		const listeners = new Map<string, Array<() => void>>()

		class FileReaderMock {
			result: string | null = 'data:image/png;base64,loaded'

			addEventListener(event: string, callback: () => void) {
				listeners.set(event, [...(listeners.get(event) || []), callback])
			}

			readAsDataURL() {
				for (const callback of listeners.get('load') || []) {
					callback()
				}
			}
		}

		vi.stubGlobal('FileReader', FileReaderMock)

		wrapper.vm.fileSelect({
			target: {
				files: [new File(['binary'], 'signature.png', { type: 'image/png' })],
			},
		} as unknown as Event)

		expect(wrapper.vm.image).toBe('data:image/png;base64,loaded')
	})

	it('clamps zoom level from cropper results', () => {
		const wrapper = mountComponent()

		wrapper.vm.updateZoomLevelFromResult({
			visibleArea: { width: 100, height: 80, left: 0, top: 0 },
			image: { width: 900, height: 300 },
		})

		expect(wrapper.vm.zoomLevel).toBe(8)
	})

	it('zooms through the cropper instance and refreshes the zoom level', async () => {
		const zoom = vi.fn()
		const getResult = vi.fn(() => ({
			visibleArea: { width: 200, height: 80, left: 0, top: 0 },
			image: { width: 400, height: 160 },
		}))
		const wrapper = mountComponent()

		wrapper.vm.cropper = { zoom, getResult }
		wrapper.vm.zoomBy(1.25)
		await wrapper.vm.$nextTick()

		expect(zoom).toHaveBeenCalledWith(1.25)
		expect(wrapper.vm.zoomLevel).toBe(2)
	})

	it('centers the image after fit-to-area completes', () => {
		const move = vi.fn()
		const wrapper = mountComponent()

		wrapper.vm.cropper = { move }
		wrapper.vm.pendingFitCenter = true
		wrapper.vm.zoomLevel = 1

		wrapper.vm.change({
			canvas: {
				toDataURL: () => 'data:image/png;base64,cropped',
			},
			visibleArea: { width: 100, height: 80, left: 0, top: 0 },
			image: { width: 100, height: 200 },
		})

		expect(wrapper.vm.imageData).toBe('data:image/png;base64,cropped')
		expect(move).toHaveBeenCalledWith(0, 60)
		expect(wrapper.vm.pendingFitCenter).toBe(false)
	})

	it('emits save with the cropped image and closes the modal', () => {
		const wrapper = mountComponent()

		wrapper.vm.modal = true
		wrapper.vm.imageData = 'data:image/png;base64,signed'
		wrapper.vm.saveSignature()

		expect(wrapper.vm.modal).toBe(false)
		expect(wrapper.emitted('save')).toEqual([['data:image/png;base64,signed']])
	})

	it('does not emit save when there is no cropped image', () => {
		const wrapper = mountComponent()

		wrapper.vm.modal = true
		wrapper.vm.imageData = ''
		wrapper.vm.saveSignature()

		expect(wrapper.emitted('save')).toBeFalsy()
		expect(wrapper.vm.modal).toBe(true)
	})

	it('opens and closes the confirmation dialog through actions', () => {
		const wrapper = mountComponent()
		wrapper.vm.image = 'data:image/png;base64,selected'
		wrapper.vm.imageData = 'data:image/png;base64,cropped'

		wrapper.vm.confirmSave()
		expect(wrapper.vm.modal).toBe(true)

		wrapper.vm.cancel()
		expect(wrapper.vm.modal).toBe(false)
	})

	it('does not open confirmation dialog without cropped image data', () => {
		const wrapper = mountComponent()

		wrapper.vm.image = 'data:image/png;base64,selected'
		wrapper.vm.imageData = ''
		wrapper.vm.confirmSave()

		expect(wrapper.vm.modal).toBe(false)
		expect(wrapper.vm.canSave).toBe(false)
	})

	it('emits close when the cancel action is requested', () => {
		const wrapper = mountComponent()

		wrapper.vm.close()

		expect(wrapper.emitted('close')).toEqual([[]])
	})

	it('resets crop state when the image is cleared', async () => {
		const disconnect = vi.fn()
		const wrapper = mountComponent()

		wrapper.vm.resizeObserver = { observe: vi.fn(), disconnect }
		wrapper.vm.containerWidth = 480
		wrapper.vm.zoomLevel = 2.4
		wrapper.vm.pendingFitCenter = true
		wrapper.vm.image = 'data:image/png;base64,existing'
		await wrapper.vm.$nextTick()

		wrapper.vm.image = ''
		await wrapper.vm.$nextTick()

		expect(disconnect).toHaveBeenCalled()
		expect(wrapper.vm.containerWidth).toBe(0)
		expect(wrapper.vm.zoomLevel).toBe(1)
		expect(wrapper.vm.pendingFitCenter).toBe(false)
	})
})
