<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<FileListFilter :is-active="isActive"
		:filter-name="t('libresign', 'Modified')"
		@reset-filter="resetFilter"
		@set-marked-filter="setMarkedFilter">
		<template #icon>
			<NcIconSvgWrapper :path="mdiCalendarRange" />
		</template>
		<NcActionButton v-for="preset of timePresets"
			:key="preset.id"
			type="radio"
			close-after-click
			:model-value.sync="selectedOption"
			:value="preset.id">
			{{ preset.label }}
		</NcActionButton>
	</FileListFilter>
</template>

<script>
import { mdiCalendarRange } from '@mdi/js'
import calendarSvg from '@mdi/svg/svg/calendar.svg?raw'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import FileListFilter from './FileListFilter.vue'

import { useFiltersStore } from '../../../store/filters.js'

const startOfToday = () => (new Date()).setHours(0, 0, 0, 0)
const endOfToday = () => (new Date()).setHours(23, 59, 59, 999)

export default {
	name: 'FileListFilterModified',
	components: {
		FileListFilter,
		NcActionButton,
		NcIconSvgWrapper,
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
			timePresets: [
				{
					id: 'today',
					label: t('libresign', 'Today'),
					start: startOfToday(),
					end: endOfToday(),
				},
				{
					id: 'last-7',
					label: t('libresign', 'Last 7 days'),
					start: startOfToday() - (7 * 24 * 60 * 60 * 1000),
					end: endOfToday(),
				},
				{
					id: 'last-30',
					label: t('libresign', 'Last 30 days'),
					start: startOfToday() - (30 * 24 * 60 * 60 * 1000),
					end: endOfToday(),
				},
				{
					id: 'this-year',
					label: t('libresign', 'This year ({year})', { year: (new Date()).getFullYear() }),
					start: (new Date(startOfToday())).setMonth(0, 1),
					end: endOfToday(),
				},
				{
					id: 'last-year',
					label: t('libresign', 'Last year ({year})', { year: (new Date()).getFullYear() - 1 }),
					start: (new Date(startOfToday())).setFullYear((new Date()).getFullYear() - 1, 0, 1),
					end: (new Date(startOfToday())).setMonth(0, 1),
				},
			],
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
		setMarkedFilter(){

			const chips = []

			let preset = this.currentPreset

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

			this.filtersStore.onFilterUpdateChipsAndSave({ detail: chips, id: 'modified' })
		}
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
