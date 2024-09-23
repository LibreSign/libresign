/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

export const useUserConfigStore = defineStore('userconfig', {
	state: () => ({
		grid_view: true,
	}),
	actions: {
		async update(key, value) {
			Vue.set(this, key, value)
		},
	},
})
