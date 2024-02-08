import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

const state = {
	user: {},
	pfx: {},
	error: {},
	config: {},
}

const getters = {
	hasPfx: (state) => {
		return state.pfx.length > 0
	},
	getError: state => {
		return state.error.length > 0
	},

}

const mutations = {
	setError: (state, error) => {
		state.error = error
	},
	setPfx: (state, data) => {
		state.pfx = data
	},
}

const actions = {
	CREATE: async ({ commit, dispatch }, { email, password, uuid }) => {
		try {
			dispatch('CLEAR_ERROR')
			const response = await axios.post(generateOcsUrl(`/apps/libresign/api/v1/account/create/${uuid}`), {
				email,
				password,
			})
			if (response.data.sucess === false) {
				commit('setError', response.data.message)
			}

			commit('setPdfData', response.data, { root: true })
			/**
			 * @todo set file to store from response.data if necessary
			 */
			dispatch('files/SET_FILE_TO_SIGN', response.data, { root: true })
			showSuccess(t('libresign', 'User created!'))
		} catch (err) {
			showError(err.response.data.message)
			console.info(err.response)
			commit('setError', err.response.data.message)
		}
	},
	CREATE_PFX: async ({ commit, dispatch }, { signPassword }) => {
		try {
			dispatch('CLEAR_ERROR')

			const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature'), {
				signPassword,
			})

			commit('setPfx', response.data.signature)

			commit('setHasPfx', true, { root: true })
			showSuccess(t('libresign', 'Password created!'))
		} catch (err) {
			showError(err.response.data.message)
			console.info(err.response)
			commit('setError', err.response.data.message)
		}
	},
	CLEAR_ERROR: ({ commit }) => {
		commit('setError', {})
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
