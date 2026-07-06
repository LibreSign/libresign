/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import CatalogToolbar from '../../../../../../views/Settings/PolicyWorkbench/Catalog/components/CatalogToolbar.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

function mountToolbar(overrides: Record<string, unknown> = {}) {
	return mount(CatalogToolbar, {
		props: {
			modelValue: '',
			hasActiveFilter: false,
			isSmallViewport: false,
			effectiveCatalogLayout: 'cards',
			isCatalogCollapsed: false,
			catalogViewButtonLabel: 'Switch to compact view',
			catalogCollapseButtonLabel: 'Collapse settings categories',
			hasVisibleCategorySections: true,
			...overrides,
		},
		global: {
			stubs: {
				NcTextField: {
					props: ['modelValue'],
					template: '<label><input class="toolbar-search" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
				},
				NcButton: {
					emits: ['click'],
					template: '<button :class="$attrs.class" :aria-label="$attrs[\'aria-label\']" :title="$attrs.title" :disabled="$attrs.disabled" @click="$emit(\'click\', $event)"><slot /><slot name="icon" /></button>',
				},
				NcIconSvgWrapper: {
					props: ['path'],
					template: '<span class="icon-stub" :data-path="path" />',
				},
			},
		},
	})
}

describe('CatalogToolbar.vue', () => {
	it('emits filter updates and clear action', async () => {
		const wrapper = mountToolbar({
			modelValue: 'signature',
			hasActiveFilter: true,
		})

		await wrapper.find('.toolbar-search').setValue('flow')
		expect(wrapper.emitted('update:modelValue')).toEqual([['flow']])

		await wrapper.find('.policy-workbench__clear-filter-button').trigger('click')
		expect(wrapper.emitted('clear-filter')).toHaveLength(1)
	})

	it('emits layout and collapse actions from toolbar controls', async () => {
		const wrapper = mountToolbar()

		await wrapper.find('.policy-workbench__catalog-view-button').trigger('click')
		await wrapper.find('.policy-workbench__catalog-collapse-button').trigger('click')

		expect(wrapper.emitted('toggle-layout')).toHaveLength(1)
		expect(wrapper.emitted('toggle-collapsed')).toHaveLength(1)
	})
})
