/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'

import IpBasedApproximateLocation from '../../../components/validation/IpBasedApproximateLocation.vue'
import type { SignerIpGeolocationEvidence } from '../../../helpers/signerIpGeolocation'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

type IpBasedApproximateLocationVm = {
	open: boolean
	hasContent: boolean
	statusText: string
	sourceIp: string | null
	country: string | null
	city: string | null
	coordinates: string | null
	accuracyRadius: string | null
	approximateLocationDisclaimer: string
}

type IpWrapper = VueWrapper<any> & {
	vm: IpBasedApproximateLocationVm
}

describe('IpBasedApproximateLocation', () => {
	let wrapper: IpWrapper | null

	const createWrapper = (geolocation?: SignerIpGeolocationEvidence | null): IpWrapper => mount(IpBasedApproximateLocation, {
		props: { geolocation },
		global: {
			stubs: {
				NcButton: {
					emits: ['click'],
					template: '<button v-bind="$attrs"><slot /><slot name="icon" /></button>',
				},
				NcIconSvgWrapper: true,
				NcListItem: {
					template: '<li><slot name="name" /><slot name="extra-actions" /></li>',
				},
			},
		},
	}) as IpWrapper

	beforeEach(() => {
		wrapper?.unmount()
		wrapper = null
	})

	it('hides itself when IP evidence is absent', () => {
		wrapper = createWrapper(null)
		expect(wrapper.vm.hasContent).toBe(false)
		expect(wrapper.text()).not.toContain('IP-based approximate location')
	})

	it('shows resolved fields without ranking them as authoritative', async () => {
		wrapper = createWrapper({
			status: 'resolved',
			sourceIp: '203.0.113.10',
			country: 'Brazil',
			region: 'São Paulo',
			city: 'São Paulo',
			latitude: -23.55,
			longitude: -46.63,
			accuracyRadius: 20,
		})

		wrapper.vm.open = true
		await wrapper.vm.$nextTick()

		expect(wrapper.text()).toContain('Approximate location stored')
		expect(wrapper.text()).toContain('Source IP:')
		expect(wrapper.text()).toContain('203.0.113.10')
		expect(wrapper.text()).toContain('Country:')
		expect(wrapper.text()).toContain('Brazil')
		expect(wrapper.text()).toContain('Latitude / longitude:')
		expect(wrapper.text()).toContain('Accuracy radius:')
		expect(wrapper.text()).toContain('Approximate location. VPNs, proxies and mobile networks can affect this result.')
		expect(wrapper.text()).not.toContain('match')
		expect(wrapper.text()).not.toContain('mismatch')
	})

	it('omits optional fields that the backend did not return', async () => {
		wrapper = createWrapper({
			status: 'resolved',
			country: 'Brazil',
		})
		wrapper.vm.open = true
		await wrapper.vm.$nextTick()

		expect(wrapper.text()).toContain('Country:')
		expect(wrapper.text()).not.toContain('Source IP:')
		expect(wrapper.text()).not.toContain('City:')
		expect(wrapper.text()).not.toContain('Latitude / longitude:')
	})

	it('shows not_found without treating the signature as invalid', async () => {
		wrapper = createWrapper({
			status: 'not_found',
			sourceIp: '203.0.113.10',
		})
		wrapper.vm.open = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.statusText).toBe('Approximate location not found')
		expect(wrapper.text()).toContain('203.0.113.10')
		expect(wrapper.text()).not.toContain('invalid')
	})

	it('shows unavailable without backend reason codes', async () => {
		wrapper = createWrapper({
			status: 'unavailable',
			reason: 'database_not_ready',
		})
		wrapper.vm.open = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.statusText).toBe('Approximate location unavailable')
		expect(wrapper.text()).not.toContain('database_not_ready')
		expect(wrapper.text()).not.toContain('The GeoIP database was not ready.')
		expect(wrapper.text()).not.toContain('invalid')
	})
})
