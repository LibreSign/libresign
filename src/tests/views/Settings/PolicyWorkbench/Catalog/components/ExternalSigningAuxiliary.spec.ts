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
		key: 'signature_flow',
		title: 'Signing order',
		context: null,
		description: 'Signing order description',
		defaultSummary: 'Instance default',
		groupCount: 0,
		userCount: 0,
		everyoneCount: 0,
	}],
}

describe('External signing auxiliary placement', () => {
	it('keeps auxiliary administration in the workbench layout without creating a policy tile', async () => {
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
			slots: {
				auxiliary: '<aside data-cy="auxiliary-provider">External signing provider</aside>',
			},
			global: {
				stubs: {
					NcButton: true,
					NcIconSvgWrapper: true,
				},
			},
		})

		expect(wrapper.find('.policy-workbench__settings-grid > [data-cy="auxiliary-provider"]').exists()).toBe(true)
		expect(wrapper.findAll('.policy-workbench__setting-tile')).toHaveLength(1)
		await wrapper.setProps({ layout: 'compact' })
		expect(wrapper.find('.policy-workbench__settings-list > [data-cy="auxiliary-provider"]').exists()).toBe(true)
		expect(wrapper.findAll('.policy-workbench__settings-row')).toHaveLength(1)
		await wrapper.setProps({ category: { ...category, key: 'trust-and-verification' } })
		expect(wrapper.find('[data-cy="auxiliary-provider"]').exists()).toBe(false)
	})
})
