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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref, watch } from 'vue'

import { mdiListStatus } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import FileListFilter from './FileListFilter.vue'

import { FILE_STATUS } from '../../../constants.js'
import { getStatusLabel, getStatusIcon } from '../../../utils/fileStatus.js'
import { useFiltersStore } from '../../../store/filters.js'

defineOptions({
	name: 'FileListFilterStatus',
})

type FileStatusOption = {
	id: number
	icon: string
	label: string
}

const filtersStore = useFiltersStore()
const selectedOptions = ref<number[]>(filtersStore.filterStatusArray || [])

const isActive = computed(() => selectedOptions.value.length > 0)
const fileStatus = computed<FileStatusOption[]>(() => {
	const codes = [FILE_STATUS.DRAFT, FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED, FILE_STATUS.SIGNED]
	return codes.map((id) => ({ id, icon: getStatusIcon(id), label: getStatusLabel(id) }))
})

function setPreset(presets?: number[]) {
	const chips = []
	if (presets && presets.length > 0) {
		for (const id of presets) {
			const status = fileStatus.value.find((item) => item.id === id)
			if (!status) continue

			chips.push({
				id: status.id,
				text: status.label,
				onclick: () => {
					selectedOptions.value = selectedOptions.value.filter((value) => value !== status.id)
				},
			})
		}
	} else {
		resetFilter()
	}
	filtersStore.onFilterUpdateChips({ detail: chips, id: 'status' })
}

function resetFilter() {
	if (selectedOptions.value.length > 0) {
		selectedOptions.value = []
		filtersStore.onFilterUpdateChipsAndSave({ detail: [], id: 'status' })
	}
}

function toggleOption(option: number) {
	const index = selectedOptions.value.indexOf(option)
	if (index !== -1) {
		selectedOptions.value = selectedOptions.value.filter((value) => value !== option)
	} else {
		selectedOptions.value = [...selectedOptions.value, option]
	}
}

function setMarkedFilter() {
	const chips = []

	if (selectedOptions.value.length > 0) {
		for (const id of selectedOptions.value) {
			const status = fileStatus.value.find((item) => item.id === id)
			if (!status) continue

			chips.push({
				id: status.id,
				text: status.label,
				onclick: () => {
					selectedOptions.value = selectedOptions.value.filter((value) => value !== id)
				},
			})
		}
	}

	filtersStore.onFilterUpdateChipsAndSave({ detail: chips, id: 'status' })
}

onMounted(() => {
	if (selectedOptions.value.length > 0) {
		setMarkedFilter()
	}
})

watch(selectedOptions, (newValue) => {
	if (newValue.length === 0) {
		setPreset()
	} else {
		setPreset(newValue)
	}
	setMarkedFilter()
})

defineExpose({
	selectedOptions,
	isActive,
	resetFilter,
})
</script>

<style>
.file-list-filter-status {
	max-width: 220px;
}
</style>
