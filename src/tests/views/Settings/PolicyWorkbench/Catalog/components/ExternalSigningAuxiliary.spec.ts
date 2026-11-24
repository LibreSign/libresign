/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import CatalogCategorySection from '../../../../../../views/Settings/PolicyWorkbench/Catalog/components/CatalogCategorySection.vue'
import type { CatalogCategorySection as Category } from '../../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogPresentation'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const category: Category = {
	id: 'policy-category-how-signing-works',
	key: 'how-signing-works',
	label: 'How signing works',
	summaries: [{
		key: 'external_signing_enabled',
		title: 'External signing providers',
		context: null,
		description: 'Control external signing access',
		defaultSummary: 'Disabled',
		groupCount: 0,
		userCount: 0,
		everyoneCount: 0,
	}],
}

describe('External signing policy placement', () => {
	it('uses the same policy tile, configuration action, and compact row as other policies', async () => {
		const wrapper = mount(CatalogCategorySection, {
			props: {
				category,
				layout: 'cards',
				isActive: true,
				isExpanded: true,
				highlightText: (text: string) => text,
				hasActiveOverrides: () => false,
				resolveDefaultStatLabel: () => 'Default',
				resolveOverridesStatLabel: () => 'Custom rules',
				formatOverrideSummary: () => 'none',
			},
			global: {
				stubs: {
					NcButton: { template: '<button><slot /></button>' },
					NcIconSvgWrapper: true,
				},
			},
		})
		expect(wrapper.findAll('.policy-workbench__setting-tile')).toHaveLength(1)
		expect(wrapper.text()).toContain('External signing providers')
		expect(wrapper.find('.policy-workbench__setting-tile').attributes('tabindex')).toBe('0')
		expect(wrapper.find('.policy-workbench__setting-footer .policy-workbench__manage-button').exists()).toBe(true)

		await wrapper.setProps({ layout: 'compact' })
		expect(wrapper.findAll('.policy-workbench__settings-row')).toHaveLength(1)
		expect(wrapper.find('.policy-workbench__settings-row').text()).toContain('External signing providers')
	})
})
