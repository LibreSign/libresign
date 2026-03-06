/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import External from '../External.vue'

describe('External.vue', () => {
	it('renders the router view', () => {
		const wrapper = mount(External, {
			global: {
				stubs: {
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.router-view').exists()).toBe(true)
	})
})