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

export const useSignatureElementsStore = defineStore('signatureElements', {
	state: () => ({
		signs: {
			signature: {
				id: 0,
				fileId: 0,
				value: '',
			},
			initial: {
				id: 0,
				fileId: 0,
				value: '',
			},
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
			try {
				const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'))

				response.data.elements.forEach(current => {
					set(this.signs, current.type, current)
				})
			} catch (err) {
				this.error = err.response.data.message
			}
		},
		hasSignatureOfType(type) {
			return this.signs[type].id > 0
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
					this.error = response.data.errors[0]
				})
		},
		async delete(type) {
			await axios.delete(
				generateOcsUrl('/apps/libresign/api/v1/account/signature/elements/{elementId}', {
					elementId: this.signs[type].id,
				}),
			)
				.then(({ data }) => {
					this.signs[type] = {
						id: 0,
						fileId: 0,
						value: '',
					}
					this.success = data.message
				})
				.catch(({ data }) => {
					this.error = data.message
				})
		},
	},
})
