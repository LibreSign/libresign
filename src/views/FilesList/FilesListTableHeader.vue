<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr v-if="filesStore.ordered.length > 0"
		class="files-list__row-head">
		<th class="files-list__column files-list__row-checkbox"
			scope="col"
			@keyup.esc.exact="resetSelection">
			<NcCheckboxRadioSwitch v-bind="selectAllBind" @update:modelValue="onToggleAll" />
		</th>

		<!-- Columns display -->

		<!-- Link to file -->
		<th class="files-list__column files-list__row-name files-list__column--sortable"
			scope="col"
			:aria-sort="ariaSortForMode('name')">
			<!-- Icon or preview -->
			<span class="files-list__row-icon" />

			<!-- Name -->
			<FilesListTableHeaderButton :name="t('libresign', 'Name')" mode="name" />
		</th>

		<!-- Actions -->
		<th class="files-list__row-actions" scope="col" />

		<!-- Custom views columns -->
		<th v-for="column in columns"
			:key="column.id"
			scope="col"
			:class="classForColumn(column)"
			:aria-sort="ariaSortForMode(column.id, !!column.sort)">
			<FilesListTableHeaderButton v-if="!!column.sort" :name="column.title" :mode="column.id" />
			<span v-else>
				{{ column.title }}
			</span>
		</th>
	</tr>
</template>

<script>
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import FilesListTableHeaderButton from './FilesListTableHeaderButton.vue'

import logger from '../../logger.js'
import { useFilesStore } from '../../store/files.js'
import { useFilesSortingStore } from '../../store/filesSorting.js'
import { useSelectionStore } from '../../store/selection.js'

export default {
	name: 'FilesListTableHeader',

	components: {
		NcCheckboxRadioSwitch,
		FilesListTableHeaderButton,
	},

	props: {
		nodes: {
			type: Array,
			required: true,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		const filesSortingStore = useFilesSortingStore()
		const selectionStore = useSelectionStore()
		return {
			t,
			filesStore,
			filesSortingStore,
			selectionStore,
		}
	},
	data() {
		return {
			columns: [
				{
					title: t('libresign', 'Status'),
					id: 'status',
					sort: true,
				},
				{
					title: t('libresign', 'Signers'),
					id: 'signers',
					sort: true,
				},
				{
					title: t('libresign', 'Created at'),
					id: 'created_at',
					sort: true,
				},
			],
		}
	},
	computed: {
		selectAllBind() {
			const label = t('libresign', 'Toggle selection for all files')
			return {
				'aria-label': label,
				'model-value': this.isAllSelected,
				indeterminate: this.isSomeSelected,
				title: label,
			}
		},
		selectedNodes() {
			return this.selectionStore.selected
		},
		isAllSelected() {
			return this.selectedNodes.length === this.filesStore.ordered.length
				&& this.filesStore.ordered.length > 0
		},
		isNoneSelected() {
			return this.selectedNodes.length === 0
		},
		isSomeSelected() {
			return !this.isAllSelected && !this.isNoneSelected
		},
	},
	methods: {
		// Returns the aria-sort value for a given column mode.
		// Sortable columns that are not currently active must declare aria-sort="none"
		// so screen readers announce that the column can be sorted.
		// Non-sortable columns should have no aria-sort attribute at all (return null).
		ariaSortForMode(mode, isSortable = true) {
			if (!isSortable) {
				return null
			}
			if (this.filesSortingStore.sortingMode === mode) {
				return this.filesSortingStore.sortingDirection === 'asc' ? 'ascending' : 'descending'
			}
			return 'none'
		},
		classForColumn(column) {
			return {
				'files-list__column': true,
				'files-list__column--sortable': !!column.sort,
				'files-list__row-column-custom': true,
				[`files-list__row-${column.id}`]: true,
			}
		},
		onToggleAll(selected) {
			if (selected) {
				const selection = this.filesStore.ordered.map(id => Number(id))
				logger.debug('Added all nodes to selection', { selection })
				this.selectionStore.setLastIndex(null)
				this.selectionStore.set(selection)
			} else {
				logger.debug('Cleared selection')
				this.selectionStore.reset()
			}
		},
		resetSelection() {
			this.selectionStore.reset()
		},
	},
}
</script>

<style scoped lang="scss">
.files-list__column {
	user-select: none;
	// Make sure the cell colors don't apply to column headers
	color: var(--color-text-maxcontrast) !important;

	&--sortable {
		cursor: pointer;
	}
}

</style>
