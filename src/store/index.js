/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
 * @author Vinicios Gomes <viniciosgomesviana@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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

import Vue from 'vue'
import Vuex, { Store } from 'vuex'
import { loadState } from '@nextcloud/initial-state'

Vue.use(Vuex)

const libresignVar = JSON.parse(loadState('libresign', 'config'))

export default new Store({

	state: {
		errors: [],
		pdfData: {},
		user: {},
		settings: {},
	},

	mutations: {
		setUser(state, user) {
			this.state.user = user
		},
		setPdfData(state, pdfData) {
			if (pdfData.pdf.url) {
				Vue.set(state.pdfData, 'url', pdfData.pdf.url)
			} else {
				Vue.set(state.pdfData, 'base64', `data:application/pdf;base64,${pdfData.pdf.base64}`)
			}
			Vue.set(state.pdfData, 'description', pdfData.description)
			Vue.set(state.pdfData, 'filename', pdfData.filename)
		},
		setSettings(state, settings) {
			Vue.set(state.settings, 'data', settings)
		},
		setHasPfx(state, haspfx) {
			Vue.set(state.settings.data, 'hasSignatureFile', haspfx)
		},
		setError(state, errors) {
			Vue.set(state.errors, errors)
		},
	},

	getters: {
		getError(state) {
			return libresignVar.errors
		},
		getSettings(state) {
			return state.settings
		},
		getHasPfx(state) {
			return state.settings.data.hasSignatureFile
		},
		getPdfData(state) {
			return state.pdfData
		},
		getUser(state) {
			return state.user
		},
	},

	modules: {},
})
