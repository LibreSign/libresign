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
						this.error = data.ocs.data.message
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
							this.error = response.data.ocs.data.message
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
						this.error = data.ocs.data.message
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
