/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { loadState } from '@nextcloud/initial-state'

export const useUserConfigStore = defineStore('userconfig', {
	state: () => ({
		grid_view: loadState('libresign', 'config', { grid_view: false }).grid_view,
	}),
	actions: {
		onUpdate(key, value) {
			this[key] = value
		},

		async update(key, value) {
			this.onUpdate(key, value)

			OCP.AppConfig.setValue('libresign', 'grid_view', value)
		},

	},
})
