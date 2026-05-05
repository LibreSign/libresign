/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { useUserConfigStore } from '../../../../store/userconfig.js'
import type { RealPolicySettingCategory } from '../settings/realTypes'

const CATALOG_LAYOUT_CONFIG_KEY = 'policy_workbench_catalog_compact_view'
const CATALOG_COLLAPSED_CONFIG_KEY = 'policy_workbench_catalog_collapsed'
const CATEGORY_ORDER: RealPolicySettingCategory[] = [
	'who-can-sign',
	'how-signing-works',
	'signer-experience',
	'what-gets-recorded',
	'time-and-limits',
	'trust-and-verification',
	'system-behavior',
]

export function useCatalogState() {
	const userConfigStore = useUserConfigStore()
	const settingsFilter = ref('')
	const isSmallViewport = ref(false)
	const catalogLayout = ref<'cards' | 'compact'>('cards')
	const isCatalogCollapsed = ref(false)
	const categoryCollapsedState = ref<Record<RealPolicySettingCategory, boolean>>({
		'who-can-sign': false,
		'how-signing-works': false,
		'signer-experience': false,
		'what-gets-recorded': false,
		'time-and-limits': false,
		'trust-and-verification': false,
		'system-behavior': false,
	})

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
		userConfigStore.update({
			[CATALOG_LAYOUT_CONFIG_KEY]: catalogLayout.value === 'compact',
		})
	}

	function toggleCatalogCollapsed() {
		isCatalogCollapsed.value = !isCatalogCollapsed.value
		userConfigStore.update({
			[CATALOG_COLLAPSED_CONFIG_KEY]: isCatalogCollapsed.value,
		})
		syncCatalogCollapsedFromSections()
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
			userConfigStore.update({
				[CATALOG_COLLAPSED_CONFIG_KEY]: allCollapsed,
			})
		}
	}

	function persistCategoryCollapsedState() {
		userConfigStore.update({
			[CATEGORY_ORDER[0] ? `${CATALOG_SECTION_COLLAPSED_CONFIG_KEY}` : 'temp']: categoryCollapsedState.value,
		})
	}

	function isCategoryExpanded(category: RealPolicySettingCategory): boolean {
		return !categoryCollapsedState.value[category]
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
		syncCatalogCollapsedFromSections,
		persistCategoryCollapsedState,
	}
}

const CATALOG_SECTION_COLLAPSED_CONFIG_KEY = 'policy_workbench_category_collapsed_state'
