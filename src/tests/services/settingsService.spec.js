/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { generateOCSResponse } from '../test-helpers.js'

const patchMock = vi.fn()
const generateUrlMock = vi.fn(() => '/apps/libresign/api/v1/account/settings')

// Mock @nextcloud/logger to avoid import-time errors
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

vi.mock('@nextcloud/axios', () => ({
	default: {
		patch: patchMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: generateUrlMock,
}))

const loadModule = async () => {
	vi.resetModules()
	patchMock.mockClear()
	generateUrlMock.mockClear()
	return await import('../../services/settingsService.js')
}

describe('settingsService', () => {
	it('saves user phone number via API', async () => {
		const response = generateOCSResponse({ payload: { success: true } })
		patchMock.mockResolvedValue(response)
		const { settingsService } = await loadModule()

		const result = await settingsService.saveUserNumber('+551199999999')

		expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/settings')
		expect(patchMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/account/settings',
			{ phone: '+551199999999' },
		)
		expect(result).toEqual(response.data)
	})
})
