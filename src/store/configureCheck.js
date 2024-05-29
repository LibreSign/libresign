/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { set } from 'vue'

export const useConfigureCheckStore = function(...args) {

	const store = defineStore('configureCheck', {
		state: () => ({
			items: [],
		}),

		actions: {
			isConfigureOk(engine) {
				return this.items.length > 0
					&& this.items.filter((o) => o.resource === engine + '-configure').length === 0
					&& this.items.filter((o) => o.resource === engine + '-configure' && o.status === 'error').length === 0
			},
			cfsslBinariesOk() {
				return this.items.length > 0
					&& this.items.filter((o) => o.resource === 'cfssl').length > 0
					&& this.items.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0
			},
			async checkSetup() {
				const response = await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/admin/configure-check'),
				)
				set(this, 'items', response.data.ocs.data)
			},
		},
	})
	const configureCheckStore = store(...args)
	// Make sure we only register the initialize once
	if (!configureCheckStore._initialized) {
		configureCheckStore.checkSetup()
		configureCheckStore._initialized = true
	}
	return configureCheckStore
}
