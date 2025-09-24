/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import logger from '../helpers/logger.js'

export const useFiltersStore = defineStore('filter', {
	state: () => ({
		chips: {},
		filter_modified: loadState('libresign', 'filters', { filter_modified: '' }).filter_modified,
		filter_status: loadState('libresign', 'filters', { filter_status: '' }).filter_status,
	}),

	getters: {
		activeChips(state) {
			return Object.values(state.chips).flat()
		},
	},

	actions: {
		async onFilterUpdateChips(event) {
			this.chips = { ...this.chips, [event.id]: [...event.detail] }
			let value = this.chips['modified'][0]?.id;

			await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key: 'filter_modified' }), {
				value,
			})

			console.log(loadState('libresign', 'filters', {}))

			emit('libresign:filters:update')
			logger.debug('File list filter chips updated', { chips: event.detail })
		},
	},
})
