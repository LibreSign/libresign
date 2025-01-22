/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'

import { getRequestToken } from '@nextcloud/auth'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'

import App from './App.vue'

import './plugins/vuelidate.js'
import router from './router/router.js'

import './assets/styles/main.scss'
import 'vue-advanced-cropper/dist/style.css'

if (window.OCA && !window.OCA.LibreSign) {
	Object.assign(window.OCA, { LibreSign: {} })
	console.debug('OCA.LibreSign initialized')
}

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('libresign', '', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(PiniaVuePlugin)

const pinia = createPinia()

export default new Vue({
	el: '#content',
	name: 'LibresignApp',
	router,
	pinia,
	render: h => h(App),
})
