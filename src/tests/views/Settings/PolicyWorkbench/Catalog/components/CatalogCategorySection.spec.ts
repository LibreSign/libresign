/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import CatalogCategorySection from '../../../../../../views/Settings/PolicyWorkbench/Catalog/components/CatalogCategorySection.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const category = {
	key: 'how-signing-works',
	id: 'policy-category-how-signing-works',
	label: 'How signing works',
	summaries: [
		{
			key: 'signature_flow',
			title: 'Signing order',
			context: null,
			description: 'Choose whether documents are signed in order or all at once.',
			defaultSummary: 'Sequential',
			groupCount: 1,
			userCount: 2,
			everyoneCount: 0,
		},
	],
} as const

function mountSection(overrides: Record<string, unknown> = {}) {
	return mount(CatalogCategorySection, {
		props: {
			category,
			layout: 'cards',
			isActive: false,
			isExpanded: true,
			highlightText: (value: string) => value,
			hasActiveOverrides: () => true,
			resolveDefaultStatLabel: () => 'Default',
			resolveOverridesStatLabel: () => 'Custom rules',
			formatOverrideSummary: () => '1 groups · 2 accounts',
			...overrides,
		},
		global: {
			stubs: {
				NcButton: {
					emits: ['click'],
					template: '<button :class="$attrs.class" :aria-label="$attrs[\'aria-label\']" @click="$emit(\'click\', $event)"><slot /></button>',
				},
				NcIconSvgWrapper: {
					props: ['path'],
					template: '<span class="icon-stub" :data-path="path" />',
				},
			},
		},
	})
}

describe('CatalogCategorySection.vue', () => {
	it('renders cards and emits category and action events', async () => {
		const wrapper = mountSection()

		expect(wrapper.find('.policy-workbench__setting-tile').exists()).toBe(true)

		await wrapper.find('.policy-workbench__category-toggle').trigger('click')
		expect(wrapper.emitted('toggle-category')).toEqual([['how-signing-works']])

		await wrapper.find('.policy-workbench__manage-button').trigger('click')
		expect(wrapper.emitted('open-from-action')?.[0]?.[0]).toMatchObject({ key: 'signature_flow' })
	})

	it('renders compact rows and hides content when collapsed', async () => {
		const wrapper = mountSection({ layout: 'compact' })

		expect(wrapper.find('.policy-workbench__settings-row').exists()).toBe(true)
		expect(wrapper.find('.policy-workbench__setting-tile').exists()).toBe(false)

		await wrapper.setProps({ isExpanded: false })
		expect(wrapper.find('.policy-workbench__category-content').attributes('style')).toContain('display: none;')
	})
})
