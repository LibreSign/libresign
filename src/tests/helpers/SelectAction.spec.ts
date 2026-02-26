/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { RouteLocationNormalized } from 'vue-router'
import { ACTION_CODES } from '../../helpers/ActionMapping'

type LoadStateValue = string | boolean
type LoadStateFn = (app: string, key: string, defaultValue: LoadStateValue) => LoadStateValue

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

const loadStateMock = vi.fn<LoadStateFn>()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: Parameters<LoadStateFn>) => loadStateMock(...args),
}))

vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		debug: vi.fn(),
		info: vi.fn(),
		warn: vi.fn(),
		error: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			debug: vi.fn(),
			info: vi.fn(),
			warn: vi.fn(),
			error: vi.fn(),
		})),
	})),
}))

describe('selectAction helper', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
	})

	it('redirects when action is REDIRECT', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => {
			if (key === 'redirect') {
				return 'https://example.test/redirect'
			}
			return defaultValue
		})
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.REDIRECT,
			makeRoute({ path: '/p/sign/abc123/pdf', name: 'SignPDFExternal' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBeNull()
	})

	it('returns current route name when action is DO_NOTHING', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.DO_NOTHING,
			makeRoute({ path: '/sign/request', name: 'RequestSign' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBe('RequestSign')
	})


	it('returns mapped route with External suffix for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGN,
			makeRoute({ path: '/p/sign/abc123/pdf', name: 'SignPDFExternal' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBe('SignPDFExternal')
	})

	it('returns mapped route for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGN,
			makeRoute({ path: '/sign/request', name: 'SignPDF' }),
			makeRoute({ path: '/dashboard', name: 'Dashboard' }),
		)

		expect(result).toBe('SignPDF')
	})

	it('maps CREATE_ACCOUNT to CreateAccount for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.CREATE_ACCOUNT,
			makeRoute({ path: '/p/sign/abc123/sign-in', name: 'CreateAccountExternal' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBe('CreateAccountExternal')
	})

	it('maps SIGN_ID_DOC to IdDocsApprove for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGN_ID_DOC,
			makeRoute({ path: '/sign/request', name: 'IdDocsApprove' }),
			makeRoute({ path: '/dashboard', name: 'Dashboard' }),
		)

		expect(result).toBe('IdDocsApprove')
	})

	it('maps SIGNED to ValidationFile for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGNED,
			makeRoute({ path: '/p/validation/abc123', name: 'ValidationFileExternal' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBe('ValidationFileExternal')
	})

	it('maps CREATE_SIGNATURE_PASSWORD to CreatePassword for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.CREATE_SIGNATURE_PASSWORD,
			makeRoute({ path: '/settings/signature', name: 'CreatePassword' }),
			makeRoute({ path: '/dashboard', name: 'Dashboard' }),
		)

		expect(result).toBe('CreatePassword')
	})

	it('maps RENEW_EMAIL to RenewEmail for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.RENEW_EMAIL,
			makeRoute({ path: '/p/sign/abc123/renew/email', name: 'RenewEmailExternal' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBe('RenewEmailExternal')
	})

	it('maps INCOMPLETE_SETUP to Incomplete for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.INCOMPLETE_SETUP,
			makeRoute({ path: '/settings/incomplete', name: 'Incomplete' }),
			makeRoute({ path: '/dashboard', name: 'Dashboard' }),
		)

		expect(result).toBe('Incomplete')
	})

	it('returns DefaultPageErrorExternal when route not found and error state is true (external)', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => {
			if (key === 'error') return true
			return defaultValue
		})
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			999,
			makeRoute({ path: '/p/error', name: 'ErrorExternal' }),
			makeRoute({ path: '/', name: 'Home' }),
		)

		expect(result).toBe('DefaultPageErrorExternal')
	})

	it('returns DefaultPageError when route not found and error state is true (internal)', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => {
			if (key === 'error') return true
			return defaultValue
		})
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			999,
			makeRoute({ path: '/error', name: 'Error' }),
			makeRoute({ path: '/dashboard', name: 'Dashboard' }),
		)

		expect(result).toBe('DefaultPageError')
	})

	it('returns null when route not found and error state is false', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => {
			if (key === 'error') return false
			return defaultValue
		})
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			999,
			makeRoute({ path: '/sign/error', name: 'Error' }),
			makeRoute({ path: '/dashboard' }),
		)

		expect(result).toBeNull()
	})

	it('returns null when route is redirect', async () => {
		const mockMapping = {
			'sign': { route: 'redirect' },
		}

		vi.doMock('./ActionMapping.js', () => ({
			default: mockMapping,
		}))

		const { selectAction } = await import('../../helpers/SelectAction.js?t=' + Date.now())

		const result = selectAction('sign', makeRoute({ path: '/sign' }), makeRoute({ path: '/' }))

		expect(result).toBeNull()
	})
})
