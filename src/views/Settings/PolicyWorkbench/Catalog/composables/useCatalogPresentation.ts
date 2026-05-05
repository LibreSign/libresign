/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from 'vue'
import type { ComputedRef, Ref } from 'vue'
import { t } from '@nextcloud/l10n'

import { realDefinitions } from '../../settings/realDefinitions'
import type { RealPolicySettingCategory } from '../../settings/realTypes'

type CatalogLayout = 'cards' | 'compact'

type SettingSummary = {
	key: string,
	title: string,
	context?: string | null,
	description: string,
	defaultSummary: string,
}

const CATEGORY_ORDER: RealPolicySettingCategory[] = [
	'who-can-sign',
	'how-signing-works',
	'signer-experience',
	'what-gets-recorded',
	'time-and-limits',
	'trust-and-verification',
	'system-behavior',
]

function categoryLabel(category: RealPolicySettingCategory): string {
	switch (category) {
	case 'who-can-sign':
		return t('libresign', 'Who can sign documents')
	case 'how-signing-works':
		return t('libresign', 'How signing works')
	case 'signer-experience':
		return t('libresign', 'What the signer sees')
	case 'what-gets-recorded':
		return t('libresign', 'What gets recorded')
	case 'time-and-limits':
		return t('libresign', 'Time and limits')
	case 'trust-and-verification':
		return t('libresign', 'Trust and verification')
	case 'system-behavior':
		return t('libresign', 'System behavior')
	default:
		return t('libresign', 'Other')
	}
}

export function useCatalogPresentation(options: {
	visibleSettingSummaries: Ref<SettingSummary[]> | ComputedRef<SettingSummary[]>,
	settingsFilter: Ref<string>,
	catalogLayout: Ref<CatalogLayout>,
	isCatalogCollapsed: Ref<boolean>,
	isSmallViewport: Ref<boolean>,
}) {
	const categoryBySettingKey = computed<Record<string, RealPolicySettingCategory>>(() => {
		const map: Record<string, RealPolicySettingCategory> = {}
		for (const definition of Object.values(realDefinitions)) {
			map[definition.key] = definition.category ?? 'system-behavior'
		}

		return map
	})

	const filteredSettingSummaries = computed(() => {
		const normalized = options.settingsFilter.value.trim().toLowerCase()
		if (!normalized) {
			return options.visibleSettingSummaries.value
		}

		return options.visibleSettingSummaries.value.filter((summary) => {
			return [summary.title, summary.context ?? '', summary.description, summary.defaultSummary]
				.some((value) => value.toLowerCase().includes(normalized))
		})
	})

	const visibleCategorySections = computed<Array<{
		key: RealPolicySettingCategory,
		id: string,
		label: string,
		summaries: typeof filteredSettingSummaries.value,
	}>>(() => {
		const grouped = new Map<RealPolicySettingCategory, typeof filteredSettingSummaries.value>()
		for (const category of CATEGORY_ORDER) {
			grouped.set(category, [])
		}

		for (const summary of filteredSettingSummaries.value) {
			const category = categoryBySettingKey.value[summary.key] ?? 'system-behavior'
			grouped.get(category)?.push(summary)
		}

		return CATEGORY_ORDER
			.map((category) => ({
				key: category,
				id: `policy-category-${category}`,
				label: categoryLabel(category),
				summaries: grouped.get(category) ?? [],
			}))
			.filter((category) => category.summaries.length > 0)
	})

	const effectiveCatalogLayout = computed<CatalogLayout>(() => options.isSmallViewport.value ? 'cards' : options.catalogLayout.value)
	const hasActiveFilter = computed(() => options.settingsFilter.value.trim().length > 0)
	const hasVisibleCategorySections = computed(() => visibleCategorySections.value.length > 0)
	const showCategoryNavigation = computed(() => hasVisibleCategorySections.value)
	const catalogViewButtonLabel = computed(() => {
		return effectiveCatalogLayout.value === 'cards'
			? t('libresign', 'Switch to compact view')
			: t('libresign', 'Switch to card view')
	})
	const catalogCollapseButtonLabel = computed(() => {
		return options.isCatalogCollapsed.value
			? t('libresign', 'Expand settings categories')
			: t('libresign', 'Collapse settings categories')
	})

	return {
		filteredSettingSummaries,
		visibleCategorySections,
		effectiveCatalogLayout,
		hasActiveFilter,
		hasVisibleCategorySections,
		showCategoryNavigation,
		catalogViewButtonLabel,
		catalogCollapseButtonLabel,
	}
}
