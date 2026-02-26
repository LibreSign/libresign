/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('../../../../store/sign.js', () => ({
	useSignStore: () => ({
		document: {
			fileId: 1,
			signers: [],
		},
	}),
}))

vi.mock('../../../../store/signMethods.js', () => ({
	useSignMethodsStore: () => ({
		settings: {
			emailToken: {
				hasConfirmCode: false,
				hashOfEmail: '',
				identifyMethod: 'email',
			},
		},
		blurredEmail: () => '',
		setEmailToken: vi.fn(),
		setHasEmailConfirmCode: vi.fn(),
	}),
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

let ModalEmailManager: unknown

beforeAll(async () => {
	;({ default: ModalEmailManager } = await import('../../../../views/SignPDF/_partials/ModalEmailManager.vue'))
})

describe('ModalEmailManager', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
	})

	it('registers icon wrapper and exposes mdi icon paths used in template', () => {
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)

		const wrapper = mount(ModalEmailManager as never, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /><slot name="actions" /></div>' },
					NcTextField: { template: '<div><slot /></div>' },
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcLoadingIcon: true,
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
				},
			},
		})

		expect(wrapper.vm.$options.components.NcIconSvgWrapper).toBeTruthy()
		expect(wrapper.vm.mdiFormTextboxPassword).toBeTruthy()
		expect(wrapper.vm.mdiEmail).toBeTruthy()
	})
})
