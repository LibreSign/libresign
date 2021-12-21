const state = {
	documents: [],
}

const getters = {
	documents: (state) => {
		return [
			{ name: 'Passaporte' },
			{ name: 'RG', status: 'approval' },
			{ name: 'CPF', status: 'approved' },
			{ name: 'CNH', status: 'reproved' },
		]
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
