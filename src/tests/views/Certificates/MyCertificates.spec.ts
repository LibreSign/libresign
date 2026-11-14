/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import MyCertificates from '../../../views/Certificates/MyCertificates.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/vue/components/NcAppContent', () => ({
	default: { name: 'NcAppContent', template: '<div><slot /></div>' },
}))

describe('MyCertificates.vue', () => {
	it('renders my certificates heading and the manage password component', () => {
		const wrapper = mount(MyCertificates, {
			global: {
				stubs: {
					ManagePassword: {
						name: 'ManagePassword',
						template: '<div class="manage-password-stub"></div>',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('My certificates')
		expect(wrapper.find('.manage-password-stub').exists()).toBe(true)
	})
})
