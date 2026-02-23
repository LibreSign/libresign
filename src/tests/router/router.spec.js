/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeAll, beforeEach, vi, afterEach } from 'vitest'

// Mock @nextcloud packages that router components may import
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'test-user',
		displayName: 'Test User',
	})),
	getRequestToken: vi.fn(() => 'test-csrf-token'),
}))

vi.mock('@nextcloud/files', () => ({
	formatFileSize: vi.fn((size) => `${size} B`),
}))

vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

vi.mock('@nextcloud/initial-state')
vi.mock('@nextcloud/router')
vi.mock('../../helpers/isExternal.js')
vi.mock('../../helpers/SelectAction.js')

describe('router business rules', () => {
	let router
	let loadState
	let generateUrl
	let getRootUrl
	let isExternal
	let selectAction
	const getRoutes = () => router.options.routes || []

	beforeAll(async () => {
		const { loadState: loadStateModule } = await import('@nextcloud/initial-state')
		const { generateUrl: generateUrlModule, getRootUrl: getRootUrlModule } = await import('@nextcloud/router')
		const { isExternal: isExternalModule } = await import('../../helpers/isExternal.js')
		const { selectAction: selectActionModule } = await import('../../helpers/SelectAction.js')

		loadState = loadStateModule
		generateUrl = generateUrlModule
		getRootUrl = getRootUrlModule
		isExternal = isExternalModule
		selectAction = selectActionModule

		const routerModule = await import('../../router/router.js')
		router = routerModule.default
	})

	beforeEach(() => {
		vi.clearAllMocks()
		loadState.mockReturnValue('')
		generateUrl.mockImplementation(path => path)
		getRootUrl.mockReturnValue('')
		isExternal.mockReturnValue(false)
		selectAction.mockReturnValue(undefined)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('public routes structure', () => {
		it('has public sign route with uuid parameter', () => {
			const route = getRoutes().find(r => r.path === '/p/sign/:uuid')
			expect(route).toBeDefined()
			expect(route.path).toContain('uuid')
		})

		it('has public validation route with uuid parameter', () => {
			const route = getRoutes().find(r => r.path === '/p/validation/:uuid')
			expect(route).toBeDefined()
		})

		it('public routes use dynamic component imports', () => {
			const publicRoutes = getRoutes().filter(r => r.path.startsWith('/p/'))
			publicRoutes.forEach(route => {
				if (route.component) {
					expect(typeof route.component).toBe('function')
				}
			})
		})
	})

	describe('sign route beforeEnter guard', () => {
		let signRoute
		let beforeEnterGuard

		beforeEach(() => {
			signRoute = getRoutes().find(r => r.path === '/p/sign/:uuid')
			beforeEnterGuard = signRoute.beforeEnter
		})

		it('calls selectAction with loadState action', () => {
			selectAction.mockReturnValue(undefined)
			loadState.mockReturnValue('some-action')

			const to = { name: null, params: { uuid: 'test-uuid' } }
			const from = {}
			const next = vi.fn()

			beforeEnterGuard(to, from, next)

			expect(selectAction).toHaveBeenCalled()
		})

		it('redirects when action is determined and route is not incomplete', () => {
			selectAction.mockReturnValue('CreateAccountExternal')

			const to = { name: 'other-route', params: { uuid: 'test-uuid' } }
			const from = {}
			const next = vi.fn()

			beforeEnterGuard(to, from, next)

			expect(next).toHaveBeenCalledWith({
				name: 'CreateAccountExternal',
				params: { uuid: 'test-uuid' },
			})
		})

		it('does not redirect when already on incomplete route', () => {
			selectAction.mockReturnValue('SignPDFExternal')

			const to = { name: 'incomplete', params: { uuid: 'test-uuid' } }
			const from = {}
			const next = vi.fn()

			beforeEnterGuard(to, from, next)

			expect(next).toHaveBeenCalledWith()
		})

		it('proceeds without redirect when action is undefined', () => {
			selectAction.mockReturnValue(undefined)

			const to = { name: null, params: { uuid: 'test-uuid' } }
			const from = {}
			const next = vi.fn()

			beforeEnterGuard(to, from, next)

			expect(next).toHaveBeenCalledWith()
		})
	})

	describe('route parameter handling', () => {
		it('handles valid UUID in sign route', () => {
			const route = getRoutes().find(r => r.path === '/p/sign/:uuid/pdf')
			expect(route).toBeDefined()
			expect(route.path).toContain('uuid')
		})

		it('handles numeric parameter in validation route', () => {
			const resolved = router.resolve({
				path: '/p/validation/12345',
			})

			expect(resolved.params.uuid).toBeDefined()
		})
	})

	describe('internal vs external route separation', () => {
		it('internal routes do not start with /p/', () => {
			const internalRoutes = getRoutes().filter(r =>
				!r.path.startsWith('/p/')
			)

			expect(internalRoutes.length).toBeGreaterThan(0)
			internalRoutes.forEach(route => {
				expect(route.path).not.toMatch(/^\/p\//)
			})
		})

		it('all public routes start with /p/', () => {
			const publicRoutes = getRoutes().filter(r =>
				r.path.startsWith('/p/')
			)

			expect(publicRoutes.length).toBeGreaterThan(0)
			publicRoutes.forEach(route => {
				expect(route.path).toMatch(/^\/p\//)
			})
		})
	})

	describe('component lazy loading', () => {
		it('uses dynamic imports for main views', () => {
			const signPdfRoute = getRoutes().find(
				r => r.name === 'SignPDFExternal'
			)

			expect(signPdfRoute).toBeDefined()
				expect(typeof signPdfRoute.component).toBe('function')
		})

		it('dynamic component returns thenable', () => {
			const validationRoute = getRoutes().find(
				r => r.name === 'ValidationFileExternal'
			)

				const componentImport = validationRoute.component()
			expect(componentImport).toHaveProperty('then')
		})
	})

	describe('route props configuration', () => {
		it('passes props to routed components', () => {
			const signRoute = getRoutes().find(
				r => r.path === '/p/sign/:uuid'
			)

				expect(signRoute.props).toBe(true)
		})

		it('enables param passing to component', () => {
			const validationRoute = getRoutes().find(
				r => r.path === '/p/validation/:uuid'
			)

				expect(validationRoute.props).toBe(true)
		})
	})

	describe('router history mode', () => {
		it('uses history mode for clean URLs', () => {
			expect(router.options.history).toBeDefined()
		})

		it('sets correct link active class', () => {
			const linkActiveClass = router.options?.linkActiveClass
			expect(linkActiveClass).toBeDefined()
			expect(linkActiveClass).toBe('active')
		})
	})

	describe('root routes redirection based on can_request_sign', () => {
		const rootPaths = [
			{ path: '/', description: 'root path /' },
			{ path: '/f/', description: 'root path /f/' },
		]

		describe.each(rootPaths)('$description', ({ path }) => {
			let route
			let beforeEnterGuard

			beforeEach(() => {
				route = getRoutes().find(r => r.path === path) || getRoutes().find(r => r.path === path.replace(/\/$/, ''))
				beforeEnterGuard = route.beforeEnter
			})

			it.each([
				{ canRequestSign: true, expectedRoute: 'requestFiles', description: 'when user can request sign' },
				{ canRequestSign: false, expectedRoute: 'fileslist', description: 'when user cannot request sign' },
				{ canRequestSign: undefined, expectedRoute: 'fileslist', description: 'by default when can_request_sign is undefined' },
			])('redirects to $expectedRoute $description', ({ canRequestSign, expectedRoute }) => {
				loadState.mockImplementation((key, state, defaultValue) => {
					if (state === 'can_request_sign') {
						return canRequestSign !== undefined ? canRequestSign : defaultValue
					}
					return defaultValue
				})

				const to = {}
				const from = {}
				const next = vi.fn()

				beforeEnterGuard(to, from, next)

				expect(next).toHaveBeenCalledWith({ name: expectedRoute })
				expect(next).toHaveBeenCalledTimes(1)
			})
		})
	})
})
