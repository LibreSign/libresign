<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<FileListFilter class="file-list-filter-status"
		:is-active="isActive"
		:filter-name="t('libresign', 'Status')"
		@reset-filter="resetFilter">
		<template #icon>
			<NcIconSvgWrapper :path="mdiListStatus" />
		</template>
		<NcActionButton v-for="status of fileStatus"
			:key="status.id"
			type="checkbox"
			:model-value="selectedOptions.includes(status)"
			@click="toggleOption(status)">
			<template #icon>
				<NcIconSvgWrapper :svg="status.icon" />
			</template>
			{{ status.label }}
		</NcActionButton>
	</FileListFilter>
</template>

<script>
import { mdiListStatus } from '@mdi/js'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import FileListFilter from './FileListFilter.vue'

import { fileStatus } from '../../../helpers/fileStatus.js'
import { useFiltersStore } from '../../../store/filters.js'

export default {
	name: 'FileListFilterStatus',
	components: {
		FileListFilter,
		NcActionButton,
		NcIconSvgWrapper,
	},
	setup() {
		const filtersStore = useFiltersStore()
		return {
			mdiListStatus,
			filtersStore,
		}
	},
	data() {
		return {
			selectedOptions: [],
		}
	},
	computed: {
		isActive() {
			return this.selectedOptions.length > 0
		},
		fileStatus() {
			return fileStatus.filter(item => [0, 1, 2, 3].includes(item.id))
		},
	},
	watch: {
		selectedOptions(newValue, oldValue) {
			if (newValue.length === 0) {
				this.setPreset()
			} else {
				this.setPreset(newValue)
			}
		},
	},
	methods: {
		setPreset(presets) {
			const chips = []
			if (presets && presets.length > 0) {
				for (const preset of presets) {
					chips.push({
						id: preset.id,
						icon: preset.icon,
						text: preset.label,
						onclick: () => this.setPreset(presets.filter(({ id }) => id !== preset.id)),
					})
				}
			} else {
				this.resetFilter()
			}
			this.filtersStore.onFilterUpdateChips({ detail: chips, id: 'status' })
		},
		resetFilter() {
			if (this.selectedOptions.length > 0) {
				this.selectedOptions = []
			}
		},
		toggleOption(option) {
			const idx = this.selectedOptions.indexOf(option)
			if (idx !== -1) {
				this.selectedOptions.splice(idx, 1)
			} else {
				this.selectedOptions.push(option)
			}
		},
	},
}
</script>

<style>
.file-list-filter-status {
	max-width: 220px;
}
</style>
