const state = {
	file: {},
	files: [],
	filterFiles: [],
}

const mutations = {
	setFile: (state, file) => {
		state.file = file
	},
	setFiles: (state, files) => {
		state.files = files
	},
}

const actions = {
	SET_FILE: ({ commit }, file) => {
		commit('setFile', file)
	},
	SET_FILES: ({ commit }, files) => {
		commit('setFiles', files)
	},
}

const getters = {
	getFile: state => {
		return state.file
	},
	getFiles: state => {
		return state.files
	},
	pendingFilter: state => {
		return state.files.slice().filter(
			(a) => (a.status === 'pending')).sort(
			(a, b) => (a.request_date < b.request_date) ? 1 : -1)
	},
	signedFilter: state => {
		return state.files.slice().filter(
			(a) => (a.status === 'signed')).sort(
			(a, b) => (a.request_date < b.request_date) ? 1 : -1)
	},
	orderFiles: state => {
		return state.files.sort((a, b) => (a.request_date < b.request_date) ? 1 : -1)
	},
}

export default {
	namespaced: true,
	state,
	mutations,
	actions,
	getters,
}
