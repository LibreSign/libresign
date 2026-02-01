/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, vi } from 'vitest'

const mockLogger = {
	debug: vi.fn(),
	info: vi.fn(),
	warn: vi.fn(),
	error: vi.fn(),
}

vi.mock('@nextcloud/logger', () => ({
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn().mockReturnValue(mockLogger),
	})),
}))

describe('logger', () => {
	it('exports a logger instance', async () => {
		const logger = (await import('./logger.js')).default

		expect(logger).toBeDefined()
		expect(logger).toBe(mockLogger)
	})

	it('exports logger methods', async () => {
		const logger = (await import('./logger.js')).default

		expect(logger.debug).toBeDefined()
		expect(logger.info).toBeDefined()
		expect(logger.warn).toBeDefined()
		expect(logger.error).toBeDefined()
	})
})
