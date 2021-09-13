const state = {
	signatures: [
		{ ol: true },
	],
	initials: [],
}
const getters = {
	haveSignatures: state => {
		return state.signatures.length > 0
	},
	haveInitials: state => {
		return state.initials.length > 0
	},
}

const mutations = {}

const actions = {}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
