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

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { translate, translatePlural } from '@nextcloud/l10n'

import AppFilesTab from './Components/RightSidebar/AppFilesTab.vue'

import './actions/openInLibreSignAction.js'
import './plugins/vuelidate.js'

import './style/icons.scss'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

if (!window.OCA.Libresign) {
	window.OCA.Libresign = {}
}

Vue.use(PiniaVuePlugin)

const pinia = createPinia()

const isEnabled = function(fileInfo) {
	if (fileInfo?.isDirectory() || !loadState('libresign', 'certificate_ok')) {
		return false
	}

	window.OCA.Libresign.fileInfo = fileInfo

	const mimetype = fileInfo.get('mimetype') || ''
	if (mimetype === 'application/pdf') {
		return true
	}

	return false
}

const View = Vue.extend(AppFilesTab)
let TabInstance = null

window.addEventListener('DOMContentLoaded', () => {
	/**
	 * Register a new tab in the sidebar
	 */
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
			id: 'libresign',
			name: t('libresign', 'LibreSign'),
			icon: 'icon-rename',
			enabled: isEnabled,

			async mount(el, fileInfo, context) {
				if (TabInstance) {
					TabInstance.$destroy()
				}

				TabInstance = new View({
					// Better integration with vue parent component
					parent: context,
					pinia,
				})

				// Only mount after we hahve all theh info we need
				await TabInstance.update(fileInfo)

				TabInstance.$mount(el)
			},
			update(fileInfo) {
				TabInstance.update(fileInfo)
			},
			destroy() {
				TabInstance.$destroy()
				TabInstance = null
			},
		}))
	}
})
