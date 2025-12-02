/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

export const useUserConfigStore = defineStore('userconfig', {
	state: () => {
		const config = loadState('libresign', 'config', {})
		return {
			grid_view: config.grid_view || false,
			crl_filters: config.crl_filters || {},
			crl_sort: config.crl_sort || { sortBy: 'revoked_at', sortOrder: 'DESC' },
		}
	},
	actions: {
		onUpdate(key, value) {
			this[key] = value
		},

		async update(key, value) {
			this.onUpdate(key, value)

			await axios.put(generateOcsUrl('/apps/libresign/api/v1/account/config/{key}', { key }), {
				value,
			})
		},
	},
})
