/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { showSuccess } from '@nextcloud/dialogs'

import DeviceReportedLocation from '../../../components/validation/DeviceReportedLocation.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
}))

type DeviceReportedLocationVm = {
	open: boolean
	hasContent: boolean
	latitude: string | null
	longitude: string | null
	accuracy: string | null
	copied: boolean
	copyCoordinates: () => Promise<void>
	physicalPresenceDisclaimer: string
	deviceReportedLocationLabel: string
	copyCoordinatesLabel: string
}

type DeviceReportedLocationWrapper = VueWrapper<any> & {
	vm: DeviceReportedLocationVm
}

describe('DeviceReportedLocation', () => {
	let wrapper: DeviceReportedLocationWrapper | null
	let writeText: ReturnType<typeof vi.fn>

	const createWrapper = (geolocation?: Record<string, unknown> | null): DeviceReportedLocationWrapper => mount(DeviceReportedLocation, {
		props: { geolocation },
		global: {
			stubs: {
				NcButton: {
					template: '<button v-bind="$attrs"><slot /><slot name="icon" /></button>',
				},
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
		writeText = vi.fn().mockResolvedValue(undefined)
		Object.defineProperty(navigator, 'clipboard', {
			configurable: true,
			value: { writeText },
		})
		vi.mocked(showSuccess).mockClear()
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
		expect(wrapper.text()).toContain('Copy coordinates')
		expect(wrapper.text()).not.toContain('Accuracy:')
		expect(wrapper.find('.serial-hex').exists()).toBe(true)
	})

	it('shows accuracy when available', async () => {
		wrapper = createWrapper({
			status: 'collected',
			latitude: -23.55,
			longitude: -46.63,
			accuracy: 12,
		})

		wrapper.vm.open = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.accuracy).toContain('±')
		expect(wrapper.vm.accuracy).toContain('12')
		expect(wrapper.text()).toContain('Accuracy:')
	})

	it('copies coordinates without opening an external map', async () => {
		wrapper = createWrapper({
			status: 'collected',
			latitude: -23.55,
			longitude: -46.63,
			accuracy: 12,
		})

		await wrapper.vm.copyCoordinates()

		expect(writeText).toHaveBeenCalledWith('-23.55, -46.63')
		expect(showSuccess).toHaveBeenCalledWith('Coordinates copied')
		expect(wrapper.vm.copied).toBe(true)
	})
})
