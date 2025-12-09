/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

export const useConfigureCheckStore = function(...args) {

	const store = defineStore('configureCheck', {
		state: () => ({
			items: [],
			state: 'in progress',
			downloadInProgress: false,
			certificateEngine: loadState('libresign', 'certificate_engine', ''),
			identifyMethods: loadState('libresign', 'identify_methods', []),
		}),

		getters: {
			isNoneEngine: (state) => state.certificateEngine === 'none',
		},

		actions: {
			setCertificateEngine(engine) {
				set(this, 'certificateEngine', engine)
			},
			setIdentifyMethods(identifyMethods) {
				set(this, 'identifyMethods', identifyMethods)
			},
			saveCertificateEngine(engine) {
				return axios.post(
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate/engine'),
					{ engine }
				)
					.then((response) => {
						this.setCertificateEngine(engine)
						if (response.data?.ocs?.data?.identify_methods) {
							this.setIdentifyMethods(response.data.ocs.data.identify_methods)
						}
						return this.checkSetup().then(() => ({ success: true, engine }))
					})
					.catch((error) => {
						console.error('Failed to save certificate engine:', error)
						return { success: false, error }
					})
			},
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
						this.updateItems(data.ocs?.data || [])
					})
					.catch((error) => {
						console.error('Failed to check setup:', error)
						set(this, 'state', 'error')
						set(this, 'downloadInProgress', false)
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
