/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const DEFAULT_SORTING_DIRECTION = 'desc'

export const useFilesSortingStore = defineStore('filesSorting', () => {
	const initialSorting = loadState('libresign', 'sorting', { sorting_mode: 'created_at', sorting_direction: DEFAULT_SORTING_DIRECTION })
	const sortingMode = ref(initialSorting.sorting_mode || 'created_at')
	const sortingDirection = ref(initialSorting.sorting_direction || DEFAULT_SORTING_DIRECTION)

	const saveSorting = async () => {
		try {
			await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'files_list_sorting_mode' }), {
				value: sortingMode.value,
			})
			await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'files_list_sorting_direction' }), {
				value: sortingDirection.value,
			})
		} catch (error) {
			console.error('Failed to save sorting:', error)
		}
	}

	const toggleSortingDirection = async () => {
		sortingDirection.value = sortingDirection.value === 'asc' ? 'desc' : 'asc'
		await saveSorting()
		emit('libresign:sorting:update')
	}

	const toggleSortBy = async (key) => {
		// If we're already sorting by this key, flip the direction
		if (sortingMode.value === key) {
			await toggleSortingDirection()
			return
		}
		// else sort ASC by this new key
		sortingMode.value = key
		sortingDirection.value = 'asc'
		await saveSorting()
		emit('libresign:sorting:update')
	}

	return {
		sortingMode,
		sortingDirection,
		toggleSortingDirection,
		toggleSortBy,
		saveSorting,
	}
})
