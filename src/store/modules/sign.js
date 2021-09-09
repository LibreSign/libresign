import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

const state = {
	sign: false,
}

const actions = {
	SIGN_DOCUMENT: async({ dispatch, rootGetters }, { fileId, password }) => {
		try {
			let response
			if (fileId.length >= 10) {
				response = await axios.post(generateUrl(`/apps/libresign/api/0.1/sign/uuid/${fileId}`), {
					password,
				})
			} else {
				response = await axios.post(generateUrl(`/apps/libresign/api/0.1/sign/file_id/${fileId}`), {
					password,
				})
			}
			dispatch('files/GET_ALL_FILES', {}, { root: true })
			dispatch('error/CLEAN', {}, { root: true })
			showSuccess(response.data.message)
		} catch (err) {
			console.info('error', err)
			err.response.data.errors.forEach(error => {
				dispatch('error/SET_ERROR', { code: err.response.status, message: error }, { root: true })
				showError(rootGetters['error/getError'])
			})
		}
	},
	REQUEST: async({ dispatch }, { fileId, name, users }) => {
		try {
			const response = await axios.post(generateUrl('/apps/libresign/api/0.1/sign/register'), {
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
