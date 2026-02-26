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
		<NcButton v-for="status of fileStatus"
			:key="status.id"
			alignment="start"
			:pressed="selectedOptions.includes(status.id)"
			variant="tertiary"
			wide
			@click="toggleOption(status.id)">
			<template #icon>
				<NcIconSvgWrapper :path="status.icon" />
			</template>
			{{ status.label }}
		</NcButton>
	</FileListFilter>
</template>

<script>
import { t } from '@nextcloud/l10n'

import { mdiListStatus } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import FileListFilter from './FileListFilter.vue'

import { FILE_STATUS } from '../../../constants.js'
import { getStatusLabel, getStatusIcon } from '../../../utils/fileStatus.js'
import { useFiltersStore } from '../../../store/filters.js'

export default {
	name: 'FileListFilterStatus',
	components: {
		NcButton,
		NcIconSvgWrapper,
		FileListFilter,
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
			selectedOptions: this.filtersStore.filterStatusArray || [],
		}
	},
	computed: {
		isActive() {
			return this.selectedOptions.length > 0
		},
		fileStatus() {
			const codes = [FILE_STATUS.DRAFT, FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED, FILE_STATUS.SIGNED]
			return codes.map(id => ({ id, icon: getStatusIcon(id), label: getStatusLabel(id) }))
		},
	},
	mounted() {
		if (this.selectedOptions.length > 0) {
			this.setMarkedFilter()
		}
	},
	watch: {
		selectedOptions(newValue, oldValue) {
			if (newValue.length === 0) {
				this.setPreset()
			} else {
				this.setPreset(newValue)
			}
			this.setMarkedFilter()
		},
	},
	methods: {
		t,
		setPreset(presets) {
			const chips = []
			if (presets && presets.length > 0) {
				for (const id of presets) {
					const status = this.fileStatus.find(item => item.id === id)
					if (!status) continue

					chips.push({
						id: status.id,
						text: status.label,
						onclick: () => {
							this.selectedOptions = this.selectedOptions.filter(v => v !== status.id)
						},
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
				this.filtersStore.onFilterUpdateChipsAndSave({ detail: [], id: 'status' })
			}
		},
		toggleOption(option) {
			const idx = this.selectedOptions.indexOf(option)
			if (idx !== -1) {
				this.selectedOptions = this.selectedOptions.filter(v => v !== option)
			} else {
				this.selectedOptions = [...this.selectedOptions, option]
			}
		},
		setMarkedFilter() {
			const chips = []

			if (this.selectedOptions.length > 0) {
				for (const id of this.selectedOptions) {
					const status = this.fileStatus.find(item => item.id === id)
					if (!status) continue

					chips.push({
						id: status.id,
						text: status.label,
						onclick: () => {
							this.selectedOptions = this.selectedOptions.filter(v => v !== id)
						},
					})
				}
			}

			this.filtersStore.onFilterUpdateChipsAndSave({ detail: chips, id: 'status' })
		}
	},
}
</script>

<style>
.file-list-filter-status {
	max-width: 220px;
}
</style>
