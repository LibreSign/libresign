/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import CollectMetadata from '../../../views/Settings/CollectMetadata.vue'

const emitMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: (...args: unknown[]) => emitMock(...args),
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

describe('CollectMetadata.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	function createWrapper() {
		return mount(CollectMetadata, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: true,
				},
			},
		})
	}

	it('loads enabled state from provisioning config', async () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: { ocs: { data: { data: '1' } } },
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.collectMetadataEnabled).toBe(true)
	})

	it('persists the flag and emits a change event on success', () => {
		const wrapper = createWrapper()

		wrapper.vm.collectMetadataEnabled = true
		wrapper.vm.saveCollectMetadata()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledTimes(1)
		const callbacks = vi.mocked(OCP.AppConfig.setValue).mock.calls[0][3]
		callbacks?.success?.()

		expect(emitMock).toHaveBeenCalledWith('collect-metadata:changed')
	})
})
