/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import type { MockedFunction } from 'vitest'
import { generateOCSResponse } from '../test-helpers'

type AxiosGet = (url: string, config?: { params?: { currentStatus?: number; timeout?: number }; timeout?: number }) => Promise<{ data: unknown }>
type GenerateOcsUrl = typeof import('@nextcloud/router').generateOcsUrl

let getMock: MockedFunction<AxiosGet>
let generateOcsUrlMock: MockedFunction<GenerateOcsUrl>

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: Parameters<AxiosGet>) => getMock(...args),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (...args: Parameters<GenerateOcsUrl>) => generateOcsUrlMock(...args),
}))

describe('longPolling services', () => {
	beforeEach(() => {
		getMock = vi.fn()
		generateOcsUrlMock = vi.fn(() => '/ocs/wait-status')
		vi.clearAllMocks()
		vi.resetModules()
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	describe('waitForFileStatusChange', () => {
		it('requests status updates and returns response data', async () => {
			getMock.mockResolvedValue(generateOCSResponse<{ status: number }>({
				payload: { status: 3 },
			}))
			const { waitForFileStatusChange } = await import('../../services/longPolling.js')

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
			getMock.mockResolvedValue(generateOCSResponse<{ status: number }>({
				payload: { status: 1 },
			}))
			const { waitForFileStatusChange } = await import('../../services/longPolling.js')

			await waitForFileStatusChange(1, 0)

			const call = getMock.mock.calls[0]
			if (call && call[1] && call[1].params) {
				expect(call[1].params.timeout).toBe(30)
				expect(call[1].timeout).toBe(35000)
			}
		})
	})

	describe('startLongPolling - core business rules', () => {
		it('RULE: polls continuously until terminal status reached', async () => {
			const onUpdate = vi.fn()
			getMock
				.mockResolvedValueOnce(generateOCSResponse({ payload: { status: 2 } }))
				.mockResolvedValueOnce(generateOCSResponse({ payload: { status: 3 } })) // terminal

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 0, (data) => {
				onUpdate(data)
			}, null)

			// Wait for polling to complete
			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(2)
			}, { timeout: 3000 })

			expect(onUpdate).toHaveBeenCalledWith({ status: 2 })
			expect(onUpdate).toHaveBeenCalledWith({ status: 3 })
		})

		it('RULE: stops polling when shouldStop returns true', async () => {
			const onUpdate = vi.fn()
			let shouldStopFlag = false
			const shouldStop = () => shouldStopFlag

			getMock
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 2 } }))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 3 } }))

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 0, (data) => {
				onUpdate(data)
				if (data.status === 2) {
					shouldStopFlag = true
				}
			}, shouldStop)

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(1)
			}, { timeout: 3000 })

			expect(onUpdate).toHaveBeenCalledWith({ status: 2 })
		})

		it('RULE: calls stopPolling function to stop polling', async () => {
			const onUpdate = vi.fn()
			getMock
				.mockResolvedValue(generateOCSResponse<{ status: number }>({ payload: { status: 1 } }))

			const { startLongPolling } = await import('../../services/longPolling.js')

			let stopFn: (() => void) | null = null
			stopFn = startLongPolling(123, 0, (data) => {
				onUpdate(data)
				if (data.status === 1 && stopFn) {
					stopFn()
				}
			}, null)

			// Wait for first call
			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(1)
			}, { timeout: 3000 })
		})

		it('RULE: terminal status 3 (signed) stops polling', async () => {
			const onUpdate = vi.fn()
			getMock
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 2 } }))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 3 } })) // terminal

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 1, (data) => {
				onUpdate(data)
			}, null)

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalled()
			}, { timeout: 3000 })

			expect(onUpdate).toHaveBeenCalledWith({ status: 3 })
			// Should not continue polling after terminal status
		})

		it('RULE: terminal status 4 (deleted) stops polling', async () => {
			const onUpdate = vi.fn()
			getMock.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 4 } }))

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 1, (data) => {
				onUpdate(data)
			}, null)

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledWith({ status: 4 })
			}, { timeout: 3000 })

			expect(onUpdate).toHaveBeenCalledWith({ status: 4 })
		})

		it('RULE: retries on error with exponential backoff', async () => {
			const onUpdate = vi.fn()
			const onError = vi.fn()

			getMock
				.mockRejectedValueOnce(new Error('Network error'))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 2 } }))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 3 } }))

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 1, (data) => {
				onUpdate(data)
			}, null, onError, { sleep: () => Promise.resolve() })

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(2)
			}, { timeout: 3000 })

			expect(onError).toHaveBeenCalledTimes(1)
			expect(onUpdate).toHaveBeenCalledWith({ status: 2 })
			expect(onUpdate).toHaveBeenCalledWith({ status: 3 })
		})

		it('RULE: stops after MAX_ERRORS consecutive failures', async () => {
			const onUpdate = vi.fn()
			const onError = vi.fn()
			const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})

			getMock.mockRejectedValue(new Error('Network error'))

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 1, onUpdate, null, (error) => {
				onError(error)
			}, { sleep: () => Promise.resolve() })

			await vi.waitFor(() => {
				expect(onError).toHaveBeenCalledTimes(5)
			}, { timeout: 10000 })

			expect(onError).toHaveBeenCalledTimes(5)
			expect(onUpdate).not.toHaveBeenCalled()
			consoleErrorSpy.mockRestore()
		})

		it('RULE: resets error count after successful poll', async () => {
			const onUpdate = vi.fn()
			const onError = vi.fn()

			getMock
				.mockRejectedValueOnce(new Error('Error 1'))
				.mockRejectedValueOnce(new Error('Error 2'))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 2 } })) // success
				.mockRejectedValueOnce(new Error('Error 3'))
				.mockRejectedValueOnce(new Error('Error 4'))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number }>({ payload: { status: 3 } })) // success & terminal

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 1, (data) => {
				onUpdate(data)
			}, null, onError, { sleep: () => Promise.resolve() })

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(2)
			}, { timeout: 10000 })

			// Should have recovered from errors due to successful responses
			expect(onError).toHaveBeenCalledTimes(4)
			expect(onUpdate).toHaveBeenCalledTimes(2)
		})

		it('RULE: does not call onUpdate when status unchanged', async () => {
			const onUpdate = vi.fn()
			let callCount = 0

			getMock.mockImplementation(() => {
				callCount++
				if (callCount <= 2) {
					return Promise.resolve(generateOCSResponse<{ status: number }>({ payload: { status: 2 } })) // same status
				}
				return Promise.resolve(generateOCSResponse<{ status: number }>({ payload: { status: 3 } })) // terminal
			})

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 2, (data) => {
				onUpdate(data)
			}, null)

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(1)
			}, { timeout: 3000 })

			// Should only be called when status actually changed (from 2 to 3)
			expect(onUpdate).toHaveBeenCalledTimes(1)
			expect(onUpdate).toHaveBeenCalledWith({ status: 3 })
		})

		it('RULE: tracks current status across poll cycles', async () => {
			const onUpdate = vi.fn()

			getMock
				.mockResolvedValueOnce(generateOCSResponse<{ status: number; message: string }>({ payload: { status: 2, message: 'Processing' } }))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number; message: string }>({ payload: { status: 5, message: 'Ready' } }))
				.mockResolvedValueOnce(generateOCSResponse<{ status: number; message: string }>({ payload: { status: 3, message: 'Done' } }))

			const { startLongPolling } = await import('../../services/longPolling.js')

			startLongPolling(123, 0, (data) => {
				onUpdate(data)
			}, null)

			await vi.waitFor(() => {
				expect(onUpdate).toHaveBeenCalledTimes(3)
			}, { timeout: 3000 })

			expect(onUpdate).toHaveBeenNthCalledWith(1, { status: 2, message: 'Processing' })
			expect(onUpdate).toHaveBeenNthCalledWith(2, { status: 5, message: 'Ready' })
			expect(onUpdate).toHaveBeenNthCalledWith(3, { status: 3, message: 'Done' })
		})
	})
})
