/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const ensureWorkerReadyMock = vi.fn()

vi.mock('@libresign/pdf-elements', () => ({
	ensureWorkerReady: vi.fn(() => ensureWorkerReadyMock()),
}))

describe('pdfWorker helper', () => {
	beforeEach(() => {
		vi.resetModules()
		ensureWorkerReadyMock.mockReset()
	})

	it('patches URL.parse to support Location input before worker bootstrap', async () => {
		const originalParse = URL.parse
		const brokenParse = vi.fn((input: unknown, base?: string | URL) => {
			if (typeof Location !== 'undefined' && input instanceof Location) {
				return null
			}
			if (typeof originalParse === 'function') {
				return originalParse(input as string, base)
			}
			return null
		})

		;(URL as unknown as { parse?: typeof URL.parse }).parse = brokenParse as unknown as typeof URL.parse

		const { ensurePdfWorker } = await import('../../helpers/pdfWorker')
		ensurePdfWorker()

		expect(ensureWorkerReadyMock).toHaveBeenCalledTimes(1)
		if (typeof Location !== 'undefined') {
			expect(URL.parse(window.location as unknown as string)).toBeInstanceOf(URL)
		}
	})

	it('initializes the worker only once', async () => {
		const { ensurePdfWorker } = await import('../../helpers/pdfWorker')
		ensurePdfWorker()
		ensurePdfWorker()

		expect(ensureWorkerReadyMock).toHaveBeenCalledTimes(1)
	})
})
