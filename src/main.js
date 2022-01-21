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
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { sync } from 'vuex-router-sync'
import { translate, translatePlural } from '@nextcloud/l10n'

import './plugins/vuelidate'
import './directives/VTooltip'
import './init'

import './assets/styles/main.scss'

import 'vue-advanced-cropper/dist/style.css'

import App from './App'
import router from './router'
import store from './store'

Vue.mixin({ methods: { t, n } })
Vue.prototype.t = translate
Vue.prototype.n = translatePlural

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('libresign', '', 'js/')

sync(store, router)

Vue.prototype.t = t
Vue.prototype.n = n

Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

if (window.location.pathname.split('/')[1] === 'index.php' && OC.config.modRewriteWorking) {
	router.push({ name: 'home' })
}

store.dispatch('user/GET_INITIAL_SETTINGS')

export default new Vue({
	el: '#content',
	name: 'LibresignApp',
	router,
	store,
	render: h => h(App),
})
