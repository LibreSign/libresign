import userApi from '../../services/api/User'

const state = () => ({
	hasPfx: false,
})

const getters = {
	getPfx: (state, getters, rootState) => {
		return state.hasPfx
	},
}

const actions = {

}

const mutations = {

}

export default {
	namespaced: true,
	state,
	getters,
	actions,
	mutations,
}
