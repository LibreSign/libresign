/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { ref } from 'vue'

const createEmptyElement = () => ({
	id: 0,
	type: '',
	file: {
		url: '',
		nodeId: 0,
	},
	starred: false,
	createdAt: '',
})

const _signatureElementsStore = defineStore('signatureElements', () => {
	const signs = ref({
		signature: createEmptyElement(),
		initial: createEmptyElement(),
	})
	const currentType = ref('')
	const signRequestUuid = ref('')
	const success = ref('')
	const error = ref('')
	const initialized = ref(false)

	const loadSignatures = async () => {
		if (initialized.value) {
			return
		}

		const userSignatures = loadState('libresign', 'user_signatures', false)
		if (userSignatures) {
			signRequestUuid.value = loadState('libresign', 'sign_request_uuid', '')
			userSignatures.forEach(element => {
				signs.value[element.type] = element
			})
			initialized.value = true
			return
		}

		const config = {
			url: generateOcsUrl('/apps/libresign/api/v1/signature/elements'),
			method: 'get',
		}

		if (signRequestUuid.value !== '') {
			config.headers = {
				'libresign-sign-request-uuid': signRequestUuid.value,
			}
		}

		await axios(config)
			.then(({ data }) => {
				data.ocs.data.elements.forEach(current => {
					signs.value[current.type] = current
				})
			})
			.catch(({ data }) => {
				error.value = { message: data.ocs.data.message }
			})

		initialized.value = true
	}

	const hasSignatureOfType = (type) => signs.value[type].createdAt.length > 0

	const save = async (type, base64) => {
		const config = {}

		if (signs.value[type].file.nodeId > 0) {
			config.url = generateOcsUrl('/apps/libresign/api/v1/signature/elements/{nodeId}', {
				nodeId: signs.value[type].file.nodeId,
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
				...signRequestUuid.value,
			}
			config.method = 'post'
		}

		if (signRequestUuid.value !== '') {
			config.headers = {
				'libresign-sign-request-uuid': signRequestUuid.value,
			}
		}

		await axios(config)
			.then(({ data }) => {
				if (Object.hasOwn(data.ocs.data, 'elements')) {
					data.ocs.data.elements.forEach(element => {
						signs.value[element.type] = element
					})
				}
				signs.value[type].value = base64
				success.value = data.ocs.data.message
			})
			.catch(({ response }) => {
				if (Object.hasOwn(response.data.ocs.data, 'errors')) {
					error.value = response.data.ocs.data.errors[0]
				} else {
					error.value = { message: response.data.ocs.data.message }
				}
			})
	}

	const deleteSignature = async (type) => {
		const config = {
			url: generateOcsUrl('/apps/libresign/api/v1/signature/elements/{nodeId}', {
				nodeId: signs.value[type].file.nodeId,
			}),
			method: 'delete',
		}

		if (signRequestUuid.value !== '') {
			config.headers = {
				'libresign-sign-request-uuid': signRequestUuid.value,
			}
		}

		await axios(config)
			.then(({ data }) => {
				signs.value[type] = createEmptyElement()
				success.value = data.ocs.data.message
			})
			.catch(({ response }) => {
				if (response?.status === 404) {
					signs.value[type] = createEmptyElement()
				}
				error.value = { message: response?.data?.ocs?.data?.message || 'Error deleting signature' }
			})
	}

	return {
		signs,
		currentType,
		signRequestUuid,
		success,
		error,
		loadSignatures,
		hasSignatureOfType,
		save,
		delete: deleteSignature,
	}
})

export const useSignatureElementsStore = function(...args) {
	const signatureElementsStore = _signatureElementsStore(...args)
	signatureElementsStore.loadSignatures()
	return signatureElementsStore
}
