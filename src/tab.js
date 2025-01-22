/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
