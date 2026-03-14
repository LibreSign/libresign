/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import PreviewSignature from '../../../components/PreviewSignature/PreviewSignature.vue'

const axiosMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: (...args: unknown[]) => axiosMock(...args),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': {
					width: '160px',
					height: '60px',
				},
			},
		},
	})),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

describe('PreviewSignature.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	function createWrapper(props: { src: string; signRequestUuid?: string; alt?: string }) {
		return mount(PreviewSignature, {
			props,
			global: {
				stubs: {
					NcLoadingIcon: true,
				},
			},
		})
	}

	it('uses the inline data URL directly without calling axios', async () => {
		const wrapper = createWrapper({ src: 'data:image/png;base64,abc' })
		await wrapper.vm.loadImage()

		expect(axiosMock).not.toHaveBeenCalled()
		expect(wrapper.vm.imageData).toBe('data:image/png;base64,abc')
	})

	it('loads a binary image through axios and emits success', async () => {
		const binaryImage = Uint8Array.from([1, 2, 3]).buffer
		axiosMock.mockResolvedValue({
			data: binaryImage,
			headers: { 'content-type': 'image/png' },
		})
		const wrapper = createWrapper({ src: '/signature.png', signRequestUuid: 'uuid-123' })

		await wrapper.vm.loadImage()

		expect(axiosMock).toHaveBeenCalledWith({
			url: '/signature.png',
			method: 'get',
			responseType: 'arraybuffer',
			headers: {
				'libresign-sign-request-uuid': 'uuid-123',
			},
		})
		expect(wrapper.vm.imageData).toBe('data:image/png;base64,AQID')
		expect(wrapper.emitted('loaded')).toEqual([[true], [true]])
	})

	it('emits failure when axios cannot load the image', async () => {
		axiosMock.mockRejectedValue(new Error('network error'))
		const wrapper = createWrapper({ src: '/signature.png' })

		await wrapper.vm.loadImage()

		expect(wrapper.emitted('loaded')).toEqual([[false], [false]])
		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.vm.isLoaded).toBe(true)
	})

	it('reloads the image when src changes', async () => {
		axiosMock.mockResolvedValue({
			data: Uint8Array.from([1, 2, 3]).buffer,
			headers: { 'content-type': 'image/png' },
		})
		const wrapper = createWrapper({ src: '/first.png' })
		axiosMock.mockClear()

		await wrapper.setProps({ src: '/second.png' })

		expect(axiosMock).toHaveBeenCalledWith({
			url: '/second.png',
			method: 'get',
			responseType: 'arraybuffer',
		})
	})
})
