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
			:checked="isSelected"
			@update:checked="onSelectionChange" />
	</td>
</template>

<script>
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import logger from '../../../logger.js'
import { useFilesStore } from '../../../store/files.js'
import { useKeyboardStore } from '../../../store/keyboard.js'
import { useSelectionStore } from '../../../store/selection.js'

export default {
	name: 'FileEntryCheckbox',

	components: {
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
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
			return this.selectedFiles.includes(this.source.nodeId)
		},
		index() {
			return this.filesStore.ordered.findIndex(nodeId => Number(nodeId) === this.source.nodeId)
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

			// Get the last selected and select all files in between
			if (this.keyboardStore?.shiftKey && lastSelectedIndex !== null) {
				const isAlreadySelected = this.selectedFiles.includes(this.source.nodeId)

				const start = Math.min(newSelectedIndex, lastSelectedIndex)
				const end = Math.max(lastSelectedIndex, newSelectedIndex)

				const lastSelection = this.selectionStore.lastSelection
				const filesToSelect = this.filesStore.ordered
					.slice(start, end + 1)

				// If already selected, update the new selection _without_ the current file
				const selection = [...new Set([...lastSelection, ...filesToSelect])]
					.filter(nodeId => !isAlreadySelected || nodeId !== this.source.nodeId)

				logger.debug('Shift key pressed, selecting all files in between', { start, end, filesToSelect, isAlreadySelected })
				// Keep previous lastSelectedIndex to be use for further shift selections
				this.selectionStore.set(selection)
				return
			}

			const selection = selected
				? [...this.selectedFiles, this.source.nodeId]
				: this.selectedFiles.filter(nodeId => nodeId !== this.source.nodeId)

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
