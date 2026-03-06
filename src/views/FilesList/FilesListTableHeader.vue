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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import FilesListTableHeaderButton from './FilesListTableHeaderButton.vue'

import logger from '../../logger.js'
import { useFilesStore } from '../../store/files.js'
import { useFilesSortingStore } from '../../store/filesSorting.js'
import { useSelectionStore } from '../../store/selection.js'

defineOptions({
	name: 'FilesListTableHeader',
})

defineProps<{
	nodes: unknown[]
}>()

type Column = {
	title: string
	id: string
	sort?: boolean
}

const filesStore = useFilesStore()
const filesSortingStore = useFilesSortingStore()
const selectionStore = useSelectionStore()

const columns = ref<Column[]>([
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
])

const selectedNodes = computed(() => selectionStore.selected)
const isAllSelected = computed(() => selectedNodes.value.length === filesStore.ordered.length && filesStore.ordered.length > 0)
const isNoneSelected = computed(() => selectedNodes.value.length === 0)
const isSomeSelected = computed(() => !isAllSelected.value && !isNoneSelected.value)
const selectAllBind = computed(() => {
	const label = t('libresign', 'Toggle selection for all files')
	return {
		'aria-label': label,
		'model-value': isAllSelected.value,
		indeterminate: isSomeSelected.value,
		title: label,
	}
})

function ariaSortForMode(mode: string, isSortable = true) {
	if (!isSortable) {
		return null
	}
	if (filesSortingStore.sortingMode === mode) {
		return filesSortingStore.sortingDirection === 'asc' ? 'ascending' : 'descending'
	}
	return 'none'
}

function classForColumn(column: Column) {
	return {
		'files-list__column': true,
		'files-list__column--sortable': !!column.sort,
		'files-list__row-column-custom': true,
		[`files-list__row-${column.id}`]: true,
	}
}

function onToggleAll(selected: boolean) {
	if (selected) {
		const selection = filesStore.ordered.map((item: any) => Number(item))
		logger.debug('Added all nodes to selection', { selection })
		selectionStore.setLastIndex(null)
		selectionStore.set(selection)
	} else {
		logger.debug('Cleared selection')
		selectionStore.reset()
	}
}

function resetSelection() {
	selectionStore.reset()
}

defineExpose({
	columns,
	selectAllBind,
	selectedNodes,
	isAllSelected,
	isNoneSelected,
	isSomeSelected,
	classForColumn,
	ariaSortForMode,
	onToggleAll,
	resetSelection,
})
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
