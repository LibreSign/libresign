/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, vi } from 'vitest'

let mockLogger

vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => {
		mockLogger = {
			debug: vi.fn(),
			info: vi.fn(),
			warn: vi.fn(),
			error: vi.fn(),
		}
		return {
			setApp: vi.fn().mockReturnThis(),
			detectUser: vi.fn().mockReturnThis(),
			build: vi.fn().mockReturnValue(mockLogger),
		}
	}),
}))

describe('logger', () => {
	it('exports a logger instance', async () => {
		vi.resetModules()
		const logger = (await import('../../helpers/logger.js')).default

		expect(logger).toBeDefined()
		expect(logger).toBe(mockLogger)
	})

	it('exports logger methods', async () => {
		vi.resetModules()
		const logger = (await import('../../helpers/logger.js')).default

		expect(logger.debug).toBeDefined()
		expect(logger.info).toBeDefined()
		expect(logger.warn).toBeDefined()
		expect(logger.error).toBeDefined()
	})
})
