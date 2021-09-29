import { showError, showSuccess } from '@nextcloud/dialogs'
import { SignaturesService } from '../../services/SignaturesService'

const apiClient = new SignaturesService()

const state = {
	signatures: {
		file: {},
	},
	initials: {
		file: {},
	},
}
const getters = {
	haveSignatures: state => {
		if ('base64' in state.signatures.file) {
			return state.signatures.file.base64.length > 0
		} else {
			return false
		}
	},
	haveInitials: state => {
		if ('base64' in state.initials.file) {
			return state.initials.file.base64.length > 0
		} else {
			return false
		}
	},
}

const mutations = {
	setSignature: (state, signature) => {
		state.signatures = signature
	},
	setInitials: (state, initial) => {
		state.initials = initial
	},
}

const actions = {
	FETCH_SIGNATURES: async({ commit }) => {
		const response = await apiClient.loadSignatures()
		const signature = response.filter(arr => arr.type === 'signature')[0]
		const initials = response.filter(arr => arr.type === 'initials')[0]

		commit('setSignature', signature)
		commit('setInitials', initials)
	},
	GET_ELEMENTS: async({ commit }, { id }) => {
		const response = await apiClient.newSignature(id)

		if (response.type === 'signature') {
			commit('signature/setSignature', response)
		} else {
			commit('signature/setInitials', response)
		}
	},
	NEW_SIGNATURE: async({ commit }, { type, file }) => {
		try {
			const response = await apiClient.newElement({ type, file })
			showSuccess(response.message)
			commit('modal/setStatus', false, { root: true })
		} catch (err) {
			showError(err)
		}
	},

}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
