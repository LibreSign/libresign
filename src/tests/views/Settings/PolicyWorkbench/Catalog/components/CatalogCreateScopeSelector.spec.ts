/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import CatalogCreateScopeSelector from '../../../../../../views/Settings/PolicyWorkbench/Catalog/components/CatalogCreateScopeSelector.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

describe('CatalogCreateScopeSelector.vue', () => {
	it('renders options and emits the selected scope for enabled entries', async () => {
		const wrapper = mount(CatalogCreateScopeSelector, {
			props: {
				options: [
					{ scope: 'user', label: 'Account', description: 'Affects a specific account', disabled: false },
					{ scope: 'group', label: 'Group', description: 'Affects all accounts in a group', disabled: true },
				],
				selectedScope: null,
				notes: [
					{ scope: 'group', label: 'Group', reason: 'Blocked by inherited rule' },
				],
			},
			global: {
				stubs: {
					NcIconSvgWrapper: {
						props: ['path'],
						template: '<span class="icon-stub" :data-path="path" />',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Choose the rule type to continue.')
		expect(wrapper.text()).toContain('Blocked by inherited rule')

		const buttons = wrapper.findAll('.policy-workbench__create-scope-option')
		expect(buttons).toHaveLength(2)
		expect(buttons[1]?.attributes('disabled')).toBeDefined()

		await buttons[0]!.trigger('click')
		expect(wrapper.emitted('select-scope')).toEqual([['user']])
	})
})
