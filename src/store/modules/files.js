import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

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
	FETCH_FILE_TO_SIGN: async() => {
	},
	SET_FILE: ({ commit }, file) => {
		commit('setFile', file)
	},
	SET_FILES: ({ commit }, files) => {
		commit('setFiles', files)
	},
	SET_FILE_TO_SIGN: ({ commit }, data) => {
		commit('setFileToSign', data)
	},
	GET_ALL_FILES: async({ dispatch }) => {
		try {
			const response = await axios.get(generateUrl('/apps/libresign/api/0.1/file/list'))
			dispatch('SET_FILES', response.data.data)
		} catch (err) {
			showError('An error occurred while fetching the files')
		}
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
	getFileToSign: state => state.fileToSign,
	fileToBeSigned: state => state.file.file,
}

export default {
	namespaced: true,
	state,
	mutations,
	actions,
	getters,
}
