<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcButton :class="['files-list__column-sort-button', {
			'files-list__column-sort-button--active': filesSortingStore.sortingMode === mode,
			'files-list__column-sort-button--size': filesSortingStore.sortingMode === 'size',
		}]"
		:alignment="mode === 'size' ? 'end' : 'start-reverse'"
		:title="name"
		variant="tertiary"
		@click="filesSortingStore.toggleSortBy(mode)">
		<template #icon>
			<NcIconSvgWrapper
				:path="isAscending ? mdiMenuUp : mdiMenuDown"
				:size="24"
				class="files-list__column-sort-button-icon" />
		</template>
		<span class="files-list__column-sort-button-text">{{ name }}</span>
	</NcButton>
</template>

<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiMenuDown,
	mdiMenuUp,
} from '@mdi/js'
import { computed } from 'vue'

import { useFilesSortingStore } from '../../store/filesSorting.js'

defineOptions({
	name: 'FilesListTableHeaderButton',
})

type FilesSortingStore = {
	sortingMode: string
	sortingDirection: string
	toggleSortBy: (mode: string) => void
}

const props = defineProps<{
	name: string
	mode: string
}>()

const filesSortingStore = useFilesSortingStore() as FilesSortingStore

const isAscending = computed(() => {
	return filesSortingStore.sortingMode !== props.mode
		|| filesSortingStore.sortingDirection === 'asc'
})

defineExpose({
	filesSortingStore,
	isAscending,
	props,
	mdiMenuDown,
	mdiMenuUp,
})
</script>

<style scoped lang="scss">
.files-list__column-sort-button {
	// Compensate for cells margin
	margin: 0 calc(var(--button-padding, var(--cell-margin)) * -1);
	min-width: calc(100% - 3 * var(--cell-margin))!important;

	&-text {
		color: var(--color-text-maxcontrast);
		font-weight: normal;
	}

	:deep(.files-list__column-sort-button-icon) {
		color: var(--color-text-maxcontrast);
		opacity: 0;
		transition: opacity var(--animation-quick);
		inset-inline-start: -10px;
	}

	&--size :deep(.files-list__column-sort-button-icon) {
		inset-inline-start: 10px;
	}

	&--active,
	&:hover,
	&:focus,
	&:active {
		:deep(.files-list__column-sort-button-icon) {
			opacity: 1;
		}
	}
}
</style>
