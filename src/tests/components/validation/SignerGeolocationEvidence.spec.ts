/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import SignerGeolocationEvidence from '../../../components/validation/SignerGeolocationEvidence.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

describe('SignerGeolocationEvidence', () => {
	function createWrapper(props: Record<string, unknown> = {}) {
		return mount(SignerGeolocationEvidence, {
			props,
			global: {
				stubs: {
					NcListItem: { template: '<div class="heading"><slot name="name" /></div>' },
					DeviceReportedLocation: { name: 'DeviceReportedLocation', template: '<div class="device" />', props: ['geolocation'] },
					IpBasedApproximateLocation: { name: 'IpBasedApproximateLocation', template: '<div class="ip" />', props: ['geolocation'] },
				},
			},
		})
	}

	it('hides the section when neither source exists', () => {
		const wrapper = createWrapper()
		expect(wrapper.vm.hasEvidence).toBe(false)
		expect(wrapper.find('.heading').exists()).toBe(false)
	})

	it('shows only device evidence when IP is absent', () => {
		const wrapper = createWrapper({
			device: { status: 'collected', latitude: -23.55, longitude: -46.63 },
		})

		expect(wrapper.vm.hasDevice).toBe(true)
		expect(wrapper.vm.hasIp).toBe(false)
		expect(wrapper.text()).toContain('Signer geolocation')
		expect(wrapper.findComponent({ name: 'DeviceReportedLocation' }).exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'IpBasedApproximateLocation' }).exists()).toBe(true)
	})

	it('shows only IP evidence when device coordinates are absent', () => {
		const wrapper = createWrapper({
			ip: { status: 'resolved', country: 'Brazil' },
		})

		expect(wrapper.vm.hasDevice).toBe(false)
		expect(wrapper.vm.hasIp).toBe(true)
		expect(wrapper.findComponent({ name: 'IpBasedApproximateLocation' }).props('geolocation')).toEqual({
			status: 'resolved',
			country: 'Brazil',
		})
	})

	it('keeps both sources as independent children', () => {
		const wrapper = createWrapper({
			device: { status: 'collected', latitude: 1, longitude: 2 },
			ip: { status: 'not_found' },
		})

		expect(wrapper.vm.hasDevice).toBe(true)
		expect(wrapper.vm.hasIp).toBe(true)
		expect(wrapper.text()).not.toContain('match')
		expect(wrapper.text()).not.toContain('preferred')
	})
})
