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
		variant="tertiary"
		@click="filesSortingStore.toggleSortBy(mode)">
		<template #icon>
			<NcIconSvgWrapper :path="mdiMenuUp" v-if="filesSortingStore.sortingMode !== mode || filesSortingStore.sortingDirection === 'asc'" class="files-list__column-sort-button-icon" />
			<NcIconSvgWrapper :path="mdiMenuDown" v-else class="files-list__column-sort-button-icon" />
		</template>
		<span class="files-list__column-sort-button-text">{{ name }}</span>
	</NcButton>
</template>

<script>


import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiMenuDown,
	mdiMenuUp,
} from '@mdi/js'

import { useFilesSortingStore } from '../../store/filesSorting.js'

export default {
	name: 'FilesListTableHeaderButton',

	components: {
		NcButton,
		NcIconSvgWrapper,
	},
	props: {
		name: {
			type: String,
			required: true,
	},
		mode: {
			type: String,
			required: true,
	},
	},

	setup() {
		const filesSortingStore = useFilesSortingStore()
		return {
			filesSortingStore,
			mdiMenuDown,
			mdiMenuUp,}
	},
}
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

	&-icon {
		color: var(--color-text-maxcontrast);
		opacity: 0;
		transition: opacity var(--animation-quick);
		inset-inline-start: -10px;
	}

	&--size &-icon {
		inset-inline-start: 10px;
	}

	&--active &-icon,
	&:hover &-icon,
	&:focus &-icon,
	&:active &-icon {
		opacity: 1;
	}
}
</style>
