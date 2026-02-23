/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ACTION_CODES } from '../../helpers/ActionMapping'

let loadStateMock

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args) => loadStateMock(...args),
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
		loadStateMock = vi.fn()
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
			{ path: '/p/sign/abc123/pdf', name: 'SignPDFExternal', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
		)

		expect(result).toBeNull()
	})

	it('returns current route name when action is DO_NOTHING', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.DO_NOTHING,
			{ path: '/sign/request', name: 'RequestSign', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
		)

		expect(result).toBe('RequestSign')
	})


	it('returns mapped route with External suffix for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGN,
			{ path: '/p/sign/abc123/pdf', name: 'SignPDFExternal', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
		)

		expect(result).toBe('SignPDFExternal')
	})

	it('returns mapped route for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGN,
			{ path: '/sign/request', name: 'SignPDF', params: {}, query: {} },
			{ path: '/dashboard', name: 'Dashboard', params: {}, query: {} },
		)

		expect(result).toBe('SignPDF')
	})

	it('maps CREATE_ACCOUNT to CreateAccount for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.CREATE_ACCOUNT,
			{ path: '/p/sign/abc123/sign-in', name: 'CreateAccountExternal', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
		)

		expect(result).toBe('CreateAccountExternal')
	})

	it('maps SIGN_ID_DOC to IdDocsApprove for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGN_ID_DOC,
			{ path: '/sign/request', name: 'IdDocsApprove', params: {}, query: {} },
			{ path: '/dashboard', name: 'Dashboard', params: {}, query: {} },
		)

		expect(result).toBe('IdDocsApprove')
	})

	it('maps SIGNED to ValidationFile for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.SIGNED,
			{ path: '/p/validation/abc123', name: 'ValidationFileExternal', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
		)

		expect(result).toBe('ValidationFileExternal')
	})

	it('maps CREATE_SIGNATURE_PASSWORD to CreatePassword for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.CREATE_SIGNATURE_PASSWORD,
			{ path: '/settings/signature', name: 'CreatePassword', params: {}, query: {} },
			{ path: '/dashboard', name: 'Dashboard', params: {}, query: {} },
		)

		expect(result).toBe('CreatePassword')
	})

	it('maps RENEW_EMAIL to RenewEmail for public routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.RENEW_EMAIL,
			{ path: '/p/sign/abc123/renew/email', name: 'RenewEmailExternal', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
		)

		expect(result).toBe('RenewEmailExternal')
	})

	it('maps INCOMPLETE_SETUP to Incomplete for internal routes', async () => {
		loadStateMock.mockImplementation((app, key, defaultValue) => defaultValue)
		const { selectAction } = await import('../../helpers/SelectAction.js')

		const result = selectAction(
			ACTION_CODES.INCOMPLETE_SETUP,
			{ path: '/settings/incomplete', name: 'Incomplete', params: {}, query: {} },
			{ path: '/dashboard', name: 'Dashboard', params: {}, query: {} },
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
			{ path: '/p/error', name: 'ErrorExternal', params: {}, query: {} },
			{ path: '/', name: 'Home', params: {}, query: {} },
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
			{ path: '/error', name: 'Error', params: {}, query: {} },
			{ path: '/dashboard', name: 'Dashboard', params: {}, query: {} },
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
			{ path: '/sign/error', name: 'Error' },
			{ path: '/dashboard' },
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

		const result = selectAction('sign', { path: '/sign' }, { path: '/' })

		expect(result).toBeNull()
	})
})
