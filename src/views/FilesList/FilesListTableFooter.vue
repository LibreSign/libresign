<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr v-show="haveFiles || activeChips.length > 0">
		<th class="files-list__row-checkbox">
			<!-- TRANSLATORS Label for a table footer which summarizes the columns of the table -->
			<span class="hidden-visually">{{ t('libresign', 'Total rows summary') }}</span>
		</th>

		<!-- Link to file -->
		<td class="files-list__row-name">
			<!-- Icon or preview -->
			<span class="files-list__row-icon" />

			<!-- Summary -->
			<span>{{ summary }}</span>
		</td>

		<!-- Actions -->
		<td class="files-list__row-actions" />
	</tr>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

import { useFilesStore } from '../../store/files.js'
import { useFiltersStore } from '../../store/filters.js'

defineOptions({
	name: 'FilesListTableFooter',
})

const filesStore = useFilesStore()
const filtersStore = useFiltersStore()
const activeChips = computed(() => Array.isArray(filtersStore.activeChips) ? filtersStore.activeChips : [])

const totalFiles = computed(() => Object.keys(filesStore.files).length)

const summary = computed(() => {
	const fileCount = totalFiles.value
	if (fileCount === 1) {
		return t('libresign', '1 file')
	}
	return t('libresign', '{fileCount} files', { fileCount })
})

const haveFiles = computed(() => {
	if (filesStore.loading) {
		return false
	}
	return totalFiles.value > 0
})

defineExpose({
	filesStore,
	filtersStore,
	activeChips,
	totalFiles,
	summary,
	haveFiles,
})
</script>

<style scoped lang="scss">
.hidden-visually {
	position: absolute;
	left: -10000px;
	top: auto;
	width: 1px;
	height: 1px;
	overflow: hidden;
}

tr {
	margin-bottom: max(25vh, var(--body-container-margin));
	border-top: 1px solid var(--color-border);
	// Prevent hover effect on the whole row
	background-color: transparent !important;
	border-bottom: none !important;

	td {
		user-select: none;
		// Make sure the cell colors don't apply to column headers
		color: var(--color-text-maxcontrast) !important;
	}
}
</style>
