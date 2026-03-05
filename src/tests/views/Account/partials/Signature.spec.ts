/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('../../../../store/signatureElements.js', () => ({
	useSignatureElementsStore: () => ({
		signRequestUuid: 'req-1',
		signs: {
			signature: {
				value: 'data:image/png;base64,abc',
			},
		},
		hasSignatureOfType: () => true,
		delete: vi.fn(),
		success: '',
		error: null,
	}),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

let Signature: unknown

beforeAll(async () => {
	;({ default: Signature } = await import('../../../../views/Account/partials/Signature.vue'))
})

describe('Signature', () => {
	it('registers icon wrapper and exposes mdi icon paths used in template', () => {
		const wrapper = mount(Signature as never, {
			props: {
				type: 'signature',
			},
			global: {
				stubs: {
					NcActions: { template: '<div><slot /></div>' },
					NcActionButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
					PreviewSignature: true,
					Draw: true,
				},
			},
		})

		expect(wrapper.vm.$options.components.NcIconSvgWrapper).toBeTruthy()
		expect(wrapper.vm.mdiDelete).toBeTruthy()
		expect(wrapper.vm.mdiDraw).toBeTruthy()
	})

	it('renders Draw editor when entering edit mode', async () => {
		const wrapper = mount(Signature as never, {
			props: {
				type: 'signature',
			},
			global: {
				stubs: {
					NcActions: { template: '<div><slot /></div>' },
					NcActionButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
					PreviewSignature: true,
					Draw: {
						name: 'Draw',
						props: ['drawEditor', 'textEditor', 'fileEditor', 'type'],
						template: '<div class="draw-editor-stub" />',
					},
				},
			},
		})

		wrapper.vm.edit()
		await wrapper.vm.$nextTick()

		const draw = wrapper.findComponent({ name: 'Draw' })
		expect(draw.exists()).toBe(true)
		expect(draw.props('drawEditor')).toBe(true)
		expect(draw.props('textEditor')).toBe(true)
		expect(draw.props('fileEditor')).toBe(true)
		expect(draw.props('type')).toBe('signature')
	})
})
