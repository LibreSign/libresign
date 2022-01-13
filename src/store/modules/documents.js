import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

const state = {
	documents: [],
}

const getters = {}

const mutations = {
	set_documents(state, payload) {
		state.documents = [...payload]
	},
}

const actions = {
	async approvalList(context, payload) {
		let result
		try {
			result = await axios.get(generateUrl('/apps/libresign/api/0.1/account/files/approval/list'))
		} catch (err) {
			showError('Error while trying to list')
			return { success: false }
		}

		return { success: true, data: result.data.data }
	},
	async list(context, payload) {
		let result
		try {
			result = await axios.get(generateUrl('/apps/libresign/api/0.1/account/files'))
		} catch (err) {
			showError('Error while trying to list')
			return { success: false }
		}

		if (result.data.data[0]) { context.commit('set_documents', result.data.data[0].files) }

		return { success: true, data: result.data.data }
	},
	async remove(context, payload) {
		let result
		try {
			result = await axios.delete(generateUrl('/apps/libresign/api/0.1/account/files'), payload.form)
		} catch (err) {
			showError('Error while trying to delete')
			context.commit('setError', err.response.data.message)
			return { success: false }
		}

		return { success: true, data: result }
	},
	async save(context, payload) {
		let result
		try {
			result = await axios.post(generateUrl('/apps/libresign/api/0.1/account/files'), payload.form)
		} catch (err) {
			showError('Error while trying to save')
			// context.commit('setError', err.response.data.message)
			return { success: false }
		}

		return { success: true, data: result }
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
