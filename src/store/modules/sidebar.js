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
		if (status === true) {
			if (state.status === true) {
				return
			}
			commit('setStatus', status)
		} else {
			commit('setStatus', status)
		}
	},
	RESET: ({ commit }) => {
		commit('setStatus', false)
	},
}

export default {
	namespaced: true,
	state,
	mutations,
	getters,
	actions,
}
