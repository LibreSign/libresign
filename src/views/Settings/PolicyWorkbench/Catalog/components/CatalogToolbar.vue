<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="policy-workbench__catalog-toolbar">
		<div :ref="toolbarRef" class="policy-workbench__catalog-search">
			<NcTextField
				:model-value="modelValue"
				:label="searchSettingsLabel"
				:placeholder="searchSettingsPlaceholder"
				@keydown.esc.prevent="emit('clear-filter')"
				@update:modelValue="onModelValueChange" />
			<div class="policy-workbench__catalog-foot">
				<NcButton
					variant="tertiary"
					class="policy-workbench__clear-filter-button"
					:class="{ 'policy-workbench__clear-filter-button--hidden': !hasActiveFilter }"
					:aria-label="clearSettingsFilterAriaLabel"
					:disabled="!hasActiveFilter"
					:tabindex="hasActiveFilter ? undefined : -1"
					@click="emit('clear-filter')">
					{{ clearFilterButtonLabel }}
				</NcButton>
			</div>
		</div>

		<div class="policy-workbench__catalog-view-switch" role="group" :aria-label="catalogControlsAriaLabel">
			<NcButton
				:aria-label="catalogViewButtonLabel"
				:title="catalogViewButtonLabel"
				:disabled="isSmallViewport"
				class="policy-workbench__catalog-view-button"
				@click="emit('toggle-layout')">
				<template #icon>
					<NcIconSvgWrapper v-if="effectiveCatalogLayout === 'cards'" :path="mdiFormatListBulletedSquare" />
					<NcIconSvgWrapper v-else :path="mdiViewGridOutline" />
				</template>
			</NcButton>

			<NcButton
				:aria-label="catalogCollapseButtonLabel"
				:title="catalogCollapseButtonLabel"
				:disabled="!hasVisibleCategorySections"
				class="policy-workbench__catalog-collapse-button"
				@click="emit('toggle-collapsed')">
				<template #icon>
					<NcIconSvgWrapper v-if="isCatalogCollapsed" :path="mdiChevronDown" />
					<NcIconSvgWrapper v-else :path="mdiChevronUp" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import {
	mdiChevronDown,
	mdiChevronUp,
	mdiFormatListBulletedSquare,
	mdiViewGridOutline,
} from '@mdi/js'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { VNodeRef } from 'vue'

import type { CatalogLayout } from '../composables/useCatalogPresentation'

defineOptions({
	name: 'CatalogToolbar',
})

const emit = defineEmits<{
	'update:modelValue': [value: string]
	'clear-filter': []
	'toggle-layout': []
	'toggle-collapsed': []
}>()

withDefaults(defineProps<{
	modelValue: string
	hasActiveFilter: boolean
	isSmallViewport: boolean
	effectiveCatalogLayout: CatalogLayout
	isCatalogCollapsed: boolean
	catalogViewButtonLabel: string
	catalogCollapseButtonLabel: string
	hasVisibleCategorySections: boolean
	toolbarRef?: VNodeRef | null
}>(), {
	toolbarRef: null,
})

// TRANSLATORS Label for the settings catalog search field.
const searchSettingsLabel = t('libresign', 'Search settings')
// TRANSLATORS Placeholder describing which policy setting fields can be searched.
const searchSettingsPlaceholder = t('libresign', 'Search by setting name, summary, description, or context')
// TRANSLATORS Aria label for the button that clears the current settings catalog search.
const clearSettingsFilterAriaLabel = t('libresign', 'Clear settings filter')
// TRANSLATORS Button label to clear the current search/filter text in the settings catalog.
const clearFilterButtonLabel = t('libresign', 'Clear filter')
// TRANSLATORS Aria label for the toolbar group containing catalog layout and collapse controls.
const catalogControlsAriaLabel = t('libresign', 'Catalog controls')

function onModelValueChange(value: string | number) {
	emit('update:modelValue', String(value ?? ''))
}
</script>

<style scoped lang="scss">
.policy-workbench {
	&__catalog-toolbar {
		margin-top: 1.1rem;
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 0.75rem;
		align-items: start;
	}

	&__catalog-search {
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	&__catalog-foot {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.75rem;

		:deep(.button-vue) {
			white-space: nowrap;
		}
	}

	&__clear-filter-button {
		&--hidden {
			visibility: hidden;
			pointer-events: none;
		}
	}

	&__catalog-view-switch {
		display: flex;
		gap: 0.6rem;
		flex-wrap: wrap;
		justify-content: flex-end;

		:deep(.button-vue) {
			min-width: var(--clickable-area-small);
		}
	}

	&__catalog-view-button {
		:deep(.button-vue__text) {
			display: none;
		}
	}

	&__catalog-collapse-button {
		:deep(.button-vue__text) {
			display: none;
		}
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__catalog-toolbar {
			display: flex;
			flex-direction: column;
			align-items: stretch;
		}

		&__catalog-view-switch {
			justify-content: flex-start;

			:deep(.button-vue) {
				justify-content: center;
			}
		}

		&__catalog-foot {
			flex-direction: column;
			align-items: flex-start;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}
	}
}
</style>
