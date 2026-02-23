/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'

// Mock @nextcloud packages to avoid import-time errors
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

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((app, text) => text),
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => count === 1 ? singular : plural),
}))

let optionFromMock = vi.fn()

vi.mock('@marionebl/option', () => ({
	Option: {
		from: (...args) => optionFromMock(...args),
	},
}))

describe('selectCustonOption', () => {
	beforeEach(() => {
		optionFromMock.mockClear()
		optionFromMock.mockImplementation((value) => ({ value }))
	})

	it('returns option wrapped when id exists', async () => {
		vi.resetModules()
		const { selectCustonOption, options } = await import('../../helpers/certification.js')
		const expectedOption = options.find((item) => item.id === 'CN')

		const result = selectCustonOption('CN')

		expect(optionFromMock).toHaveBeenCalledWith(expectedOption)
		expect(result).toEqual({ value: expectedOption })
	})

	it('returns empty option when id does not exist', async () => {
		vi.resetModules()
		const { selectCustonOption } = await import('../../helpers/certification.js')

		const result = selectCustonOption('UNKNOWN')

		expect(optionFromMock).toHaveBeenCalledWith(undefined)
		expect(result).toEqual({ value: undefined })
	})
})
