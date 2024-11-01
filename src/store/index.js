/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue, { set } from 'vue'
import Vuex, { Store } from 'vuex'

import files from './modules/files.js'
import settings from './modules/settings.js'
import sidebar from './modules/sidebar.js'

Vue.use(Vuex)

export default new Store({

	state: {
		errors: [],
		pdfData: {},
	},

	mutations: {
		setError: (state, errorMessage) => {
			state.errors = errorMessage
		},
		setPdfData(state, pdfData) {
			if (pdfData.pdf.url) {
				set(state.pdfData, 'url', pdfData.pdf.url)
			} else {
				set(state.pdfData, 'base64', `data:application/pdf;base64,${pdfData.pdf.base64}`)
			}
			set(state.pdfData, 'description', pdfData.description)
			set(state.pdfData, 'filename', pdfData.filename)
		},
		setHasPfx(state, haspfx) {
			set(state.settings, 'hasSignatureFile', haspfx)
		},
	},

	actions: {
		SET_ERROR: ({ commit }, errorMessage) => {
			commit('setError', errorMessage)
		},
		RESET_ERROR: ({ commit }) => {
			commit('setError', [])
		},
	},

	getters: {
		getErrors: state => {
			return state.errors
		},
		getHasPfx(state) {
			return state.settings.hasSignatureFile
		},
		getPdfData(state) {
			return state.pdfData
		},
	},

	modules: {
		settings,
		sidebar,
		files,
	},
})
