/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import SignatureHashAlgorithm from '../../../views/Settings/SignatureHashAlgorithm.vue'

const confirmPasswordMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: (...args: unknown[]) => confirmPasswordMock(...args),
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

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

describe('SignatureHashAlgorithm.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		confirmPasswordMock.mockResolvedValue(undefined)
	})

	function createWrapper() {
		return mount(SignatureHashAlgorithm, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcSelect: true,
				},
			},
		})
	}

	it('loads a saved valid hash algorithm', async () => {
		vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: { data: 'SHA512' } } } })
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.selected).toBe('SHA512')
	})

	it('falls back to SHA256 when the configured value is invalid', async () => {
		vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: { data: 'MD5' } } } })
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.selected).toBe('SHA256')
	})

	it('confirms password and persists the selected hash', async () => {
		vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: { data: 'SHA256' } } } })
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.selected = 'SHA384'
		await wrapper.vm.saveSignatureHash()

		expect(confirmPasswordMock).toHaveBeenCalled()
		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'signature_hash_algorithm', 'SHA384')
		expect(wrapper.vm.idKey).toBe(1)
	})
})