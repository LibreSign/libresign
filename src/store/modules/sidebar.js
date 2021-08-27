const state = {
	status: false,
}

const mutations = {
	setStatus(state, status) {
		state.status = status
	},
}

const getters = {
	getStatus(state) {
		return state.status
	},
}

const actions = {
	setStatus({ commit }, status) {
		commit('setStatus', status)
	},
}

export default {
	namespaced: true,
	state,
	mutations,
	getters,
	actions,
}
