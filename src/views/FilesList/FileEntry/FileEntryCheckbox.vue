<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<td class="files-list__row-checkbox"
		@keyup.esc.exact="resetSelection">
		<NcLoadingIcon v-if="isLoading" :name="loadingLabel" />
		<NcCheckboxRadioSwitch v-else
			:aria-label="ariaLabel"
			:model-value="isSelected"
			@update:modelValue="onSelectionChange" />
	</td>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import logger from '../../../logger.js'
import { useFilesStore } from '../../../store/files.js'
import { useKeyboardStore } from '../../../store/keyboard.js'
import { useSelectionStore } from '../../../store/selection.js'

defineOptions({
	name: 'FileEntryCheckbox',
})

type Source = {
	id: number | string
	basename?: string
	name?: string
}

type FilesStore = {
	ordered: Array<number | string>
}

type KeyboardStore = {
	shiftKey?: boolean
}

type SelectionStore = {
	selected: Array<number | string>
	lastSelectedIndex: number | null
	lastSelection: Array<number | string>
	set: (selection: number[]) => void
	setLastIndex: (index: number) => void
	reset: () => void
}

const props = withDefaults(defineProps<{
	isLoading?: boolean
	source: Source
}>(), {
	isLoading: false,
})

const filesStore = useFilesStore() as FilesStore
const keyboardStore = useKeyboardStore() as KeyboardStore
const selectionStore = useSelectionStore() as SelectionStore

const selectedFiles = computed(() => selectionStore.selected)
const isSelected = computed(() => {
	const normalizedId = Number(props.source.id)
	return selectedFiles.value.some((id) => Number(id) === normalizedId)
})

const index = computed(() => {
	const normalizedId = Number(props.source.id)
	return filesStore.ordered.findIndex((key) => Number(key) === normalizedId)
})

const ariaLabel = computed(() => {
	return t('libresign', 'Toggle selection for file "{displayName}"', { displayName: props.source.basename ?? props.source.name ?? '' })
})

const loadingLabel = computed(() => t('libresign', 'File is loading'))

function onSelectionChange(selected: boolean) {
	const newSelectedIndex = index.value
	const lastSelectedIndex = selectionStore.lastSelectedIndex
	const normalizedCurrentId = Number(props.source.id)

	if (keyboardStore?.shiftKey && lastSelectedIndex !== null) {
		const isAlreadySelected = selectedFiles.value.some((id) => Number(id) === normalizedCurrentId)
		const start = Math.min(newSelectedIndex, lastSelectedIndex)
		const end = Math.max(lastSelectedIndex, newSelectedIndex)
		const lastSelection = selectionStore.lastSelection
		const filesToSelect = filesStore.ordered
			.slice(start, end + 1)
			.map((id) => Number(id))

		const selection = [...new Set([...lastSelection.map((id) => Number(id)), ...filesToSelect])]
			.filter((id) => !isAlreadySelected || id !== normalizedCurrentId)
		selectionStore.set(selection)
		return
	}

	const selection = selected
		? [...selectedFiles.value.map((id) => Number(id)), normalizedCurrentId]
		: selectedFiles.value.map((id) => Number(id)).filter((id) => id !== normalizedCurrentId)

	logger.debug('Updating selection', { selection })
	selectionStore.set(selection)
	selectionStore.setLastIndex(newSelectedIndex)
}

function resetSelection() {
	selectionStore.reset()
}

defineExpose({
	filesStore,
	keyboardStore,
	selectionStore,
	selectedFiles,
	isSelected,
	index,
	ariaLabel,
	loadingLabel,
	onSelectionChange,
	resetSelection,
	props,
})
</script>
