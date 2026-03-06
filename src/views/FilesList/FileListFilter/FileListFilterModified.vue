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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref, watch, onMounted } from 'vue'

import { mdiCalendarRange } from '@mdi/js'
import calendarSvg from '@mdi/svg/svg/calendar.svg?raw'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import FileListFilter from './FileListFilter.vue'

import { useFiltersStore } from '../../../store/filters.js'
import { getTimePresets } from '../../../utils/timePresets.js'

defineOptions({
	name: 'FileListFilterModified',
})

type TimePreset = {
	id: string
	label: string
	start: string | number | Date
	end: string | number | Date
}

const filtersStore = useFiltersStore()
const selectedOption = ref<string | null>(filtersStore.filter_modified || null)
const timePresets = getTimePresets() as TimePreset[]

const isActive = computed(() => selectedOption.value !== null)
const currentPreset = computed(() => timePresets.find(({ id }) => id === selectedOption.value) ?? null)

function setPreset(preset?: TimePreset | null) {
	const chips = []
	if (preset) {
		chips.push({
			start: preset.start,
			end: preset.end,
			icon: calendarSvg,
			text: preset.label,
			id: preset.id,
			onclick: () => setPreset(),
		})
	} else {
		resetFilter()
	}
	filtersStore.onFilterUpdateChips({ detail: chips, id: 'modified' })
}

function resetFilter() {
	if (selectedOption.value !== null) {
		selectedOption.value = null
		filtersStore.onFilterUpdateChipsAndSave({ detail: '', id: 'modified' })
	}
}

function setMarkedFilter() {
	const chips = []
	const preset = currentPreset.value

	if (preset) {
		chips.push({
			start: preset.start,
			end: preset.end,
			icon: calendarSvg,
			text: preset.label,
			id: preset.id,
			onclick: () => setPreset(),
		})
	}

	filtersStore.onFilterUpdateChipsAndSave({ detail: chips, id: 'modified' })
}

onMounted(() => {
	if (selectedOption.value) {
		setPreset(currentPreset.value)
	}
})

watch(selectedOption, () => {
	if (selectedOption.value === null) {
		selectedOption.value = null
		setPreset()
	} else {
		setPreset(currentPreset.value)
	}
	setMarkedFilter()
})

defineExpose({
	selectedOption,
	isActive,
	resetFilter,
})
</script>

<style scoped lang="scss">
.files-list-filter-time {
	&__clear-button :deep(.action-button__text) {
		color: var(--color-error-text);
	}
}
</style>
