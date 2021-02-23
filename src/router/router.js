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

import SelectAction from '../middlewares/SelectAction'

const routes = [
	{
		path: '/',
		name: 'Home',
		component: () => import('../views/CreateSubscription'),
	}, {
		path: '/sign/:uuid',
		redirect: { name: OC.appConfig.libresign ? SelectAction(OC.appConfig.libresign.action) : 'Home' },
	}, {
		path: '/sign/:uuid#Sign',
		component: () => import('../views/SignPDF'),
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'SignPDF',
	}, {
		path: '/sign/:uuid#Create',
		component: () => import('../views/CreateUser'),
		name: 'CreateUser',
		props: (route) => ({
			messageToast: 'User not found for this email.',
		}),
	}, {
		path: '/sign/:uuid#error',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
		props: (route) => ({ error: { message: OC.appConfig.libresign.errors } }),
	},
	{
		path: '/success',
		component: () => import('../views/DefaultPageSuccess'),
		name: 'DefaultPageSuccess',
	},
]

export default routes
