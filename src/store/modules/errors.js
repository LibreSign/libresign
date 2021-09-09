const state = {
	code: 0,
	message: '',
}

const getters = {
	getErrorsCode: (state) => {
		return state.code
	},
	getErrorMessage: state => {
		return state.message
	},
	getError: state => {
		return `${state.code} - ${state.message}`
	},
}

const mutations = {
	setErrorCode: (state, code) => {
		state.code = code
	},
	setErrorMessage: (state, message) => {
		state.message = message
	},
}

const actions = {
	CLEAN: ({ commit }) => {
		commit('setErrorCode', 0)
		commit('setErrorMessage', '')
	},
	SET_ERROR: ({ commit }, { code, message }) => {
		commit('setErrorCode', code)
		commit('setErrorMessage', message)
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
