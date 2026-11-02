/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'

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

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

type SignatureComponent = typeof import('../../../../views/Account/partials/Signature.vue').default

type SignatureVm = {
	signatureLoaded: (success: boolean) => void
	edit: () => void
	$nextTick: () => Promise<void>
	mdiDelete: string
	mdiDraw: string
}

type SignatureWrapper = VueWrapper<any> & {
	vm: SignatureVm
}

let Signature: SignatureComponent

beforeAll(async () => {
	;({ default: Signature } = await import('../../../../views/Account/partials/Signature.vue'))
})

describe('Signature', () => {
	it('registers icon wrapper and exposes mdi icon paths used in template', async () => {
		const wrapper = mount(Signature, {
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
		}) as SignatureWrapper

		wrapper.vm.signatureLoaded(true)
		await wrapper.vm.$nextTick()

		expect(wrapper.findAll('.icon')).toHaveLength(2)
		expect(wrapper.vm.mdiDelete).toBeTruthy()
		expect(wrapper.vm.mdiDraw).toBeTruthy()
	})

	it('renders Draw editor when entering edit mode', async () => {
		const wrapper = mount(Signature, {
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
		}) as SignatureWrapper

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
