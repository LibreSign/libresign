<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<tr class="files-list__row-head">
		<!-- Columns display -->

		<!-- Link to file -->
		<th class="files-list__column files-list__row-name files-list__column--sortable"
			:aria-sort="ariaSortForMode('basename')">
			<!-- Icon or preview -->
			<span class="files-list__row-icon" />

			<!-- Name -->
			<FilesListTableHeaderButton :name="t('files', 'Name')" mode="name" />
		</th>

		<!-- Actions -->
		<th class="files-list__row-actions" />

		<!-- Custom views columns -->
		<th v-for="column in columns"
			:key="column.id"
			:class="classForColumn(column)"
			:aria-sort="ariaSortForMode(column.id)">
			<FilesListTableHeaderButton v-if="!!column.sort" :name="column.title" :mode="column.id" />
			<span v-else>
				{{ column.title }}
			</span>
		</th>
	</tr>
</template>

<script>
import FilesListTableHeaderButton from './FilesListTableHeaderButton.vue'

export default {
	name: 'FilesListTableHeader',

	components: {
		FilesListTableHeaderButton,
	},

	props: {
		nodes: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			isAscSorting: false,
			sortingMode: 'name',
			columns: [
				{
					title: t('libresign', 'Status'),
					id: 'status',
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
	methods: {
		ariaSortForMode(mode) {
			if (this.sortingMode === mode) {
				return this.isAscSorting ? 'ascending' : 'descending'
			}
			return null
		},
		classForColumn(column) {
			return {
				'files-list__column': true,
				'files-list__column--sortable': !!column.sort,
				'files-list__row-column-custom': true,
				[`files-list__row-${column.id}`]: true,
			}
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
