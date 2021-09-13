const state = {
	status: false,
}

const getters = {
	getStatus: (state) => {
		return state.status
	},
}

const mutations = {
	setStatus: (state, status) => {
		state.status = status
	},
}

const actions = {
	OPEN_MODAL: ({ commit }) => {
		commit('setStatus', true)
	},
	CLOSE_MODAL: ({ commit }) => {
		commit('setStatus', false)
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
