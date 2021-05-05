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

import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import SelectAction from '../helpers/SelectAction'

const libresignVar = JSON.parse(loadState('libresign', 'config'))

const routes = [
	{
		path: '/',
		name: 'Home',
		component: () => import('../views/CreateSubscription'),
	}, {
		path: '/sign/:uuid',
		redirect: { name: SelectAction(libresignVar.action) },
	}, {
		path: '/sign/:uuid',
		component: () => import('../views/SignPDF'),
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'SignPDF',
	}, {
		path: '/sign/:uuid',
		component: () => import('../views/CreateUser'),
		name: 'CreateUser',
		props: (route) => ({
			messageToast: t('libresign', 'You need to create an account to sign this file.'),
		}),
	}, {
		path: '/validation',
		component: () => import('../views/Validation'),
		name: 'validation',
	}, {
		path: '/sign/:uuid',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
	},
	{
		path: '/success',
		component: () => import('../views/DefaultPageSuccess'),
		name: 'DefaultPageSuccess',
	},
]

export default routes
