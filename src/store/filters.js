/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import logger from '../helpers/logger'
import { getTimePresetRange } from '../utils/timePresets.js'

/**
 * @typedef {{ id?: string, [key: string]: unknown }} FilterChip
 */

export const useFiltersStore = defineStore('filter', () => {
	const initialFilters = loadState('libresign', 'filters', {})
	const chips = ref(/** @type {Record<string, FilterChip[]>} */ ({}))
	const filter_modified = ref(initialFilters.files_list_filter_modified ?? '')
	const filter_status = ref(initialFilters.files_list_filter_status ?? '')

	const activeChips = computed(() => Object.values(chips.value).flat())

	const filterStatusArray = computed(() => {
		try {
			return filter_status.value !== '' ? JSON.parse(filter_status.value) : []
		} catch (e) {
			return []
		}
	})

	/**
	 * Returns { start, end } in ms for the saved modified preset, or null.
	 * Computed fresh on each access so date boundaries are always current.
	 */
	const filterModifiedRange = computed(() => getTimePresetRange(filter_modified.value))

	const onFilterUpdateChips = async (event) => {
		chips.value = { ...chips.value, [event.id]: [...event.detail] }
		logger.debug('File list filter chips updated', { chips: event.detail })
	}

	const onFilterUpdateChipsAndSave = async (event) => {
		chips.value = { ...chips.value, [event.id]: [...event.detail] }

		if (event.id === 'modified') {
			const value = chips.value.modified?.[0]?.id || ''
			await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'files_list_filter_modified' }), {
				value,
			})
			filter_modified.value = value
			emit('libresign:filters:update')
		}

		if (event.id === 'status') {
			const value = event.detail.length > 0 ? JSON.stringify(event.detail.map(item => item.id)) : ''
			await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'files_list_filter_status' }), {
				value,
			})
			filter_status.value = value
			emit('libresign:filters:update')
		}

		logger.debug('File list filter chips updated', { chips: event.detail })
	}

	return {
		chips,
		filter_modified,
		filter_status,
		activeChips,
		filterStatusArray,
		filterModifiedRange,
		onFilterUpdateChips,
		onFilterUpdateChipsAndSave,
	}
})
