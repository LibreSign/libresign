<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr v-show="haveFiles || filtersStore.activeChips.length > 0">
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

<script>
import { useFilesStore } from '../../store/files.js'
import { useFiltersStore } from '../../store/filters.js'

export default {
	name: 'FilesListTableFooter',
	setup() {
		const filesStore = useFilesStore()
		const filtersStore = useFiltersStore()
		return {
			filesStore,
			filtersStore,
		}
	},
	computed: {
		totalFiles() {
			return Object.keys(this.filesStore.files).length
		},
		summary() {
			const fileCount = this.totalFiles
			if (fileCount === 1) {
				return t('libresign', '1 file')
			}
			return t('libresign', '{fileCount} files', { fileCount })
		},
		haveFiles() {
			const fileCount = this.totalFiles
			if (this.filesStore.loading) {
				return false
			}
			return fileCount > 0
		},
	},
}
</script>

<style scoped lang="scss">
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
