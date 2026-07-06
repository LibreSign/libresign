/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import CatalogCrudRulesTable from '../../../../../../views/Settings/PolicyWorkbench/Catalog/components/CatalogCrudRulesTable.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

function mountCrudTable(overrides: Record<string, unknown> = {}) {
	return mount(CatalogCrudRulesTable, {
		props: {
			crudSearch: '',
			crudScopeFilter: 'all',
			scopeFilterOpen: false,
			displayedCrudRows: [
				{ key: 'group-finance', ruleId: 'group-finance', scope: 'group', targetLabel: 'finance', valueLabel: 'parallel', canRemove: false },
				{ key: 'user-john', ruleId: 'user-john', scope: 'user', targetLabel: 'john', valueLabel: 'parallel', canRemove: true },
			],
			loadingMoreCrudRows: false,
			crudSelectedRowsCount: 0,
			crudAllVisibleRowsSelected: false,
			hasSelectableVisibleCrudRows: true,
			isRemovingRule: false,
			rulesLoading: false,
			hasCreatableScope: true,
			createRuleDisabledReason: '',
			createUserOverrideDisabledReason: '',
			hasActiveCrudFilters: false,
			crudEmptyStateName: 'No rules',
			crudEmptyStateDescription: 'Create one',
			crudEmptyStateIconPath: 'mdi-plus',
			activeScopeFilterChip: '',
			openRuleActionsKey: 'user-john',
			crudScopeLabel: (scope: string) => scope,
			isCrudRowSelected: () => false,
			...overrides,
		},
		global: {
			stubs: {
				NcActionButton: {
					emits: ['click'],
					template: '<button class="nc-action-button" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
				},
				NcActions: {
					props: ['open'],
					template: '<div class="nc-actions"><slot v-if="open" /></div>',
				},
				NcAppNavigationSearch: {
					template: '<div class="search-stub"><slot name="actions" /></div>',
				},
				NcButton: {
					emits: ['click'],
					template: '<button :class="$attrs.class" :aria-label="$attrs[\'aria-label\']" @click="$emit(\'click\', $event)"><slot /><slot name="icon" /></button>',
				},
				NcCheckboxRadioSwitch: {
					props: ['modelValue'],
					template: '<input class="nc-checkbox-radio-switch" type="checkbox" :checked="modelValue" />',
				},
				NcChip: { props: ['text'], template: '<span class="chip-stub">{{ text }}</span>' },
				NcEmptyContent: { template: '<div class="empty-content-stub"><slot /><slot name="icon" /><slot name="action" /></div>' },
				NcIconSvgWrapper: { props: ['path'], template: '<span class="icon-stub" :data-path="path" />' },
				NcLoadingIcon: { template: '<span class="loading-icon-stub" />' },
			},
		},
	})
}

describe('CatalogCrudRulesTable.vue', () => {
	it('hides checkbox and remove action for non-removable rules', () => {
		const wrapper = mountCrudTable()

		const rows = wrapper.findAll('tbody tr')
		expect(rows).toHaveLength(2)

		const nonRemovableRow = rows.find((row) => row.text().includes('finance'))
		expect(nonRemovableRow).toBeDefined()
		expect(nonRemovableRow?.findAll('.nc-checkbox-radio-switch')).toHaveLength(0)
		expect(nonRemovableRow?.find('.policy-workbench__table-select-placeholder').exists()).toBe(true)
		expect(nonRemovableRow?.findAll('.nc-action-button').some((button) => button.text().includes('Remove'))).toBe(false)

		const removableRow = rows.find((row) => row.text().includes('john'))
		expect(removableRow).toBeDefined()
		expect(removableRow?.findAll('.nc-checkbox-radio-switch')).toHaveLength(1)
		expect(removableRow?.findAll('.nc-action-button').some((button) => button.text().includes('Remove'))).toBe(true)
	})
})
