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

import External from '../views/AssignExternal'
import CreateUser from '../views/CreateUser'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/libresign/external', ''),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			name: 'ExternalRoot',
			redirect: {
				name: 'AssignPDF',
			},
		}, {
			path: '/assign-pdf',
			component: External,
			name: 'AssignPDF',
		}, {
			path: '/create-user',
			component: CreateUser,
			name: 'CreateUser',
		},
	],
})
