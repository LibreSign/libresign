const state = {
	file: {},
	files: [],
	filterFiles: [],
	fileToSign: {

	},
}

const mutations = {
	setFile: (state, file) => {
		state.file = file
	},
	setFiles: (state, files) => {
		state.files = files
	},
	setFileToSign: (state, data) => {
		state.fileToSign = data
	},
}

const actions = {
	SET_FILE_TO_SIGN: ({ commit }, data) => {
		commit('setFileToSign', data)
	},
}

const getters = {
	getFile: state => {
		return state.file
	},
	getFileToSign: state => state.fileToSign,
}

export default {
	namespaced: true,
	state,
	mutations,
	actions,
	getters,
}
