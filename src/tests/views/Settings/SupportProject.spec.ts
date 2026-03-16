/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createL10nMock } from '../../testHelpers/l10n.js'

import SupportProject from '../../../views/Settings/SupportProject.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('SupportProject.vue', () => {
	it('renders the three support links with expected targets', () => {
		const wrapper = mount(SupportProject, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcIconSvgWrapper: true,
					NcButton: {
						name: 'NcButton',
						props: ['href', 'target', 'rel', 'variant'],
						template: '<a :href="href" :target="target" :rel="rel"><slot /></a>',
					},
				},
			},
		})

		const links = wrapper.findAll('a')
		expect(links).toHaveLength(3)
		expect(links[0].attributes('href')).toBe('https://github.com/sponsors/libresign')
		expect(links[1].attributes('href')).toBe('https://buy.stripe.com/eVqfZibhx8QO3LseWc2kw00')
		expect(links[2].attributes('href')).toBe('https://libresign.coop')
	})
})
