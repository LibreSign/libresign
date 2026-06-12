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
	groupCount?: number,
	userCount?: number,
	everyoneCount?: number,
}

// TRANSLATORS Category heading grouping rules about who is allowed to sign documents.
const categoryWhoCanSignLabel = t('libresign', 'Who can sign documents')
// TRANSLATORS Category heading grouping rules about signing process behavior.
const categoryHowSigningWorksLabel = t('libresign', 'How signing works')
// TRANSLATORS Category heading grouping rules affecting signer-facing experience.
const categorySignerExperienceLabel = t('libresign', 'What the signer sees')
// TRANSLATORS Category heading grouping rules about metadata and evidence recorded during signing.
const categoryWhatGetsRecordedLabel = t('libresign', 'What gets recorded')
// TRANSLATORS Category heading grouping rules about durations, deadlines, and limits.
const categoryTimeAndLimitsLabel = t('libresign', 'Time and limits')
// TRANSLATORS Category heading grouping trust, certificate, and validation behavior.
const categoryTrustAndVerificationLabel = t('libresign', 'Trust and verification')
// TRANSLATORS Category heading grouping infrastructure and system-level behavior.
const categorySystemBehaviorLabel = t('libresign', 'System behavior')
// TRANSLATORS Fallback category heading for settings without a specific mapped category.
const categoryOtherLabel = t('libresign', 'Other')

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
		return categoryWhoCanSignLabel
	case 'how-signing-works':
		return categoryHowSigningWorksLabel
	case 'signer-experience':
		return categorySignerExperienceLabel
	case 'what-gets-recorded':
		return categoryWhatGetsRecordedLabel
	case 'time-and-limits':
		return categoryTimeAndLimitsLabel
	case 'trust-and-verification':
		return categoryTrustAndVerificationLabel
	case 'system-behavior':
		return categorySystemBehaviorLabel
	default:
		return categoryOtherLabel
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
	// TRANSLATORS Button label that switches catalog from cards to compact list layout.
	const switchToCompactViewLabel = t('libresign', 'Switch to compact view')
	// TRANSLATORS Button label that switches catalog from compact list to card layout.
	const switchToCardViewLabel = t('libresign', 'Switch to card view')
	const catalogViewButtonLabel = computed(() => {
		return effectiveCatalogLayout.value === 'cards'
			? switchToCompactViewLabel
			: switchToCardViewLabel
	})
	// TRANSLATORS Button label to expand collapsed settings category navigation.
	const expandSettingsCategoriesLabel = t('libresign', 'Expand settings categories')
	// TRANSLATORS Button label to collapse settings category navigation.
	const collapseSettingsCategoriesLabel = t('libresign', 'Collapse settings categories')
	const catalogCollapseButtonLabel = computed(() => {
		return options.isCatalogCollapsed.value
			? expandSettingsCategoriesLabel
			: collapseSettingsCategoriesLabel
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
