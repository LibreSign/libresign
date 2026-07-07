<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="policy-workbench__category-nav-sticky">
		<div
			:ref="chipsScrollerRef"
			class="policy-workbench__category-nav"
			:class="{ 'policy-workbench__category-nav--rtl': isRtl }"
			role="navigation"
			:aria-label="jumpToSettingsCategoryAriaLabel">
			<button
				v-for="category in sections"
				:key="category.key"
				type="button"
				class="policy-workbench__category-chip"
				:class="{ 'policy-workbench__category-chip--active': activeCategory === category.key }"
				:aria-current="activeCategory === category.key ? 'location' : undefined"
				:aria-label="goToCategoryAriaLabel(category.label)"
				@click="emitNavigate(category.key, $event)"
				@keydown.enter.prevent="emitNavigate(category.key)"
				@keydown.space.prevent="emitNavigate(category.key)">
				<NcChip :text="category.label" no-close />
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcChip from '@nextcloud/vue/components/NcChip'
import type { VNodeRef } from 'vue'

import type { CatalogCategorySection } from '../composables/useCatalogPresentation'
import type { RealPolicySettingCategory } from '../../settings/realTypes'

defineOptions({
	name: 'CatalogCategoryNavigation',
})

const emit = defineEmits<{
	navigate: [category: RealPolicySettingCategory, event?: MouseEvent]
}>()

withDefaults(defineProps<{
	sections: CatalogCategorySection[]
	activeCategory: RealPolicySettingCategory | null
	isRtl: boolean
	chipsScrollerRef?: VNodeRef | null
}>(), {
	chipsScrollerRef: null,
})

// TRANSLATORS Aria label for the navigation region used to jump between settings categories.
const jumpToSettingsCategoryAriaLabel = t('libresign', 'Jump to settings category')
// TRANSLATORS Aria label for a category navigation chip; {category} is the visible category name.
const goToCategoryAriaLabel = (category: string) => t('libresign', 'Go to {category}', { category })

function emitNavigate(category: RealPolicySettingCategory, event?: MouseEvent) {
	emit('navigate', category, event)
}
</script>

<style scoped lang="scss">
.policy-workbench {
	&__category-nav-sticky {
		position: sticky;
		top: 0.5rem;
		z-index: 4;
		margin-top: 0.55rem;
		margin-bottom: 1.15rem;
		padding: 0.45rem 0;
		background: color-mix(in srgb, var(--color-main-background) 92%, transparent);
		backdrop-filter: blur(6px);
		border-radius: 12px;
		border: 1px solid color-mix(in srgb, var(--color-border) 58%, transparent);
		box-shadow: 0 6px 18px color-mix(in srgb, var(--color-box-shadow) 10%, transparent);
	}

	&__category-nav {
		display: flex;
		flex-wrap: wrap;
		gap: 0.55rem 0.6rem;
		padding: 0.1rem;
		align-items: center;

		&--rtl {
			direction: rtl;
		}
	}

	&__category-chip {
		appearance: none;
		background: none;
		border: none;
		padding: 0;
		margin: 0;
		cursor: pointer;
		border-radius: 999px;
		position: relative;
		outline: none;

		&::after {
			content: '';
			position: absolute;
			inset-inline: 0.9rem;
			bottom: -0.1rem;
			height: 2px;
			border-radius: 2px;
			background: color-mix(in srgb, var(--color-primary-element) 62%, transparent);
			opacity: 0;
			transform: scaleX(0.4);
			transition: opacity 0.18s ease, transform 0.18s ease;
		}

		:deep(.nc-chip) {
			background: color-mix(in srgb, var(--color-background-dark) 20%, var(--color-main-background));
			border: 1px solid color-mix(in srgb, var(--color-border-maxcontrast) 22%, transparent);
			transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
		}

		&:hover :deep(.nc-chip),
		&:focus-visible :deep(.nc-chip) {
			background: color-mix(in srgb, var(--color-primary-element) 10%, var(--color-main-background));
			border-color: color-mix(in srgb, var(--color-primary-element) 42%, var(--color-border-maxcontrast));
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 12%, transparent);
		}

		&:focus-visible {
			outline: 2px solid color-mix(in srgb, var(--color-primary-element) 65%, white 35%);
			outline-offset: 2px;
		}

		&:hover::after,
		&:focus-visible::after {
			opacity: 0.5;
			transform: scaleX(1);
		}

		&--active :deep(.nc-chip) {
			background: color-mix(in srgb, var(--color-primary-element) 12%, var(--color-main-background));
			border-color: color-mix(in srgb, var(--color-primary-element) 46%, var(--color-border-maxcontrast));
			box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary-element) 18%, transparent);
		}

		&--active::after {
			opacity: 1;
			transform: scaleX(1);
		}
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__category-nav-sticky {
			top: 0.3rem;
		}
	}
}
</style>
