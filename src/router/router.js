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
import { selectAction } from '../helpers/SelectAction'
import { getInitialState } from '../services/InitialStateService'

const libresignVar = getInitialState()

const routes = [
	// public
	{
		path: '/p/sign/:uuid',
		redirect: { name: selectAction(libresignVar.action) },
	},
	{
		path: '/p/sign/:uuid/pdf',
		component: () => import('../views/SignPDF/SignPDF'),
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'SignPDF',
	}, {
		path: '/p/sign/:uuid/sign-in',
		component: () => import('../views/CreateUser'),
		name: 'CreateUser',
		props: (route) => ({
			messageToast: t('libresign', 'You need to create an account to sign this file.'),
		}),
	}, {
		path: '/p/sign/:uuid/error',
		component: () => import('../views/DefaultPageError'),
		name: 'DefaultPageError',
	}, {
		path: '/p/sign/:uuid/success',
		component: () => import('../views/DefaultPageSuccess'),
		name: 'DefaultPageSuccess',
	},
	{
		path: '/p/validation/:uuid',
		component: () => import('../views/Validation'),
		name: 'validationFilePublic',
		props: (route) => ({
			uuid: route.params.uuid,
		}),
	},
	{
		path: '/reset-password',
		component: () => import('../views/ResetPassword'),
		name: 'ResetPassword',
	},
	{
		path: '/f/validation',
		component: () => import('../views/Validation'),
		name: 'validation',
	},
	{
		path: '/f/validation/:uuid',
		component: () => import('../views/Validation'),
		name: 'validationFile',
		props: (route) => ({
			uuid: route.params.uuid,
		}),
	},
	{
		path: '/f/timeline/sign',
		component: () => import('../views/Timeline/Timeline.vue'),
		name: 'signFiles',
	},
	{
		path: '/f/sign/:uuid',
		component: () => import('../views/SignDetail/SignDetail.vue'),
		name: 'f.sign.detail',
	},
	{
		path: '/f/request',
		component: () => import('../views/Request'),
		name: 'requestFiles',
	}, {
		path: '/f/account',
		component: () => import('../views/Account/Account'),
		name: 'Account',
	}, {
		path: '/f/create-password',
		component: () => import('../views/CreatePassword'),
		name: 'CreatePassword',
	},
]

export default routes
