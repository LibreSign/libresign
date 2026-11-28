/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import MyCertificates from '../../../views/Certificates/MyCertificates.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/vue/components/NcAppContent', () => ({
	default: {
		name: 'NcAppContent',
		template: '<div class="app-content-stub"><slot /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({
	default: {
		name: 'NcSettingsSection',
		props: ['name', 'description'],
		template: '<section class="settings-section-stub"><h2>{{ name }}</h2><p>{{ description }}</p><slot /></section>',
	},
}))

describe('MyCertificates.vue', () => {
	const createWrapper = () => mount(MyCertificates, {
		global: {
			stubs: {
				ManagePassword: {
					name: 'ManagePassword',
					template: '<div class="manage-password-stub"></div>',
				},
			},
		},
	})

	it('shows a My certificates heading', () => {
		const wrapper = createWrapper()

		expect(wrapper.get('.settings-section-stub h2').text()).toBe('My certificates')
	})

	it('reuses the existing certificate controls instead of duplicating them', () => {
		const wrapper = createWrapper()

		expect(wrapper.findAllComponents({ name: 'ManagePassword' })).toHaveLength(1)
		expect(wrapper.find('.manage-password-stub').exists()).toBe(true)
	})

	it('describes the certificate actions available on the page', () => {
		const wrapper = createWrapper()

		expect(wrapper.get('.settings-section-stub p').text()).toContain('certificate used to sign your documents')
	})

	it('renders inside the app content area', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.app-content-stub .my-certificates').exists()).toBe(true)
	})
})
