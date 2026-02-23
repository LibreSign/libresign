/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHistory, type RouteRecordRaw, type Router } from 'vue-router'
import { loadState } from '@nextcloud/initial-state'
import { getRootUrl, generateUrl } from '@nextcloud/router'

import { isExternal } from '../helpers/isExternal'
import { selectAction } from '../helpers/SelectAction'

/**
 * @return {string} Vue Router base url
 */
function generateWebBasePath(): string {
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	const webRootWithIndexPHP = getRootUrl() + '/index.php'
	const doesURLContainIndexPHP = window.location.pathname.startsWith(webRootWithIndexPHP)
	return generateUrl('/apps/libresign', {}, {
		noRewrite: doesURLContainIndexPHP,
	})
}

const routes: RouteRecordRaw[] = [
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
		component: () => import('../../views/SignPDF/SignPDF.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid/sign-in',
		name: 'CreateAccountExternal',
		component: () => import('../../views/CreateAccount.vue'),
		props: true,
	},
	{
		path: '/p/error',
		name: 'DefaultPageErrorExternal',
		component: () => import('../../views/DefaultPageError.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid/renew/email',
		name: 'RenewEmailExternal',
		component: () => import('../../views/RenewEmail.vue'),
	},
	{
		path: '/p/validation/:uuid',
		name: 'ValidationFileExternal',
		component: () => import('../../views/Validation.vue'),
		props: true,
	},
	{
		path: '/validation/:uuid',
		name: 'ValidationFileShortUrl',
		component: () => import('../../views/Validation.vue'),
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
		component: () => import('../../views/IncompleteCertification.vue'),
	},

	// internal pages
	{
		path: '/f/',
		beforeEnter: (to, from, next) => {
			const canRequestSign = loadState('libresign', 'can_request_sign', false)
			if (canRequestSign) {
				next({ name: 'requestFiles' })
			} else {
				next({ name: 'fileslist' })
			}
		},
	},
	{
		path: '/',
		beforeEnter: (to, from, next) => {
			const canRequestSign = loadState('libresign', 'can_request_sign', false)
			if (canRequestSign) {
				next({ name: 'requestFiles' })
			} else {
				next({ name: 'fileslist' })
			}
		},
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
		component: () => import('../../views/IncompleteCertification.vue'),
	},
	{
		path: '/f/validation',
		name: 'validation',
		component: () => import('../../views/Validation.vue'),
	},
	{
		path: '/f/validation/:uuid',
		name: 'ValidationFile',
		component: () => import('../../views/Validation.vue'),
		props: true,
	},
	{
		path: '/f/filelist/sign',
		name: 'fileslist',
		component: () => import('../../views/FilesList/FilesList.vue'),
	},
	{
		path: '/f/request',
		name: 'requestFiles',
		beforeEnter: (to, from, next) => {
			if (!loadState('libresign', 'can_request_sign', false)) {
				next({ path: '/' })
				return
			}
			next()
		},
		component: () => import('../../views/Request.vue'),
	},
	{
		path: '/f/sign/:uuid/pdf',
		name: 'SignPDF',
		component: () => import('../../views/SignPDF/SignPDF.vue'),
		props: true,
	},
	{
		path: '/f/id-docs/approve/:uuid',
		name: 'IdDocsApprove',
		component: () => import('../../views/SignPDF/SignPDF.vue'),
		props: true,
	},
	{
		path: '/f/account',
		name: 'Account',
		component: () => import('../../views/Account/Account.vue'),
	},
	{
		path: '/f/docs/id-docs/validation',
		name: 'DocsIdDocsValidation',
		component: () => import('../../views/Documents/IdDocsValidation.vue'),
	},
	{
		path: '/f/crl/management',
		name: 'CrlManagement',
		component: () => import('../../views/CrlManagement/CrlManagement.vue'),
	},
	{
		path: '/f/create-password',
		name: 'CreatePassword',
		component: () => import('../../views/CreatePassword.vue'),
	},
	{
		path: '/f/reset-password',
		name: 'ResetPassword',
		component: () => import('../../views/ResetPassword.vue'),
	},
]

const router: Router = createRouter({
	history: createWebHistory(generateWebBasePath()),
	routes,
})

router.beforeEach((to, from, next) => {
	const actionElement = document.querySelector('#initial-state-libresign-action')
	let action: any
	if (actionElement) {
		const params = to.params as Record<string, any>
		params.action = loadState('libresign', 'action', '')
		action = selectAction(params.action, to, from)
		document.querySelector('#initial-state-libresign-action')?.remove()
	}
	const toName = to.name as string
	if (toName && typeof toName === 'string' && !toName.endsWith('External') && isExternal(to, from)) {
		next({
			name: toName + 'External',
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
