/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'
import { set } from 'vue'

import { subscribe } from '@nextcloud/event-bus'

export const useSelectionStore = function(...args) {
	const store = defineStore('selection', {
		state: () => ({
			selected: [],
			lastSelection: [],
			lastSelectedIndex: null,
		}),

		actions: {
			/**
			 * Set the selection of fileIds
			 * @param {Array} selection Selected files
			 */
			set(selection = []) {
				set(this, 'selected', [...new Set(selection)])
			},

			/**
			 * Set the last selected index
			 * @param {number | null} lastSelectedIndex Position of last selected file
			 */
			setLastIndex(lastSelectedIndex = null) {
				// Update the last selection if we provided a new selection starting point
				set(this, 'lastSelection', lastSelectedIndex ? this.selected : [])
				set(this, 'lastSelectedIndex', lastSelectedIndex)
			},

			/**
			 * Reset the selection
			 */
			reset() {
				set(this, 'selected', [])
				set(this, 'lastSelection', [])
				set(this, 'lastSelectedIndex', null)
			},
		},
	})

	const selectionStore = store(...args)

	// Make sure we only register the listeners once
	if (!selectionStore._initialized) {
		subscribe('libresign:filters:update', selectionStore.reset)
		selectionStore._initialized = true
	}

	return selectionStore
}
