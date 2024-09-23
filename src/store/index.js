/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
 * @author Vinicios Gomes <viniciosgomesviana@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
