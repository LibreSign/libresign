/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const DEFAULT_SORTING_DIRECTION = 'desc'

export const useFilesSortingStore = defineStore('filesSorting', {
	state: () => {
		const initialSorting = loadState('libresign', 'sorting', { files_list_sorting_mode: 'name', files_list_sorting_direction: DEFAULT_SORTING_DIRECTION })
		return {
			sortingMode: initialSorting.files_list_sorting_mode || 'name',
			sortingDirection: initialSorting.files_list_sorting_direction || DEFAULT_SORTING_DIRECTION,
		}
	},

	actions: {
		toggleSortingDirection() {
			this.sortingDirection = this.sortingDirection === 'asc' ? 'desc' : 'asc'
			this.saveSorting()
			emit('libresign:sorting:update')
		},

		toggleSortBy(key) {
			// If we're already sorting by this key, flip the direction
			if (this.sortingMode === key) {
				this.toggleSortingDirection()
				return
			}
			// else sort ASC by this new key
			this.sortingMode = key
			this.sortingDirection = 'asc'
			this.saveSorting()
			emit('libresign:sorting:update')
		},

		async saveSorting() {
			try {
				await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'files_list_sorting_mode' }), {
					value: this.sortingMode,
				})
				await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'files_list_sorting_direction' }), {
					value: this.sortingDirection,
				})
			} catch (error) {
				console.error('Failed to save sorting:', error)
			}
		},
	},
})
