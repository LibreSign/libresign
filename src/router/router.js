/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
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

import { translate as t } from '@nextcloud/l10n'
import { selectAction } from '../helpers/SelectAction.js'
import { loadState } from '@nextcloud/initial-state'

const libresignVar = loadState('libresign', 'config')
const isCompleteAdminConfig = libresignVar?.settings?.certificateOk;
const initUrl =  isCompleteAdminConfig ? 'requestFiles' : 'incomplete';

const routes = [
	{
		path: '/reset-password',
		component: () => import('../views/ResetPassword.vue'),
		name: 'ResetPassword',
	},

	// public
	{
		path: '/p/account/files/approve/:uuid',
		component: () => import('../views/SignPDF/SignPDF.vue'),
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'AccountFileApprove',
	},
	{
		path: '/p/sign/:uuid',
		redirect: { name: selectAction(libresignVar.action) },
	},
	{
		path: '/p/sign/:uuid/pdf',
		component: () => import('../views/SignPDF/SignPDF.vue'),
		props: (route) => ({ uuid: route.params.uuid, redirect: false }),
		name: 'SignPDF',
	}, {
		path: '/p/sign/:uuid/sign-in',
		component: () => import('../views/CreateUser.vue'),
		name: 'CreateUser',
		props: (route) => ({
			messageToast: t('libresign', 'You need to create an account to sign this file.'),
		}),
	}, {
		path: '/p/sign/:uuid/error',
		component: () => import('../views/DefaultPageError.vue'),
		name: 'DefaultPageError',
	}, {
		path: '/p/sign/:uuid/success',
		component: () => import('../views/DefaultPageSuccess.vue'),
		name: 'DefaultPageSuccess',
	},
	{
		path: '/p/validation/:uuid',
		component: () => import('../views/Validation.vue'),
		name: 'validationFilePublic',
		props: (route) => ({
			uuid: route.params.uuid,
		}),
	},

	// internal pages
	{
		path: '/f/',
		redirect: { name: initUrl },
	},
	{
		path: '/',
		redirect: { name: initUrl },
	},
	{
		path: '/f/incomplete',
		component: () => import('../views/IncompleteCertification.vue'),
		name: 'incomplete',
	},
	{
		path: '/f/validation',
		component: () => import('../views/Validation.vue'),
		name: 'validation',
	},
	{
		path: '/f/validation/:uuid',
		component: () => import('../views/Validation.vue'),
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
		component: () => import('../views/Request.vue'),
		name: 'requestFiles',
	},
	{
		path: '/f/account',
		component: () => import('../views/Account/Account.vue'),
		name: 'Account',
	},
	{
		path: '/f/docs/accounts/validation',
		component: () => import('../views/Documents/AccountValidation.vue'),
		name: 'DocsAccountValidation',
	},
	{
		path: '/f/create-password',
		component: () => import('../views/CreatePassword.vue'),
		name: 'CreatePassword',
	},
]

export default routes
