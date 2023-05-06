import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const state = {
	file: {
		signers: [],
	},
}
const mutations = {
	setFile: (state, file) => {
		state.file = file
	},
}

const getters = {
	getFile: state => {
		return state.file
	},
	getSigners: state => {
		return state.file.signers
	},
}

const actions = {
	VALIDATE_BY_ID: async ({ commit, dispatch }, id) => {
		try {
			const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${id}`))
			await commit('setFile', response.data)
		} catch (err) {
			dispatch('error/SET_ERROR', { code: err.response.status, message: err.response.data.message }, { root: true })
		}
	},
	RESET: ({ commit }) => {
		commit('setFile', {})
	},
}

export default {
	namespaced: true,
	state,
	mutations,
	actions,
	getters,
}
