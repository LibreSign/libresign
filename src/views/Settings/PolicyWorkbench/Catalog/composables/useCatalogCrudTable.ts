/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, nextTick, ref, watch } from 'vue'

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
	canRemove?: boolean,
}

type CatalogStateLike = {
	inheritedSystemRule: { id: string } | null,
	hasGlobalDefault: boolean,
	viewMode: 'system-admin' | 'group-admin',
	summary?: { currentBaseValue?: string } | null,
	visibleGroupRules: PolicyRuleLike[],
	visibleUserRules: PolicyRuleLike[],
	resolveTargetLabel: (scope: 'group' | 'user', targetId: string) => string,
}

const CRUD_PAGE_SIZE = 20

export const useCatalogCrudTable = (options: {
	state: CatalogStateLike,
	summarizeRuleValue: (value: unknown) => string,
}) => {
	const crudSearch = ref('')
	const crudScopeFilter = ref<'all' | CrudScope>('all')
	const visibleCrudCount = ref(CRUD_PAGE_SIZE)
	const loadingMoreCrudRows = ref(false)
	const selectedCrudRuleIds = ref<Set<string>>(new Set())
	const scopeFilterOpen = ref(false)

	const crudScopeLabel = (scope: CrudScope) => {
		if (scope === 'system') {
			return t('libresign', 'Everyone')
		}

		if (scope === 'group') {
			return t('libresign', 'Group')
		}

		return t('libresign', 'Account')
	}

	const filteredCrudRows = computed<CrudRow[]>(() => {
		const rows: CrudRow[] = []
		const systemRule = options.state.inheritedSystemRule
		if (systemRule && options.state.hasGlobalDefault && options.state.viewMode === 'system-admin') {
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
				canRemove: rule.canRemove ?? true,
			})
		}

		for (const rule of options.state.visibleUserRules) {
			rows.push({
				key: rule.id,
				ruleId: rule.id,
				scope: 'user',
				targetLabel: options.state.resolveTargetLabel('user', rule.targetId || ''),
				valueLabel: options.summarizeRuleValue(rule.value),
				canRemove: rule.canRemove ?? true,
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

	const displayedCrudRows = computed(() => filteredCrudRows.value.slice(0, visibleCrudCount.value))
	const selectableDisplayedCrudRows = computed(() => displayedCrudRows.value.filter((row) => row.canRemove && row.ruleId))
	const hasMoreCrudRows = computed(() => visibleCrudCount.value < filteredCrudRows.value.length)
	const selectedCrudRowsCount = computed(() => filteredCrudRows.value.filter((row) => row.canRemove && selectedCrudRuleIds.value.has(row.ruleId ?? row.key)).length)
	const selectedVisibleCrudRowsCount = computed(() => displayedCrudRows.value.filter((row) => row.canRemove && selectedCrudRuleIds.value.has(row.ruleId ?? row.key)).length)
	const allVisibleCrudRowsSelected = computed(() => selectableDisplayedCrudRows.value.length > 0 && selectedVisibleCrudRowsCount.value === selectableDisplayedCrudRows.value.length)
	const hasSelectableVisibleCrudRows = computed(() => selectableDisplayedCrudRows.value.length > 0)

	const activeScopeFilterChip = computed(() => {
		if (crudScopeFilter.value === 'all') {
			return ''
		}

		return t('libresign', 'Scope: {scope}', {
			scope: crudScopeLabel(crudScopeFilter.value),
		})
	})

	const onCrudSearchChange = (value: string | number) => {
		crudSearch.value = String(value ?? '')
		visibleCrudCount.value = CRUD_PAGE_SIZE
		selectedCrudRuleIds.value = new Set()
	}

	const setCrudScopeFilter = (value: 'all' | CrudScope, selected: boolean) => {
		if (!selected) {
			return
		}

		crudScopeFilter.value = value
		visibleCrudCount.value = CRUD_PAGE_SIZE
		selectedCrudRuleIds.value = new Set()
		scopeFilterOpen.value = false
	}

	const clearCrudSelection = () => {
		selectedCrudRuleIds.value = new Set()
	}

	const isCrudRowSelected = (ruleId: string) => {
		return selectedCrudRuleIds.value.has(ruleId)
	}

	const toggleCrudRowSelection = (ruleId: string, selected: boolean) => {
		const row = filteredCrudRows.value.find((candidate) => (candidate.ruleId ?? candidate.key) === ruleId)
		if (!row?.canRemove) {
			return
		}

		const nextSelection = new Set(selectedCrudRuleIds.value)
		if (selected) {
			nextSelection.add(ruleId)
		} else {
			nextSelection.delete(ruleId)
		}
		selectedCrudRuleIds.value = nextSelection
	}

	const toggleVisibleCrudRowsSelection = (selected: boolean) => {
		const nextSelection = new Set(selectedCrudRuleIds.value)
		for (const row of displayedCrudRows.value) {
			if (!row.canRemove) {
				continue
			}

			const rowId = row.ruleId ?? row.key
			if (selected) {
				nextSelection.add(rowId)
			} else {
				nextSelection.delete(rowId)
			}
		}
		selectedCrudRuleIds.value = nextSelection
	}

	const loadMoreCrudRows = async () => {
		if (loadingMoreCrudRows.value || !hasMoreCrudRows.value) {
			return
		}

		loadingMoreCrudRows.value = true
		await nextTick()
		visibleCrudCount.value = Math.min(visibleCrudCount.value + CRUD_PAGE_SIZE, filteredCrudRows.value.length)
		await nextTick()
		loadingMoreCrudRows.value = false
	}

	watch([crudSearch, crudScopeFilter], () => {
		visibleCrudCount.value = CRUD_PAGE_SIZE
		selectedCrudRuleIds.value = new Set()
	})

	watch(filteredCrudRows, (nextRows) => {
		if (visibleCrudCount.value > nextRows.length) {
			visibleCrudCount.value = Math.max(CRUD_PAGE_SIZE, nextRows.length)
		}

		const nextSelection = new Set<string>()
		for (const row of nextRows) {
			const rowId = row.ruleId ?? row.key
			if (row.canRemove && selectedCrudRuleIds.value.has(rowId)) {
				nextSelection.add(rowId)
			}
		}
		selectedCrudRuleIds.value = nextSelection
	})

	return {
		crudSearch,
		crudScopeFilter,
		scopeFilterOpen,
		filteredCrudRows,
		displayedCrudRows,
		hasMoreCrudRows,
		loadingMoreCrudRows,
		selectedCrudRowsCount,
		allVisibleCrudRowsSelected,
		hasSelectableVisibleCrudRows,
		selectedCrudRuleIds,
		isCrudRowSelected,
		toggleCrudRowSelection,
		toggleVisibleCrudRowsSelection,
		clearCrudSelection,
		loadMoreCrudRows,
		activeScopeFilterChip,
		crudScopeLabel,
		onCrudSearchChange,
		setCrudScopeFilter,
	}
}
