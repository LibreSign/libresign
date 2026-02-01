/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

const getMock = vi.fn()
const generateOcsUrlMock = vi.fn(() => '/ocs/wait-status')

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: getMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: generateOcsUrlMock,
}))

describe('waitForFileStatusChange', () => {
	it('requests status updates and returns response data', async () => {
		getMock.mockResolvedValue({ data: { ocs: { data: { status: 3 } } } })
		const { waitForFileStatusChange } = await import('./longPolling.js')

		const result = await waitForFileStatusChange(42, 1, 15)

		expect(generateOcsUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/file/{fileId}/wait-status', { fileId: 42 })
		expect(getMock).toHaveBeenCalledWith('/ocs/wait-status', {
			params: { currentStatus: 1, timeout: 15 },
			timeout: 20000,
		})
		expect(result).toEqual({ status: 3 })
	})

	it('uses default timeout of 30 seconds', async () => {
		getMock.mockClear()
		getMock.mockResolvedValue({ data: { ocs: { data: { status: 1 } } } })
		const { waitForFileStatusChange } = await import('./longPolling.js')

		await waitForFileStatusChange(1, '0')

		const call = getMock.mock.calls[0]
		expect(call[1].params.timeout).toBe(30)
		expect(call[1].timeout).toBe(35000)
	})
})
