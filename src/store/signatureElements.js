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
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { set } from 'vue'

export const useSignatureElementsStore = function(...args) {
	const emptyElement = {
		id: 0,
		type: '',
		file: {
			url: '',
			fileId: 0,
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
			uuid: null,
			success: '',
			error: '',
		}),

		actions: {
			async loadSignatures() {
				const userSignatures = loadState('libresign', 'user_signatures', false)
				if (userSignatures) {
					userSignatures.forEach(element => {
						set(this.signs, element.type, element)
					})
					return
				}
				await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'))
					.then(({ data }) => {
						data.elements.forEach(current => {
							set(this.signs, current.type, current)
						})
					})
					.catch(({ data }) => {
						this.error = data.message
					})
			},
			hasSignatureOfType(type) {
				return this.signs[type].createdAt.length > 0
			},
			async save(type, base64) {
				const config = {}
				if (this.signs[type].id > 0) {
					config.url = generateOcsUrl('/apps/libresign/api/v1/account/signature/elements/{elementId}', {
						elementId: this.signs[type].id,
					})
					config.data = {
						type,
						file: { base64 },
					}
					config.method = 'patch'
				} else {
					config.url = generateOcsUrl('/apps/libresign/api/v1/account/signature/elements')
					config.data = {
						elements: [
							{
								type,
								file: { base64 },
							},
						],
						// Only add UUID if is not null
						...this.uuid,
					}
					config.method = 'post'
				}
				await axios(config)
					.then(({ data }) => {
						if (Object.hasOwn(data, 'elements')) {
							data.elements.forEach(element => {
								set(this.signs, element.type, element)
							})
						}
						set(this.signs[type], 'value', base64)
						this.success = data.message
					})
					.catch(({ response }) => {
						if (Object.hasOwn(response.data, 'errors')) {
							this.error = response.data.errors[0]
						} else {
							this.error = response.data.message
						}
					})
			},
			async delete(type) {
				await axios.delete(
					generateOcsUrl('/apps/libresign/api/v1/account/signature/elements/{elementId}', {
						elementId: this.signs[type].id,
					}),
				)
					.then(({ data }) => {
						this.signs[type] = emptyElement
						this.success = data.message
					})
					.catch(({ data }) => {
						this.error = data.message
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
