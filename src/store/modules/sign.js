import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

const state = {
	sign: false,
}

const actions = {
	SIGN_DOCUMENT: async ({ dispatch, rootGetters }, { fileId, password, elements }) => {
		try {
			let response
			if (fileId.length >= 10) {
				response = await axios.post(generateOcsUrl(`/apps/libresign/api/v1/sign/uuid/${fileId}`), {
					password,
					elements,
				})
			} else {
				response = await axios.post(generateOcsUrl(`/apps/libresign/api/v1/sign/file_id/${fileId}`), {
					password,
					elements,
				})
			}
			await dispatch('files/GET_ALL_FILES', {}, { root: true })
			await dispatch('error/CLEAN', {}, { root: true })
			await dispatch('sidebar/RESET', {}, { root: true })

			showSuccess(response.data.message)
		} catch (err) {
			err.response.data.errors.forEach(async error => {
				await dispatch('error/SET_ERROR', { code: err.response.status, message: error }, { root: true })
				showError(rootGetters['error/getError'])
			})
		}
	},
	REQUEST: async ({ dispatch }, { fileId, name, users }) => {
		try {
			const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/request-signature'), {
				file: {
					fileId,
				},
				name,
				users,
			})
			showSuccess(response.data.message)
		} catch (err) {
			showError(err.response.data.message)
		}
	},
}

export default {
	namespaced: true,
	state,
	actions,
}
