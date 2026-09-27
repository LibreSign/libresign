/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { useUserConfigStore } from '../../../../../store/userconfig.js'
import type { RealPolicySettingCategory } from '../../settings/realTypes'

const CATALOG_LAYOUT_CONFIG_KEY = 'policy_workbench_catalog_compact_view'
const CATALOG_COLLAPSED_CONFIG_KEY = 'policy_workbench_catalog_collapsed'
const CATALOG_SECTION_COLLAPSED_CONFIG_KEY = 'policy_workbench_category_collapsed_state'
const CATEGORY_ORDER: RealPolicySettingCategory[] = [
	'who-can-sign',
	'how-signing-works',
	'signer-experience',
	'what-gets-recorded',
	'signer-geolocation',
	'time-and-limits',
	'trust-and-verification',
	'system-behavior',
]

function createCategoryCollapsedState(collapsed: boolean): Record<RealPolicySettingCategory, boolean> {
	return {
		'who-can-sign': collapsed,
		'how-signing-works': collapsed,
		'signer-experience': collapsed,
		'what-gets-recorded': collapsed,
		'signer-geolocation': collapsed,
		'time-and-limits': collapsed,
		'trust-and-verification': collapsed,
		'system-behavior': collapsed,
	}
}

export function useCatalogState() {
	const userConfigStore = useUserConfigStore()
	const settingsFilter = ref('')
	const isSmallViewport = ref(false)
	const catalogLayout = ref<'cards' | 'compact'>('cards')
	const isCatalogCollapsed = ref(false)
	const categoryCollapsedState = ref<Record<RealPolicySettingCategory, boolean>>(createCategoryCollapsedState(false))

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
		if (!config || typeof config !== 'object') {
			return createCategoryCollapsedState(false)
		}

		const result = createCategoryCollapsedState(false)

		for (const category of CATEGORY_ORDER) {
			if (category in config) {
				const value = config[category]
				result[category] = Boolean(value)
			}
		}

		return result
	}

	function setAllCategoriesCollapsed(collapsed: boolean) {
		categoryCollapsedState.value = createCategoryCollapsedState(collapsed)
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
