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

<script>
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import logger from '../../../logger.js'
import { useFilesStore } from '../../../store/files.js'
import { useKeyboardStore } from '../../../store/keyboard.js'
import { useSelectionStore } from '../../../store/selection.js'

export default {
	name: 'FileEntryCheckbox',

	components: {
		NcLoadingIcon,
		NcCheckboxRadioSwitch,
	},
	props: {
		isLoading: {
			type: Boolean,
			default: false,
		},
		source: {
			type: Object,
			required: true,
		},
	},

	setup() {
		const filesStore = useFilesStore()
		const keyboardStore = useKeyboardStore()
		const selectionStore = useSelectionStore()
		return {
			t,
			filesStore,
			keyboardStore,
			selectionStore,
		}
	},

	computed: {
		selectedFiles() {
			return this.selectionStore.selected
		},
		isSelected() {
			const normalizedId = Number(this.source.id)
			return this.selectedFiles.some(id => Number(id) === normalizedId)
		},
		index() {
			const normalizedId = Number(this.source.id)
			return this.filesStore.ordered.findIndex(key => Number(key) === normalizedId)
		},
		ariaLabel() {
			return t('libresign', 'Toggle selection for file "{displayName}"', { displayName: this.source.basename })
		},
		loadingLabel() {
			return t('libresign', 'File is loading')
		},
	},
	methods: {
		onSelectionChange(selected) {
			const newSelectedIndex = this.index
			const lastSelectedIndex = this.selectionStore.lastSelectedIndex

			const normalizedCurrentId = Number(this.source.id)

			if (this.keyboardStore?.shiftKey && lastSelectedIndex !== null) {
				const isAlreadySelected = this.selectedFiles.some(id => Number(id) === normalizedCurrentId)

				const start = Math.min(newSelectedIndex, lastSelectedIndex)
				const end = Math.max(lastSelectedIndex, newSelectedIndex)

				const lastSelection = this.selectionStore.lastSelection
				const filesToSelect = this.filesStore.ordered
					.slice(start, end + 1)
					.map(id => Number(id))

				const selection = [...new Set([...lastSelection.map(id => Number(id)), ...filesToSelect])]
					.filter(id => !isAlreadySelected || id !== normalizedCurrentId)
				this.selectionStore.set(selection)
				return
			}

			const selection = selected
				? [...this.selectedFiles.map(id => Number(id)), normalizedCurrentId]
				: this.selectedFiles.map(id => Number(id)).filter(id => id !== normalizedCurrentId)

			logger.debug('Updating selection', { selection })
			this.selectionStore.set(selection)
			this.selectionStore.setLastIndex(newSelectedIndex)
		},

		resetSelection() {
			this.selectionStore.reset()
		},
	},
}
</script>
