/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

export const useSignatureElementsStore = function(...args) {
	const emptyElement = {
		id: 0,
		type: '',
		file: {
			url: '',
			nodeId: 0,
		},
		starred: 0,
		createdAt: '',
	}
	const store = defineStore('signatureElements', {
		state: () => ({
			signs: {
				signature: emptyElement,
				initial: emptyElement,
			},
			currentType: '',
			signRequestUuid: '',
			success: '',
			error: '',
		}),

		actions: {
			async loadSignatures() {
				// Make sure we only run once
				if (this._initialized) {
					return
				}
				const userSignatures = loadState('libresign', 'user_signatures', false)
				if (userSignatures) {
					this.signRequestUuid = loadState('libresign', 'sign_request_uuid', '')
					userSignatures.forEach(element => {
						set(this.signs, element.type, element)
					})
					this._initialized = true
					return
				}
				const config = {
					url: generateOcsUrl('/apps/libresign/api/v1/signature/elements'),
					method: 'get',
				}
				if (this.signRequestUuid !== '') {
					config.headers = {
						'LibreSign-sign-request-uuid': this.signRequestUuid,
					}
				}
				await axios(config)
					.then(({ data }) => {
						data.ocs.data.elements.forEach(current => {
							set(this.signs, current.type, current)
						})
					})
					.catch(({ data }) => {
						this.error = { message: data.ocs.data.message }
					})
				this._initialized = true
			},
			hasSignatureOfType(type) {
				return this.signs[type].createdAt.length > 0
			},
			async save(type, base64) {
				const config = {}
				if (this.signs[type].file.nodeId > 0) {
					config.url = generateOcsUrl('/apps/libresign/api/v1/signature/elements/{nodeId}', {
						nodeId: this.signs[type].file.nodeId,
					})
					config.data = {
						type,
						file: { base64 },
					}
					config.method = 'patch'
				} else {
					config.url = generateOcsUrl('/apps/libresign/api/v1/signature/elements')
					config.data = {
						elements: [
							{
								type,
								file: { base64 },
							},
						],
						// Only add UUID if is not null
						...this.signRequestUuid,
					}
					config.method = 'post'
				}
				if (this.signRequestUuid !== '') {
					config.headers = {
						'LibreSign-sign-request-uuid': this.signRequestUuid,
					}
				}
				await axios(config)
					.then(({ data }) => {
						if (Object.hasOwn(data.ocs.data, 'elements')) {
							data.ocs.data.elements.forEach(element => {
								set(this.signs, element.type, element)
							})
						}
						set(this.signs[type], 'value', base64)
						this.success = data.ocs.data.message
					})
					.catch(({ response }) => {
						if (Object.hasOwn(response.data.ocs.data, 'errors')) {
							this.error = response.data.ocs.data.errors[0]
						} else {
							this.error = { message: response.data.ocs.data.message }
						}
					})
			},
			async delete(type) {
				const config = {
					url: generateOcsUrl('/apps/libresign/api/v1/signature/elements/{nodeId}', {
						nodeId: this.signs[type].file.nodeId,
					}),
					method: 'delete',
				}
				if (this.signRequestUuid !== '') {
					config.headers = {
						'LibreSign-sign-request-uuid': this.signRequestUuid,
					}
				}
				await axios(config)
					.then(({ data }) => {
						this.signs[type] = emptyElement
						this.success = data.ocs.data.message
					})
					.catch(({ data }) => {
						this.error = { message: data.ocs.data.message }
					})
			},
		},
	})
	const configureCheckStore = store(...args)
	// Make sure we only register the initialize once
	if (!configureCheckStore._initialized) {
		configureCheckStore.loadSignatures()
		configureCheckStore._initialized = true
	}
	return configureCheckStore
}
