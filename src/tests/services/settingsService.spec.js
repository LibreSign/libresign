/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import { generateOCSResponse } from '../test-helpers.js'

let patchMock
let generateUrlMock

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
		patch: (...args) => patchMock(...args),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (...args) => generateUrlMock(...args),
}))

describe('settingsService', () => {
	beforeEach(() => {
		patchMock = vi.fn()
		generateUrlMock = vi.fn(() => '/apps/libresign/api/v1/account/settings')
	})

	it('saves user phone number via API', async () => {
		const response = generateOCSResponse({ payload: { success: true } })
		patchMock.mockResolvedValue(response)
		const { settingsService } = await import('../../services/settingsService.js')

		const result = await settingsService.saveUserNumber('+551199999999')

		expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/settings')
		expect(patchMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/account/settings',
			{ phone: '+551199999999' },
		)
		expect(result).toEqual(response.data)
	})
})
