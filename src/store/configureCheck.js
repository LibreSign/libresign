/*
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export const useConfigureCheckStore = function(...args) {

	const store = defineStore('configureCheck', {
		state: () => ({
			items: [],
			state: 'in progress',
			downloadInProgress: false,
		}),

		actions: {
			isConfigureOk(engine) {
				return this.items.length > 0
					&& this.items.filter((o) => o.resource === engine + '-configure').length > 0
					&& this.items.filter((o) => o.resource === engine + '-configure' && o.status === 'error').length === 0
			},
			cfsslBinariesOk() {
				return this.items.length > 0
					&& this.items.filter((o) => o.resource === 'cfssl').length > 0
					&& this.items.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0
			},
			updateItems(items) {
				set(this, 'items', items)
				const java = this.items.filter((o) => o.resource === 'java' && o.status === 'error').length === 0
				const jsignpdf = this.items.filter((o) => o.resource === 'jsignpdf' && o.status === 'error').length === 0
				const cfssl = this.items.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0
				if (!java
					|| !jsignpdf
					|| !cfssl
				) {
					set(this, 'state', 'need download')
				} else {
					set(this, 'state', 'done')
				}
				set(this, 'downloadInProgress', false)
			},
			async checkSetup() {
				set(this, 'state', 'in progress')
				set(this, 'downloadInProgress', true)
				await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/admin/configure-check'),
				)
					.then(({ data }) => {
						this.updateItems(data.ocs.data)
					})
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
