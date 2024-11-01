/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const state = {
	code: 0,
	message: '',
}

const getters = {
	getErrorCode: (state) => {
		return state.code
	},
	getErrorMessage: state => {
		return state.message
	},
	getError: state => {
		return `${state.code} - ${state.message}`
	},
}

const mutations = {
	setErrorCode: (state, code) => {
		state.code = code
	},
	setErrorMessage: (state, message) => {
		state.message = message
	},
}

const actions = {
	CLEAN: ({ commit }) => {
		commit('setErrorCode', 0)
		commit('setErrorMessage', '')
	},
	SET_ERROR: ({ commit }, { code, message }) => {
		commit('setErrorCode', code)
		commit('setErrorMessage', message)
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
