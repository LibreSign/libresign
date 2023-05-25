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

import Vue from 'vue'
import Router from 'vue-router'
import routes from './router.js'
import store from '../store/index.js'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

const libresignVar = store.state.settings

const base = generateUrl('/apps/libresign')

const router = new Router({
	mode: 'history',
	base,
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
		if (libresignVar.errors) {
			store.commit('setError', libresignVar.errors)
		}
	}
	next()
})

export default router
