/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'

import DeviceReportedLocation from '../../../components/validation/DeviceReportedLocation.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

type DeviceReportedLocationVm = {
	open: boolean
	hasContent: boolean
	latitude: string | null
	longitude: string | null
	physicalPresenceDisclaimer: string
	deviceReportedLocationLabel: string
}

type DeviceReportedLocationWrapper = VueWrapper<any> & {
	vm: DeviceReportedLocationVm
}

describe('DeviceReportedLocation', () => {
	let wrapper: DeviceReportedLocationWrapper | null

	const createWrapper = (geolocation?: Record<string, unknown> | null): DeviceReportedLocationWrapper => mount(DeviceReportedLocation, {
		props: { geolocation },
		global: {
			stubs: {
				NcButton: true,
				NcIconSvgWrapper: true,
				NcListItem: {
					template: '<li><slot name="name" /><slot name="extra-actions" /></li>',
				},
			},
		},
	}) as DeviceReportedLocationWrapper

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
	})

	it('hides itself when coordinates are missing', () => {
		wrapper = createWrapper({ status: 'denied' })
		expect(wrapper.vm.hasContent).toBe(false)
		expect(wrapper.text()).not.toContain('Device-reported location')
	})

	it('shows only the section label while collapsed', () => {
		wrapper = createWrapper({
			status: 'collected',
			latitude: -23.55,
			longitude: -46.63,
		})

		expect(wrapper.vm.hasContent).toBe(true)
		expect(wrapper.vm.open).toBe(false)
		expect(wrapper.text()).toContain('Device-reported location')
		expect(wrapper.text()).not.toContain('Latitude:')
		expect(wrapper.text()).not.toContain('Not verified')
	})

	it('reveals latitude, longitude and physical presence when expanded', async () => {
		wrapper = createWrapper({
			status: 'collected',
			latitude: -23.55,
			longitude: -46.63,
		})

		wrapper.vm.open = true
		await wrapper.vm.$nextTick()

		expect(wrapper.text()).toContain('Latitude:')
		expect(wrapper.text()).toContain('-23.55')
		expect(wrapper.text()).toContain('Longitude:')
		expect(wrapper.text()).toContain('-46.63')
		expect(wrapper.text()).toContain('Physical presence not verified')
		expect(wrapper.find('.serial-hex').exists()).toBe(true)
	})
})
