/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { showError, showSuccess } from '@nextcloud/dialogs'

import GeoIpDatabase from '../../../views/Settings/GeoIpDatabase.vue'
import { getGeoIpConfig, saveGeoIpConfig } from '../../../services/geoip'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())
vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock('../../../services/geoip', () => ({
	getGeoIpConfig: vi.fn(),
	saveGeoIpConfig: vi.fn(),
}))

describe('GeoIpDatabase', () => {
	beforeEach(() => {
		vi.mocked(getGeoIpConfig).mockReset()
		vi.mocked(saveGeoIpConfig).mockReset()
		vi.mocked(showError).mockClear()
		vi.mocked(showSuccess).mockClear()
	})

	function createWrapper() {
		return mount(GeoIpDatabase, {
			global: {
				stubs: {
					NcNoteCard: { template: '<div class="note"><slot /></div>' },
					NcTextField: {
						props: ['modelValue', 'disabled'],
						emits: ['update:modelValue'],
						template: '<input class="path-input" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
					},
					NcButton: {
						props: ['disabled'],
						emits: ['click'],
						template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
					},
					NcLoadingIcon: true,
				},
			},
		})
	}

	it('loads configuration and shows a ready database with metadata', async () => {
		vi.mocked(getGeoIpConfig).mockResolvedValue({
			path: '/var/lib/libresign/GeoLite2-City.mmdb',
			status: 'ready',
			databaseType: 'GeoLite2-City',
			buildEpoch: 1_700_000_000,
			modifiedAt: '2026-09-27T00:00:00+00:00',
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.draftPath).toBe('/var/lib/libresign/GeoLite2-City.mmdb')
		expect(wrapper.vm.statusLabel).toBe('Ready')
		expect(wrapper.text()).toContain('GeoLite2-City')
		expect(wrapper.text()).not.toContain('sourceIp')
		expect(wrapper.text()).not.toContain('81.2.69.160')
	})

	it.each([
		['not_configured', 'Not configured'],
		['not_found', 'Not found'],
		['not_readable', 'Database file is not readable'],
		['invalid_database', 'Invalid GeoIP database'],
		['unsupported_database', 'Unsupported GeoIP database'],
	] as const)('shows a user-facing %s state', async (status, label) => {
		vi.mocked(getGeoIpConfig).mockResolvedValue({
			path: status === 'not_configured' ? null : '/missing.mmdb',
			status,
		})

		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.status).toBe(status)
		expect(wrapper.vm.statusLabel).toBe(label)
		expect(wrapper.text()).not.toContain(status)
	})

	it('saves a replacement path even when the file is currently unavailable', async () => {
		vi.mocked(getGeoIpConfig).mockResolvedValue({
			path: '/old.mmdb',
			status: 'ready',
		})
		vi.mocked(saveGeoIpConfig).mockResolvedValue({
			path: '/later.mmdb',
			status: 'not_found',
		})

		const wrapper = createWrapper()
		await flushPromises()
		wrapper.vm.draftPath = '/later.mmdb'
		await wrapper.vm.savePath()
		await flushPromises()

		expect(saveGeoIpConfig).toHaveBeenCalledWith('/later.mmdb')
		expect(wrapper.vm.status).toBe('not_found')
		expect(showSuccess).toHaveBeenCalledWith('GeoIP database path saved')
	})

	it('clears the configured path with an empty save', async () => {
		vi.mocked(getGeoIpConfig).mockResolvedValue({
			path: '/old.mmdb',
			status: 'ready',
		})
		vi.mocked(saveGeoIpConfig).mockResolvedValue({
			path: null,
			status: 'not_configured',
		})

		const wrapper = createWrapper()
		await flushPromises()
		await wrapper.vm.clearPath()
		await flushPromises()

		expect(saveGeoIpConfig).toHaveBeenCalledWith('')
		expect(wrapper.vm.draftPath).toBe('')
		expect(wrapper.vm.status).toBe('not_configured')
	})
})
