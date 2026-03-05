/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const loadStateMock = vi.fn()
const getCurrentUserMock = vi.fn()
const axiosGetMock = vi.fn().mockResolvedValue({ data: { ocs: { data: { data: [] } } } })

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => getCurrentUserMock(),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGetMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showWarning: vi.fn(),
	showSuccess: vi.fn(),
	getFilePickerBuilder: vi.fn(),
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

let Documents: unknown

beforeAll(async () => {
	;({ default: Documents } = await import('../../../../views/Account/partials/Documents.vue'))
})

describe('Documents', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
		getCurrentUserMock.mockReset()
		axiosGetMock.mockClear()
	})

	it('registers icon wrapper and exposes mdi icon paths used in template', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'config') return { identificationDocumentsFlow: true }
			return fallback
		})
		getCurrentUserMock.mockReturnValue({ uid: 'user' })

		const wrapper = mount(Documents as never, {
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcNoteCard: { template: '<div><slot /></div>' },
					NcLoadingIcon: true,
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
				},
			},
		})
		await flushPromises()

		expect(wrapper.vm.$options.components.NcIconSvgWrapper).toBeTruthy()
		expect(wrapper.vm.mdiFolder).toBeTruthy()
		expect(wrapper.vm.mdiUpload).toBeTruthy()
		expect(wrapper.vm.mdiDelete).toBeTruthy()
	})
})
