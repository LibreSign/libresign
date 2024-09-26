<template>
	<FileListFilter :is-active="isActive"
		:filter-name="t('libresign', 'Modified')"
		@reset-filter="resetFilter">
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

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'

import FileListFilter from './FileListFilter.vue'

import { useFiltersStore } from '../../../store/filters.js'

const startOfToday = () => (new Date()).setHours(0, 0, 0, 0)

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
			selectedOption: null,
			timeRangeEnd: null,
			timeRangeStart: null,
			timePresets: [
				{
					id: 'today',
					label: t('libresign', 'Today'),
					start: '',
					end: '',
					filter: (time) => time > startOfToday(),
				},
				{
					id: 'last-7',
					label: t('libresign', 'Last 7 days'),
					start: '',
					end: '',
					filter: (time) => time > (startOfToday() - (7 * 24 * 60 * 60 * 1000)),
				},
				{
					id: 'last-30',
					label: t('libresign', 'Last 30 days'),
					start: '',
					end: '',
					filter: (time) => time > (startOfToday() - (30 * 24 * 60 * 60 * 1000)),
				},
				{
					id: 'this-year',
					label: t('libresign', 'This year ({year})', { year: (new Date()).getFullYear() }),
					start: '',
					end: '',
					filter: (time) => time > (new Date(startOfToday())).setMonth(0, 1),
				},
				{
					id: 'last-year',
					start: '',
					end: '',
					label: t('libresign', 'Last year ({year})', { year: (new Date()).getFullYear() - 1 }),
					filter: (time) => (time > (new Date(startOfToday())).setFullYear((new Date()).getFullYear() - 1, 0, 1)) && (time < (new Date(startOfToday())).setMonth(0, 1)),
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
					icon: calendarSvg,
					text: preset.label,
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
			}
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
