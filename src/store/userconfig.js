/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { emit, subscribe } from '@nextcloud/event-bus'

export const useUserConfigStore = defineStore('userconfig', {
	state: () => ({
		grid_view: true, // valor inicial, mas será sobrescrito pelo servidor
	}),
	actions: {
		onUpdate(key, value) {
			set(this, key, value)
		},

		async update(key, value) {
			const oldValue = this[key]
			this.onUpdate(key, value)

			try {
				const response = await axios.put(
					generateUrl('/apps/files/api/v1/config/{key}', { key }),
					{ value },
				)

				if (response?.data?.value !== undefined) {
					this.onUpdate(key, response.data.value)
				}

				emit('files:config:updated', { key, value })
			} catch (error) {
				console.error('Erro ao salvar configuração:', error)
				this.onUpdate(key, oldValue)
			}
		},

		initListeners() {
			console.log('Initializing user config listeners...')
			subscribe('files:config:updated', ({ key, value }) => {
				this.onUpdate(key, value)
			})
		},
	},
})
