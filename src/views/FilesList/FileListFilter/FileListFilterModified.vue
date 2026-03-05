<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<FileListFilter :is-active="isActive"
		:filter-name="t('libresign', 'Modified')"
		@reset-filter="resetFilter">
		<template #icon>
			<NcIconSvgWrapper :path="mdiCalendarRange" />
		</template>
		<NcButton v-for="preset of timePresets"
			:key="preset.id"
			alignment="start"
			:pressed="selectedOption === preset.id"
			variant="tertiary"
			wide
			@click="selectedOption = selectedOption === preset.id ? null : preset.id">
			{{ preset.label }}
		</NcButton>
	</FileListFilter>
</template>

<script>
import { t } from '@nextcloud/l10n'

import { mdiCalendarRange } from '@mdi/js'
import calendarSvg from '@mdi/svg/svg/calendar.svg?raw'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import FileListFilter from './FileListFilter.vue'

import { useFiltersStore } from '../../../store/filters.js'
import { getTimePresets } from '../../../utils/timePresets.js'

export default {
	name: 'FileListFilterModified',
	components: {
		NcButton,
		NcIconSvgWrapper,
		FileListFilter,
	},
	setup() {
		const filtersStore = useFiltersStore()
		return {
			// icons used in template
			mdiCalendarRange,
			filtersStore,
		}
	},
	data() {
		return {
			selectedOption: this.filtersStore.filter_modified || null,
			timePresets: getTimePresets(),
		}
	},
	computed: {
		isActive() {
			return this.selectedOption !== null
		},
		currentPreset() {
			return this.timePresets.find(({ id }) => id === this.selectedOption) ?? null
		},
	},
	mounted() {
		if (this.selectedOption) {
			this.setPreset(this.currentPreset)
		}
	},
	watch: {
		selectedOption() {
			if (this.selectedOption === null) {
				this.selectedOption = null
				this.setPreset()
			} else {
				this.setPreset(this.currentPreset)
			}
			this.setMarkedFilter()
		},
	},
	methods: {
		setPreset(preset) {
			const chips = []
			if (preset) {
				chips.push({
					start: preset.start,
					end: preset.end,
					icon: calendarSvg,
					text: preset.label,
					id: preset.id,
					onclick: () => this.setPreset(),
				})
			} else {
				this.resetFilter()
			}
			this.filtersStore.onFilterUpdateChips({ detail: chips, id: 'modified' })
		},
		resetFilter() {
			if (this.selectedOption !== null) {
				this.selectedOption = null
				this.timeRangeEnd = null
				this.timeRangeStart = null
				this.filtersStore.onFilterUpdateChipsAndSave({ detail: '', id: 'modified' })
			}
		},
		setMarkedFilter() {
			const chips = []
			const preset = this.currentPreset

			if (preset) {
				chips.push({
					start: preset.start,
					end: preset.end,
					icon: calendarSvg,
					text: preset.label,
					id: preset.id,
					onclick: () => this.setPreset(),
				})
			}

			this.filtersStore.onFilterUpdateChipsAndSave({ detail: chips, id: 'modified' })
		},
	},
}
</script>

<style scoped lang="scss">
.files-list-filter-time {
	&__clear-button :deep(.action-button__text) {
		color: var(--color-error-text);
	}
}
</style>
