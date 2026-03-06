/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'

import { subscribe } from '@nextcloud/event-bus'

const _selectionStore = defineStore('selection', () => {
	const selected = ref([])
	const lastSelection = ref([])
	const lastSelectedIndex = ref(null)

	/**
	 * Set the selection of fileIds
	 * @param {Array} selection Selected files
	 */
	const set = (selection = []) => {
		selected.value = [...new Set(selection)]
	}

	/**
	 * Set the last selected index
	 * @param {number | null} index Position of last selected file
	 */
	const setLastIndex = (index = null) => {
		// Update the last selection if we provided a new selection starting point
		lastSelection.value = index ? selected.value : []
		lastSelectedIndex.value = index
	}

	/**
	 * Reset the selection
	 */
	const reset = () => {
		selected.value = []
		lastSelection.value = []
		lastSelectedIndex.value = null
	}

	return {
		selected,
		lastSelection,
		lastSelectedIndex,
		set,
		setLastIndex,
		reset,
	}
})

let _initialized = false

export const useSelectionStore = function(...args) {
	const selectionStore = _selectionStore(...args)
	if (!_initialized) {
		subscribe('libresign:filters:update', selectionStore.reset)
		_initialized = true
	}
	return selectionStore
}
