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

import Vue from 'vue'
import Router from 'vue-router'

import { loadState } from '@nextcloud/initial-state'
import { getRootUrl, generateUrl } from '@nextcloud/router'

import { isExternal } from '../helpers/isExternal.js'
import { selectAction } from '../helpers/SelectAction.js'

Vue.use(Router)

/**
 * @return {string} Vue Router base url
 */
function generateWebBasePath() {
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	const webRootWithIndexPHP = getRootUrl() + '/index.php'
	const doesURLContainIndexPHP = window.location.pathname.startsWith(webRootWithIndexPHP)
	return generateUrl('/apps/libresign', {}, {
		noRewrite: doesURLContainIndexPHP,
	})
}

const router = new Router({
	mode: 'history',
	base: generateWebBasePath(),
	linkActiveClass: 'active',
	routes: [
		// public
		{
			path: '/p/sign/:uuid',
			beforeEnter: (to, from, next) => {
				const action = selectAction(loadState('libresign', 'action', ''), to, from)
				if (action !== undefined) {
					if (to.name !== 'incomplete') {
						next({
							name: action,
							params: to.params,
						})
						return
					}
				}
				next()
			},
			props: true,
		},
		{
			path: '/p/sign/:uuid/pdf',
			name: 'SignPDFExternal',
			component: () => import('../views/SignPDF/SignPDF.vue'),
			props: true,
		},
		{
			path: '/p/sign/:uuid/sign-in',
			name: 'CreateAccountExternal',
			component: () => import('../views/CreateAccount.vue'),
			props: true,
		},
		{
			path: '/p/error',
			name: 'DefaultPageErrorExternal',
			component: () => import('../views/DefaultPageError.vue'),
			props: true,
		},
		{
			path: '/p/sign/:uuid/renew/email',
			name: 'RenewEmailExternal',
			component: () => import('../views/RenewEmail.vue'),
		},
		{
			path: '/p/validation/:uuid',
			name: 'ValidationFileExternal',
			component: () => import('../views/Validation.vue'),
			props: true,
		},
		{
			path: '/validation/:uuid',
			name: 'ValidationFileShortUrl',
			component: () => import('../views/Validation.vue'),
			props: true,
		},
		{
			path: '/p/incomplete',
			name: 'IncompleteExternal',
			beforeEnter: (to, from, next) => {
				const action = selectAction(loadState('libresign', 'action', ''), to, from)
				if (action !== undefined) {
					if (to.name !== 'IncompleteExternal') {
						next({
							name: action,
							params: to.params,
						})
						return
					}
				}
				next()
			},
			component: () => import('../views/IncompleteCertification.vue'),
		},

		// internal pages
		{
			path: '/f/',
			redirect: { name: 'requestFiles' },
		},
		{
			path: '/',
			redirect: { name: 'requestFiles' },
		},
		{
			path: '/f/incomplete',
			name: 'Incomplete',
			beforeEnter: (to, from, next) => {
				const action = selectAction(loadState('libresign', 'action', ''), to, from)
				if (action !== undefined) {
					if (to.name !== 'Incomplete') {
						next({
							name: action,
							params: to.params,
						})
						return
					}
				}
				next()
			},
			component: () => import('../views/IncompleteCertification.vue'),
		},
		{
			path: '/f/validation',
			name: 'validation',
			component: () => import('../views/Validation.vue'),
		},
		{
			path: '/f/validation/:uuid',
			name: 'ValidationFile',
			component: () => import('../views/Validation.vue'),
			props: true,
		},
		{
			path: '/f/filelist/sign',
			name: 'fileslist',
			component: () => import('../views/FilesList/FilesList.vue'),
		},
		{
			path: '/f/request',
			name: 'requestFiles',
			beforeEnter: (to, from, next) => {
				if (!loadState('libresign', 'can_request_sign', false)) {
					return { path: '/' }
				}
				next()
			},
			component: () => import('../views/Request.vue'),
		},
		{
			path: '/f/sign/:uuid/pdf',
			name: 'SignPDF',
			component: () => import('../views/SignPDF/SignPDF.vue'),
			props: true,
		},
		{
			path: '/f/account/files/approve/:uuid',
			name: 'AccountFileApprove',
			component: () => import('../views/SignPDF/SignPDF.vue'),
			props: true,
		},
		{
			path: '/f/account',
			name: 'Account',
			component: () => import('../views/Account/Account.vue'),
		},
		{
			path: '/f/docs/accounts/validation',
			name: 'DocsAccountValidation',
			component: () => import('../views/Documents/AccountValidation.vue'),
		},
		{
			path: '/f/create-password',
			name: 'CreatePassword',
			component: () => import('../views/CreatePassword.vue'),
		},
		{
			path: '/f/reset-password',
			name: 'ResetPassword',
			component: () => import('../views/ResetPassword.vue'),
		},
	],
})

router.beforeEach((to, from, next) => {
	const actionElement = document.querySelector('#initial-state-libresign-action')
	let action
	if (actionElement) {
		to.params.action = loadState('libresign', 'action', '')
		action = selectAction(to.params.action, to, from)
		document.querySelector('#initial-state-libresign-action')?.remove()
	}
	if (Object.hasOwn(to, 'name') && typeof to.name === 'string' && !to.name.endsWith('External') && isExternal(to, from)) {
		next({
			name: to.name + 'External',
			params: to.params,
		})
	} else if (action !== undefined) {
		next({
			name: action,
			params: to.params,
		})
	} else {
		next()
	}
})

export default router
