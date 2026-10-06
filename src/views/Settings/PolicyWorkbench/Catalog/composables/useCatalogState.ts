/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { useUserConfigStore } from '../../../../../store/userconfig.js'
import type { RealPolicySettingCategory } from '../../settings/realTypes'
import { CATEGORY_ORDER } from './categoryOrder'

const CATALOG_LAYOUT_CONFIG_KEY = 'policy_workbench_catalog_compact_view'
const CATALOG_COLLAPSED_CONFIG_KEY = 'policy_workbench_catalog_collapsed'
const CATALOG_SECTION_COLLAPSED_CONFIG_KEY = 'policy_workbench_category_collapsed_state'

export function useCatalogState() {
	const userConfigStore = useUserConfigStore()
	const settingsFilter = ref('')
	const isSmallViewport = ref(false)
	const catalogLayout = ref<'cards' | 'compact'>('cards')
	const isCatalogCollapsed = ref(false)
	const categoryCollapsedState = ref<Record<RealPolicySettingCategory, boolean>>(
		Object.fromEntries(CATEGORY_ORDER.map((category) => [category, false])) as Record<RealPolicySettingCategory, boolean>,
	)

	const hasActiveFilter = computed(() => settingsFilter.value.trim().length > 0)
	const effectiveCatalogLayout = computed(() => isSmallViewport.value ? 'cards' : catalogLayout.value)
	const catalogViewButtonLabel = computed(() => {
		return effectiveCatalogLayout.value === 'cards'
			? t('libresign', 'Switch to compact view')
			: t('libresign', 'Switch to card view')
	})
	const catalogCollapseButtonLabel = computed(() => {
		return isCatalogCollapsed.value
			? t('libresign', 'Expand settings categories')
			: t('libresign', 'Collapse settings categories')
	})

	function clearSettingsFilter() {
		settingsFilter.value = ''
	}

	function onSettingsFilterChange(value: string) {
		settingsFilter.value = value
	}

	function toggleCatalogLayout() {
		catalogLayout.value = catalogLayout.value === 'cards' ? 'compact' : 'cards'
		void userConfigStore.update(CATALOG_LAYOUT_CONFIG_KEY, catalogLayout.value === 'compact')
	}

	function toggleCatalogCollapsed() {
		const collapsed = !isCatalogCollapsed.value
		isCatalogCollapsed.value = collapsed
		setAllCategoriesCollapsed(collapsed)
		void userConfigStore.update(CATALOG_COLLAPSED_CONFIG_KEY, collapsed)
		persistCategoryCollapsedState()
	}

	function toggleCategoryCollapsed(category: RealPolicySettingCategory) {
		categoryCollapsedState.value = {
			...categoryCollapsedState.value,
			[category]: !categoryCollapsedState.value[category],
		}
		persistCategoryCollapsedState()
		syncCatalogCollapsedFromSections()
	}

	function syncCatalogCollapsedFromSections() {
		const allCollapsed = CATEGORY_ORDER.every(cat => categoryCollapsedState.value[cat])
		if (allCollapsed !== isCatalogCollapsed.value) {
			isCatalogCollapsed.value = allCollapsed
			void userConfigStore.update(CATALOG_COLLAPSED_CONFIG_KEY, allCollapsed)
		}
	}

	function persistCategoryCollapsedState() {
		void userConfigStore.update(CATALOG_SECTION_COLLAPSED_CONFIG_KEY, categoryCollapsedState.value)
	}

	function isCategoryExpanded(category: RealPolicySettingCategory): boolean {
		return !categoryCollapsedState.value[category]
	}

	function normalizeCategoryCollapsedConfig(config?: Record<string, unknown>): Record<RealPolicySettingCategory, boolean> {
		const result = Object.fromEntries(CATEGORY_ORDER.map((category) => [category, false])) as Record<RealPolicySettingCategory, boolean>
		if (!config || typeof config !== 'object') {
			return result
		}

		for (const category of CATEGORY_ORDER) {
			if (category in config) {
				const value = config[category]
				result[category] = Boolean(value)
			}
		}

		return result
	}

	function setAllCategoriesCollapsed(collapsed: boolean) {
		categoryCollapsedState.value = Object.fromEntries(
			CATEGORY_ORDER.map((category) => [category, collapsed]),
		) as Record<RealPolicySettingCategory, boolean>
	}

	return {
		// State
		settingsFilter,
		isSmallViewport,
		catalogLayout,
		isCatalogCollapsed,
		categoryCollapsedState,
		// Computed
		hasActiveFilter,
		effectiveCatalogLayout,
		catalogViewButtonLabel,
		catalogCollapseButtonLabel,
		// Methods
		clearSettingsFilter,
		onSettingsFilterChange,
		toggleCatalogLayout,
		toggleCatalogCollapsed,
		toggleCategoryCollapsed,
		isCategoryExpanded,
		normalizeCategoryCollapsedConfig,
		setAllCategoriesCollapsed,
		syncCatalogCollapsedFromSections,
		persistCategoryCollapsedState,
	}
}
