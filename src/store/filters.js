/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import logger from '../helpers/logger.js'

export const useFiltersStore = defineStore('filter', {
	state: () => ({
		chips: {},
	}),

	getters: {
		activeChips(state) {
			return Object.values(state.chips).flat()
		},
	},

	actions: {
		onFilterUpdateChips(event) {
			this.chips = { ...this.chips, [event.id]: [...event.detail] }

			logger.debug('File list filter chips updated', { chips: event.detail })
		},
	},
})
