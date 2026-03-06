/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import DefaultUserFolder from '../../../views/Settings/DefaultUserFolder.vue'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
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

describe('DefaultUserFolder.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	function createWrapper() {
		return mount(DefaultUserFolder, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcTextField: true,
					NcCheckboxRadioSwitch: true,
				},
			},
		})
	}

	it('loads the saved folder name and enables customization when a value exists', async () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: { ocs: { data: { data: 'LibreSign Custom' } } },
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.customUserFolder).toBe(true)
		expect(wrapper.vm.value).toBe('LibreSign Custom')
	})

	it('falls back to LibreSign when the stored folder is empty', async () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: { ocs: { data: { data: '' } } },
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.customUserFolder).toBe(false)
		expect(wrapper.vm.value).toBe('LibreSign')
	})

	it('persists the current folder value through OCP.AppConfig', () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: { ocs: { data: { data: '' } } },
		})

		const wrapper = createWrapper()
		wrapper.vm.value = 'Contracts'
		wrapper.vm.saveDefaultUserFolder()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'default_user_folder', 'Contracts')
	})
})
