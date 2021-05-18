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
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import routes from './router'
import store from '../store'

Vue.use(Router)

const libresignVar = JSON.parse(loadState('libresign', 'config'))

const router = new Router({
	mode: 'history',
	base: generateUrl('/apps/libresign/', ''),
	linkActiveClass: 'active',
	routes,
})

router.beforeEach((to, from, next) => {
	if (to.query.redirect === 'CreatePassword') {
		next({ name: 'CreatePassword' })
	}

	if (libresignVar) {
		if (libresignVar.sign) {
			store.commit('setPdfData', libresignVar.sign)
		}
		if (libresignVar.user) {
			store.commit('setUser', libresignVar.user)
		}
		if (libresignVar.errors) {
			store.commit('setError', libresignVar.errors)
		}
	}
	next()
})

export default router
