/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'

type CrudScope = 'system' | 'group' | 'user'

type CrudRow = {
	key: string,
	ruleId: string | null,
	scope: CrudScope,
	targetLabel: string,
	valueLabel: string,
	canRemove: boolean,
}

type PolicyRuleLike = {
	id: string,
	targetId?: string | null,
	value: unknown,
}

type CatalogStateLike = {
	inheritedSystemRule: { id: string } | null,
	hasGlobalDefault: boolean,
	summary?: { currentBaseValue?: string } | null,
	visibleGroupRules: PolicyRuleLike[],
	visibleUserRules: PolicyRuleLike[],
	resolveTargetLabel: (scope: 'group' | 'user', targetId: string) => string,
}

const CRUD_PAGE_SIZE = 20

export function useCatalogCrudTable(options: {
	state: CatalogStateLike,
	summarizeRuleValue: (value: unknown) => string,
}) {
	const crudSearch = ref('')
	const crudScopeFilter = ref<'all' | CrudScope>('all')
	const crudPage = ref(1)
	const scopeFilterOpen = ref(false)

	function crudScopeLabel(scope: CrudScope) {
		if (scope === 'system') {
			return t('libresign', 'Everyone')
		}

		if (scope === 'group') {
			return t('libresign', 'Group')
		}

		return t('libresign', 'User')
	}

	const filteredCrudRows = computed<CrudRow[]>(() => {
		const rows: CrudRow[] = []
		const systemRule = options.state.inheritedSystemRule
		if (systemRule && options.state.hasGlobalDefault) {
			rows.push({
				key: systemRule.id,
				ruleId: systemRule.id,
				scope: 'system',
				targetLabel: t('libresign', 'Default (everyone)'),
				valueLabel: options.state.summary?.currentBaseValue ?? t('libresign', 'Not configured'),
				canRemove: Boolean(systemRule.id),
			})
		}

		for (const rule of options.state.visibleGroupRules) {
			rows.push({
				key: rule.id,
				ruleId: rule.id,
				scope: 'group',
				targetLabel: options.state.resolveTargetLabel('group', rule.targetId || ''),
				valueLabel: options.summarizeRuleValue(rule.value),
				canRemove: true,
			})
		}

		for (const rule of options.state.visibleUserRules) {
			rows.push({
				key: rule.id,
				ruleId: rule.id,
				scope: 'user',
				targetLabel: options.state.resolveTargetLabel('user', rule.targetId || ''),
				valueLabel: options.summarizeRuleValue(rule.value),
				canRemove: true,
			})
		}

		const scopePriority: Record<CrudScope, number> = {
			user: 0,
			group: 1,
			system: 2,
		}

		rows.sort((left, right) => {
			const priorityDiff = scopePriority[left.scope] - scopePriority[right.scope]
			if (priorityDiff !== 0) {
				return priorityDiff
			}

			return left.targetLabel.localeCompare(right.targetLabel)
		})

		const normalized = crudSearch.value.trim().toLowerCase()

		return rows.filter((row) => {
			if (crudScopeFilter.value !== 'all' && row.scope !== crudScopeFilter.value) {
				return false
			}

			if (!normalized) {
				return true
			}

			const scope = crudScopeLabel(row.scope).toLowerCase()
			return [scope, row.targetLabel.toLowerCase(), row.valueLabel.toLowerCase()]
				.some((value) => value.includes(normalized))
		})
	})

	const crudPageCount = computed(() => Math.max(1, Math.ceil(filteredCrudRows.value.length / CRUD_PAGE_SIZE)))
	const pagedCrudRows = computed(() => {
		if (crudPage.value > crudPageCount.value) {
			crudPage.value = crudPageCount.value
		}

		const start = (crudPage.value - 1) * CRUD_PAGE_SIZE
		return filteredCrudRows.value.slice(start, start + CRUD_PAGE_SIZE)
	})

	const activeScopeFilterChip = computed(() => {
		if (crudScopeFilter.value === 'all') {
			return ''
		}

		return t('libresign', 'Scope: {scope}', {
			scope: crudScopeLabel(crudScopeFilter.value),
		})
	})

	function onCrudSearchChange(value: string | number) {
		crudSearch.value = String(value ?? '')
		crudPage.value = 1
	}

	function setCrudScopeFilter(value: 'all' | CrudScope, selected: boolean) {
		if (!selected) {
			return
		}

		crudScopeFilter.value = value
		crudPage.value = 1
		scopeFilterOpen.value = false
	}

	return {
		crudSearch,
		crudScopeFilter,
		crudPage,
		scopeFilterOpen,
		filteredCrudRows,
		crudPageCount,
		pagedCrudRows,
		activeScopeFilterChip,
		crudScopeLabel,
		onCrudSearchChange,
		setCrudScopeFilter,
	}
}
