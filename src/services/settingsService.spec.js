/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

const patchMock = vi.fn()
const generateUrlMock = vi.fn(() => '/apps/libresign/api/v1/account/settings')

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
	return await import('./settingsService.js')
}

describe('settingsService', () => {
	it('saves user phone number via API', async () => {
		patchMock.mockResolvedValue({ data: { success: true } })
		const { settingsService } = await loadModule()

		const result = await settingsService.saveUserNumber('+551199999999')

		expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/settings')
		expect(patchMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/account/settings',
			{ phone: '+551199999999' },
		)
		expect(result).toEqual({ success: true })
	})
})
