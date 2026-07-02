/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import HeadingMenu from '../../../components/markdown-editor/HeadingMenu.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

describe('HeadingMenu', () => {
	function createWrapper() {
		return mount(HeadingMenu, {
			global: {
				stubs: {
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						template: '<span class="nc-icon-svg-wrapper-stub" />',
					},
				},
			},
		})
	}

	it('renders Paragraph and heading options H1-H6', async () => {
		const wrapper = createWrapper()
		await wrapper.find('.markdown-heading-menu__toggle').trigger('click')

		const text = wrapper.text()
		expect(text).toContain('Paragraph')
		expect(text).toMatch(/H1\s*Heading\s*1/)
		expect(text).toMatch(/H2\s*Heading\s*2/)
		expect(text).toMatch(/H3\s*Heading\s*3/)
		expect(text).toMatch(/H4\s*Heading\s*4/)
		expect(text).toMatch(/H5\s*Heading\s*5/)
		expect(text).toMatch(/H6\s*Heading\s*6/)
	})

	it('emits clear-heading when paragraph is clicked', async () => {
		const wrapper = createWrapper()
		await wrapper.find('.markdown-heading-menu__toggle').trigger('click')
		const buttons = wrapper.findAll('.markdown-heading-menu__item')

		await buttons[0].trigger('click')
		expect(wrapper.emitted('clear-heading')).toBeTruthy()
	})

	it('emits apply-heading with correct levels', async () => {
		const wrapper = createWrapper()
		await wrapper.find('.markdown-heading-menu__toggle').trigger('click')
		const buttons = wrapper.findAll('.markdown-heading-menu__item')

		for (let i = 1; i <= 6; i++) {
			await buttons[i].trigger('click')
		}

		const emitted = wrapper.emitted('apply-heading') ?? []
		expect(emitted).toEqual([[1], [2], [3], [4], [5], [6]])
	})
})
