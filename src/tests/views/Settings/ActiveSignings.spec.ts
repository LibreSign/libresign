/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { interpolateL10n } from '../../testHelpers/l10n.js'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import ActiveSignings from '../../../views/Settings/ActiveSignings.vue'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t: (_app: string, text: string, vars?: Record<string, string>) => interpolateL10n(text, vars),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((value?: number | string) => ({
		format: vi.fn(() => '12:00:00'),
		fromNow: vi.fn(() => `from ${value}`),
	})),
}))

describe('ActiveSignings.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	function createWrapper() {
		return mount(ActiveSignings, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcButton: true,
					NcIconSvgWrapper: true,
					NcCheckboxRadioSwitch: true,
					NcLoadingIcon: true,
				},
			},
		})
	}

	it('loads active signings on mount', async () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: {
				ocs: {
					data: {
						data: [{ id: 7, uuid: 'uuid-7', name: 'Contract.pdf', signerDisplayName: '', signerEmail: 'signer@example.com', updatedAt: 123 }],
					},
				},
			},
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.signings).toHaveLength(1)
		expect(wrapper.vm.lastUpdateTime).toBe('12:00:00')
	})

	it('starts and stops auto refresh with the toggle', async () => {
		vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: { data: [] } } } })
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.refreshInterval).not.toBeNull()
		wrapper.vm.autoRefresh = false
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.refreshInterval).toBeNull()
	})

	it('formats the file URL consistently', async () => {
		vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: { data: [] } } } })
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.getFileUrl(42)).toBe('/index.php/apps/files/?fileid=42')
	})
})
