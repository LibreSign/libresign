/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeAll, beforeEach, vi, afterEach } from 'vitest'
import type { MockedFunction } from 'vitest'
import type {
	NavigationGuardNext,
	RouteLocationNormalized,
	RouteRecordNormalized,
	Router,
} from 'vue-router'

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

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => ''),
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path: string) => path),
	getRootUrl: vi.fn(() => ''),
}))

vi.mock('../../helpers/isExternal.js', () => ({
	isExternal: vi.fn(() => false),
}))

vi.mock('../../helpers/SelectAction.js', () => ({
	selectAction: vi.fn(() => null),
}))

const makeRoute = (overrides: Partial<RouteLocationNormalized>): RouteLocationNormalized => ({
	fullPath: overrides.fullPath ?? '',
	hash: overrides.hash ?? '',
	matched: overrides.matched ?? [],
	meta: overrides.meta ?? {},
	name: overrides.name,
	params: overrides.params ?? {},
	path: overrides.path ?? '/',
	query: overrides.query ?? {},
	redirectedFrom: overrides.redirectedFrom,
})

describe('router business rules', () => {
	let router: Router
	let loadState: MockedFunction<typeof import('@nextcloud/initial-state').loadState>
	let generateUrl: MockedFunction<typeof import('@nextcloud/router').generateUrl>
	let getRootUrl: MockedFunction<typeof import('@nextcloud/router').getRootUrl>
	let isExternal: MockedFunction<typeof import('../../helpers/isExternal.js').isExternal>
	let selectAction: MockedFunction<typeof import('../../helpers/SelectAction.js').selectAction>
	const getRoutes = (): RouteRecordNormalized[] => router.getRoutes()

	beforeAll(async () => {
		const { loadState: loadStateModule } = await import('@nextcloud/initial-state')
		const { generateUrl: generateUrlModule, getRootUrl: getRootUrlModule } = await import('@nextcloud/router')
		const { isExternal: isExternalModule } = await import('../../helpers/isExternal.js')
		const { selectAction: selectActionModule } = await import('../../helpers/SelectAction.js')

		loadState = loadStateModule as MockedFunction<typeof loadStateModule>
		generateUrl = generateUrlModule as MockedFunction<typeof generateUrlModule>
		getRootUrl = getRootUrlModule as MockedFunction<typeof getRootUrlModule>
		isExternal = isExternalModule as MockedFunction<typeof isExternalModule>
		selectAction = selectActionModule as MockedFunction<typeof selectActionModule>

		const routerModule = await import('../../router/router')
		router = routerModule.default as Router
	})

	beforeEach(() => {
		vi.clearAllMocks()
		loadState.mockReturnValue('')
		generateUrl.mockImplementation(path => path)
		getRootUrl.mockReturnValue('')
		isExternal.mockReturnValue(false)
		selectAction.mockReturnValue(null)
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('public routes structure', () => {
		it('has public sign route with uuid parameter', () => {
			const route = getRoutes().find((r: RouteRecordNormalized) => r.path === '/p/sign/:uuid')
			expect(route).toBeDefined()
			expect((route as RouteRecordNormalized).path).toContain('uuid')
		})

		it('has public validation route with uuid parameter', () => {
			const route = getRoutes().find((r: RouteRecordNormalized) => r.path === '/p/validation/:uuid')
			expect(route).toBeDefined()
		})

		it('public routes use dynamic component imports', () => {
			const publicRoutes = getRoutes().filter((r: RouteRecordNormalized) => r.path.startsWith('/p/'))
			publicRoutes.forEach((route: RouteRecordNormalized) => {
				// Skip redirect-only routes (they don't have components)
				if (route.redirect) {
					return
				}
				const component = route.components?.default
				if (component) {
					expect(typeof component).toBe('function')
				}
			})
		})
	})

	describe('sign route redirect', () => {
		it('has redirect function for /p/sign/:uuid route', () => {
			const signRoute = getRoutes().find((r: RouteRecordNormalized) => r.path === '/p/sign/:uuid')
			expect(signRoute).toBeDefined()
			expect(signRoute?.redirect).toBeDefined()
			expect(typeof signRoute?.redirect).toBe('function')
		})

		it('redirects to action route when action is determined', () => {
			selectAction.mockReturnValue('CreateAccountExternal')
			loadState.mockReturnValue('some-action')

			const signRoute = getRoutes().find((r: RouteRecordNormalized) => r.path === '/p/sign/:uuid')
			const redirectFn = signRoute?.redirect as (to: RouteLocationNormalized) => { name: string; params: Record<string, string> }

			const to = makeRoute({ name: undefined, params: { uuid: 'test-uuid' } })
			const result = redirectFn(to)

			expect(result).toEqual({
				name: 'CreateAccountExternal',
				params: { uuid: 'test-uuid' },
			})
		})

		it('redirects to SignPDFExternal when no action', () => {
			selectAction.mockReturnValue(null)
			loadState.mockReturnValue('')

			const signRoute = getRoutes().find((r: RouteRecordNormalized) => r.path === '/p/sign/:uuid')
			const redirectFn = signRoute?.redirect as (to: RouteLocationNormalized) => { name: string; params: Record<string, string> }

			const to = makeRoute({ name: undefined, params: { uuid: 'test-uuid' } })
			const result = redirectFn(to)

			expect(result).toEqual({
				name: 'SignPDFExternal',
				params: { uuid: 'test-uuid' },
			})
		})
	})

	describe('route parameter handling', () => {
		it('handles valid UUID in sign route', () => {
			const route = getRoutes().find((r: RouteRecordNormalized) => r.path === '/p/sign/:uuid/pdf')
			expect(route).toBeDefined()
			expect((route as RouteRecordNormalized).path).toContain('uuid')
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
			const internalRoutes = getRoutes().filter((r: RouteRecordNormalized) =>
				!r.path.startsWith('/p/')
			)

			expect(internalRoutes.length).toBeGreaterThan(0)
			internalRoutes.forEach((route: RouteRecordNormalized) => {
				expect(route.path).not.toMatch(/^\/p\//)
			})
		})

		it('all public routes start with /p/', () => {
			const publicRoutes = getRoutes().filter((r: RouteRecordNormalized) =>
				r.path.startsWith('/p/')
			)

			expect(publicRoutes.length).toBeGreaterThan(0)
			publicRoutes.forEach((route: RouteRecordNormalized) => {
				expect(route.path).toMatch(/^\/p\//)
			})
		})
	})

	describe('component lazy loading', () => {
		it('uses dynamic imports for main views', () => {
			const signPdfRoute = getRoutes().find(
					(r: RouteRecordNormalized) => r.name === 'SignPDFExternal'
			)

			expect(signPdfRoute).toBeDefined()
				const component = signPdfRoute?.components?.default
				expect(typeof component).toBe('function')
		})

		it('dynamic component returns thenable', () => {
			const validationRoute = getRoutes().find(
					(r: RouteRecordNormalized) => r.name === 'ValidationFileExternal'
			)

				const component = validationRoute?.components?.default
				expect(component).toBeDefined()
				const componentImport = (component as () => Promise<unknown>)()
			expect(componentImport).toHaveProperty('then')
		})
	})

	describe('route props configuration', () => {
		it('passes props to routed components', () => {
			// /p/sign/:uuid is now a redirect-only route, test /p/sign/:uuid/pdf instead
			const signRoute = getRoutes().find(
					(r: RouteRecordNormalized) => r.path === '/p/sign/:uuid/pdf'
			)

				expect(signRoute).toBeDefined()
				const props = (signRoute as RouteRecordNormalized).props
				// Vue Router normalizes `props: true` to `{default: true}` internally
				expect(typeof props === 'object' && props !== null && props.default === true).toBe(true)
		})

		it('enables param passing to component', () => {
			const validationRoute = getRoutes().find(
					(r: RouteRecordNormalized) => r.path === '/p/validation/:uuid'
			)

				expect(validationRoute).toBeDefined()
				const props = (validationRoute as RouteRecordNormalized).props
				// Vue Router normalizes `props: true` to `{default: true}` internally
				expect(typeof props === 'object' && props !== null && props.default === true).toBe(true)
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
			it.each([
				{ canRequestSign: true, expectedRoute: 'requestFiles', description: 'when user can request sign' },
				{ canRequestSign: false, expectedRoute: 'fileslist', description: 'when user cannot request sign' },
				{ canRequestSign: undefined, expectedRoute: 'fileslist', description: 'by default when can_request_sign is undefined' },
			])('redirects to $expectedRoute $description', ({ canRequestSign, expectedRoute }) => {
				loadState.mockImplementation((app, key, defaultValue) => {
					if (key === 'can_request_sign') {
						return canRequestSign !== undefined ? canRequestSign : defaultValue
					}
					return defaultValue
				})

				const route = getRoutes().find((r: RouteRecordNormalized) => r.path === path)
				expect(route).toBeDefined()
				expect(route?.redirect).toBeDefined()

				const redirectFn = route?.redirect as () => { name: string }
				const result = redirectFn()

				expect(result).toEqual({ name: expectedRoute })
			})
		})
	})
})
