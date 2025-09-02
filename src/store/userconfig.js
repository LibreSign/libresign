/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'
import axios from 'axios'

export const useUserConfigStore = defineStore('userconfig', {
	state: () => ({
		grid_view: true,
	}),
	actions: {
		async update(key, value) {
			const oldValue = this[key]

			set(this, key, value)

			try {
				const response = await axios.put(
					`/apps/files/api/v1/config/${key}`,
					{ value }
				)

				if (response?.data?.value !== undefined) {
					set(this, key, response.data.value)
				}
			} catch (error) {
				console.error('Erro ao salvar configuração:', error)

				set(this, key, oldValue)
			}
		},
	},
})
