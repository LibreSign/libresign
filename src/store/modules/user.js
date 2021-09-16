import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
const state = {
	user: {},
}

const getters = {

}

const mutations = {}

const actions = {
	CREATE: async({ commit }, { email, password, signPassword, uuid }) => {
		try {
			const response = await axios.post(generateUrl(`/apps/libresign/api/0.1/create/${uuid}`), {
				email,
				password,
				signPassword,
			})
			commit('setPdfData', response.data, { root: true })
			showSuccess('libresign', 'User created!')
		} catch (err) {
			showError(err.response.data.message)
		}
	},
	CREATE_PFX: async({ commit }, signPassword) => {
		try {
			await axios.post(generateUrl('/apps/libresign/api/0.1/account/signature'), {
				signPassword,
			})

			commit('setHasPfx', true, { root: true })
			showSuccess(t('libresign', 'Password dreated!'))
		} catch (err) {
			showError(err.response.data.message)
		}
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
