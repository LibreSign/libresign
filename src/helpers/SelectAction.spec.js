/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { ACTION_CODES } from './ActionMapping.js'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: loadStateMock,
}))

const loadModule = async (loadStateImpl) => {
	vi.resetModules()
	loadStateMock.mockImplementation(loadStateImpl)
	return await import('./SelectAction.js')
}

describe('selectAction helper', () => {
	let originalLocation

	beforeEach(() => {
		loadStateMock.mockReset()
		originalLocation = window.location
	})

	afterEach(() => {
		Object.defineProperty(window, 'location', {
			value: originalLocation,
			writable: true,
		})
	})

	it('redirects when action is REDIRECT', async () => {
		const replaceSpy = vi.fn()
		Object.defineProperty(window, 'location', {
			value: { replace: replaceSpy },
			writable: true,
		})

		const { selectAction } = await loadModule((app, key, defaultValue) => {
			if (key === 'redirect') {
				return 'https://example.test/redirect'
			}
			return defaultValue
		})

		const result = selectAction(
			ACTION_CODES.REDIRECT,
			{ path: '/p/sign/abc123/pdf', name: 'SignPDFExternal' },
			{ path: '/' },
		)

		expect(replaceSpy).toHaveBeenCalledWith('https://example.test/redirect')
		expect(result).toBeUndefined()
	})

	it('returns current route name when action is DO_NOTHING', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.DO_NOTHING,
			{ path: '/sign/request', name: 'RequestSign' },
			{ path: '/' },
		)

		expect(result).toBe('RequestSign')
	})


	it('returns mapped route with External suffix for public routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.SIGN,
			{ path: '/p/sign/abc123/pdf', name: 'SignPDFExternal' },
			{ path: '/' },
		)

		expect(result).toBe('SignPDFExternal')
	})

	it('returns mapped route for internal routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.SIGN,
			{ path: '/sign/request', name: 'SignPDF' },
			{ path: '/dashboard' },
		)

		expect(result).toBe('SignPDF')
	})

	it('maps CREATE_ACCOUNT to CreateAccount for public routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.CREATE_ACCOUNT,
			{ path: '/p/sign/abc123/sign-in', name: 'CreateAccountExternal' },
			{ path: '/' },
		)

		expect(result).toBe('CreateAccountExternal')
	})

	it('maps SIGN_ID_DOC to IdDocsApprove for internal routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.SIGN_ID_DOC,
			{ path: '/sign/request', name: 'IdDocsApprove' },
			{ path: '/dashboard' },
		)

		expect(result).toBe('IdDocsApprove')
	})

	it('maps SIGNED to ValidationFile for public routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.SIGNED,
			{ path: '/p/validation/abc123', name: 'ValidationFileExternal' },
			{ path: '/' },
		)

		expect(result).toBe('ValidationFileExternal')
	})

	it('maps CREATE_SIGNATURE_PASSWORD to CreatePassword for internal routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.CREATE_SIGNATURE_PASSWORD,
			{ path: '/settings/signature', name: 'CreatePassword' },
			{ path: '/dashboard' },
		)

		expect(result).toBe('CreatePassword')
	})

	it('maps RENEW_EMAIL to RenewEmail for public routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.RENEW_EMAIL,
			{ path: '/p/sign/abc123/renew/email', name: 'RenewEmailExternal' },
			{ path: '/' },
		)

		expect(result).toBe('RenewEmailExternal')
	})

	it('maps INCOMPLETE_SETUP to Incomplete for internal routes', async () => {
		const { selectAction } = await loadModule((app, key, defaultValue) => defaultValue)

		const result = selectAction(
			ACTION_CODES.INCOMPLETE_SETUP,
			{ path: '/settings/incomplete', name: 'Incomplete' },
			{ path: '/dashboard' },
		)

		expect(result).toBe('Incomplete')
	})
})
