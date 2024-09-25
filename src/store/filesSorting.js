/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

const DEFAULT_SORTING_DIRECTION = 'asc'

export const useFilesSortingStore = defineStore('filesSorting', {
	state: () => ({
		sortingMode: 'basename',
		sortingDirection: DEFAULT_SORTING_DIRECTION,
	}),

	actions: {
		toggleSortingDirection() {
			this.sortingDirection = this.sortingDirection === 'asc' ? 'desc' : 'asc'
		},

		toggleSortBy(key) {
			// If we're already sorting by this key, flip the direction
			if (this.sortingMode === key) {
				this.toggleSortingDirection()
				return
			}
			// else sort ASC by this new key
			this.sortingMode = key
			this.sortingDirection = DEFAULT_SORTING_DIRECTION
		},
	},
})
