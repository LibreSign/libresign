/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { getGeoIpConfig } from '../../../../../../services/geoip'
import SignerIpGeolocationDependencySummary from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-ip-geolocation/SignerIpGeolocationDependencySummary.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())
vi.mock('../../../../../../services/geoip', () => ({
	getGeoIpConfig: vi.fn(),
	saveGeoIpConfig: vi.fn(),
}))
vi.mock('../../../../../../store/policies', () => ({
	usePoliciesStore: () => ({
		getPolicy: () => ({ effectiveValue: { mode: 'disabled' } }),
	}),
}))

describe('SignerIpGeolocationDependencySummary.vue', () => {
	beforeEach(() => {
		vi.mocked(getGeoIpConfig).mockReset()
		vi.mocked(getGeoIpConfig).mockResolvedValue({
			path: null,
			status: 'not_configured',
		})
	})

	const sharedStubs = {
		NcButton: {
			template: '<button type="button"><slot /></button>',
		},
		NcNoteCard: { template: '<div class="note"><slot /></div>' },
	}

	it('shows compact GeoIP status and Configure next to it', async () => {
		const wrapper = mount(SignerIpGeolocationDependencySummary, {
			props: {
				canConfigure: true,
			},
			global: {
				stubs: sharedStubs,
			},
		})

		await flushPromises()

		expect(wrapper.find('[data-cy="geoip-database-dependency"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('GeoIP database:')
		expect(wrapper.text()).toContain('Not configured')
		expect(wrapper.text()).toContain('Configure')
	})

	it('emits configure when Configure is clicked', async () => {
		const wrapper = mount(SignerIpGeolocationDependencySummary, {
			props: {
				canConfigure: true,
			},
			global: {
				stubs: sharedStubs,
			},
		})

		await flushPromises()
		await wrapper.get('button').trigger('click')

		expect(wrapper.emitted('configure')).toHaveLength(1)
	})

	it('hides Configure when the actor cannot edit instance GeoIP settings', async () => {
		const wrapper = mount(SignerIpGeolocationDependencySummary, {
			props: {
				canConfigure: false,
			},
			global: {
				stubs: sharedStubs,
			},
		})

		await flushPromises()

		expect(getGeoIpConfig).not.toHaveBeenCalled()
		expect(wrapper.text()).not.toContain('GeoIP database:')
		expect(wrapper.text()).not.toContain('Loading …')
		expect(wrapper.text()).not.toContain('Configure')
		expect(wrapper.text()).toContain('instance configuration')
	})
})
