/* eslint-disable vue/match-component-file-name */
/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
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

import Home from '../views/Home'
import FormLibreSign from '../views/FormLibresign'
import CreateUser from '../views/CreateUser'
import External from '../views/External'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/libresign/', ''),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			component: Home,
			name: 'home',
		}, {
			path: '/FormLibreSign',
			component: FormLibreSign,
		}, {
			path: '/create-user',
			name: 'CreateUser',
			component: CreateUser,
		}, {
			path: '/external',
			name: 'ExternalRoute',
			component: External,
		},
	],
})
