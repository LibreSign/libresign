import { showError, showSuccess } from '@nextcloud/dialogs'
import { SignaturesService } from '../../services/SignaturesService'

const apiClient = new SignaturesService()

const state = {
	signatures: {},
	initials: {},
}
const getters = {
	haveSignatures: state => {
		return state.signatures.length > 0
	},
	haveInitials: state => {
		return state.initials.length > 0
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
		const signature = response.filter(arr => arr.type === 'signature')
		const initials = response.filter(arr => arr.type === 'initials')

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
