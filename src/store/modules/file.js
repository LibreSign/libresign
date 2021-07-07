import { getFileList, getInfo } from '@/services/api/file'

const state = () => ({
	files: [],
	currentFile: {},
	signers: [],
})

const getters = {
	getFiles: (state, getters, rootState) => {
		return state.files
	},
	getCurrentFile: (state) => {
		return state.currentFile
	},
	getSigners: (state) => {
		return state.signers
	},
}

const actions = {
	setCurrentFile({ commit, state }, file) {
		commit('setCurrentFile', file)
		commit('setSigners', file.signers)
	},

	async getSignersFile({ commit, state }, fileId) {
		const response = await getInfo(fileId)
		if (response.data.signers) {
			commit('setSigners', response.data.signers)
		} else if (response.data.errors) {
			commit('setSigners', [])
		}
	},

	async getAllFiles({ commit }) {
		const files = await getFileList()

		commit('setFiles', files)
	},
}

const mutations = {
	setFiles(state, files) {
		state.files = files
	},
	setCurrentFile(state, currentFile) {
		state.currentFile = currentFile
	},
	setSigners(state, signers) {
		if (state.signers !== signers) {
			console.info('óld')
		}
		state.signers = signers
	},
}

export default {
	namespaced: true,
	state,
	getters,
	actions,
	mutations,
}
