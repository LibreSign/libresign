/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHistory, type Router, type RouteRecordRaw } from 'vue-router'

import { loadState } from '@nextcloud/initial-state'
import { getRootUrl, generateUrl } from '@nextcloud/router'

import { isExternal } from '../helpers/isExternal'
import { selectAction } from '../helpers/SelectAction'

/**
 * Generate Vue Router base url
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
		redirect: (to) => {
			const action = selectAction(loadState('libresign', 'action', 0), to, { path: '/' })
			if (action) {
				return {
					name: action,
					params: to.params as Record<string, string>,
				}
			}
			return {
				name: 'SignPDFExternal',
				params: to.params as Record<string, string>,
			}
		},
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
		meta: {
			hideLeftSidebar: true,
		},
		beforeEnter: (to, from, next) => {
			const action = selectAction(loadState('libresign', 'action', 0), to, from)
			if (action) {
				if (to.name !== 'IncompleteExternal') {
					next({
						name: action,
						params: to.params as Record<string, string>,
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
		redirect: () => {
			const canRequestSign = loadState('libresign', 'can_request_sign', false)
			return { name: canRequestSign ? 'requestFiles' : 'fileslist' }
		},
	},
	{
		path: '/',
		redirect: () => {
			const canRequestSign = loadState('libresign', 'can_request_sign', false)
			return { name: canRequestSign ? 'requestFiles' : 'fileslist' }
		},
	},
	{
		path: '/f/incomplete',
		name: 'Incomplete',
		meta: {
			hideLeftSidebar: true,
		},
		beforeEnter: (to, from, next) => {
			const action = selectAction(loadState('libresign', 'action', 0), to, from)
			if (action) {
				if (to.name !== 'Incomplete') {
					next({
						name: action,
						params: to.params as Record<string, string>,
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
		path: '/f/id-docs/approve/:uuid',
		name: 'IdDocsApprove',
		component: () => import('../views/SignPDF/SignPDF.vue'),
		props: true,
	},
	{
		path: '/f/account',
		name: 'Account',
		component: () => import('../views/Account/Account.vue'),
	},
	{
		path: '/f/docs/id-docs/validation',
		name: 'DocsIdDocsValidation',
		component: () => import('../views/Documents/IdDocsValidation.vue'),
	},
	{
		path: '/f/crl/management',
		name: 'CrlManagement',
		component: () => import('../views/CrlManagement/CrlManagement.vue'),
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
]

const router: Router = createRouter({
	history: createWebHistory(generateWebBasePath()),
	linkActiveClass: 'active',
	routes,
})

router.beforeEach((to, from, next) => {
	const actionElement = document.querySelector('#initial-state-libresign-action')
	let action
	if (actionElement) {
		const actionValue = loadState('libresign', 'action', 0)
		to.params.action = String(actionValue)
		action = selectAction(actionValue, to, from)
		document.querySelector('#initial-state-libresign-action')?.remove()
	}
	if (Object.hasOwn(to, 'name') && typeof to.name === 'string' && !to.name.endsWith('External') && isExternal(to, from)) {
		next({
			name: to.name + 'External',
			params: to.params as Record<string, string>,
		})
	} else if (action) {
		next({
			name: action,
			params: to.params as Record<string, string>,
		})
	} else {
		next()
	}
})

export default router
