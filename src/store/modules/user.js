const state = () => ({
	pfx: false,
	hableToRequestSign: false,
})

const getters = {
	getPfx: (state, getters, rootState) => {
		return state.pfx
	},
	getHableToRequestSign: (state, getters, rootState) => {
		return state.hableToRequestSign
	},
}

const actions = {
}

const mutations = {
	setHableToRequestSign(state, resp) {
		state.hableToRequestSign = resp
	},
	setPfx(state, newData) {
		state.pfx = newData
	},
}

export default {
	namespaced: true,
	state,
	getters,
	actions,
	mutations,
}
