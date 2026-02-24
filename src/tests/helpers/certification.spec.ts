/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import { selectCustonOption, options } from '../../helpers/certification'

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
	t: vi.fn((app: any, text: any) => text),
	translate: vi.fn((app: any, text: any) => text),
	translatePlural: vi.fn((app: any, singular: any, plural: any, count: any) => count === 1 ? singular : plural),
	isRTL: vi.fn(() => false),
}))

const optionFromMock = vi.fn((value) => ({ value }))

vi.mock('@marionebl/option', () => ({
	Option: {
		from: (value: unknown) => optionFromMock(value),
	},
}))

describe('selectCustonOption', () => {
	beforeEach(() => {
		optionFromMock.mockClear()
	})

	it('returns option wrapped when id exists', () => {
		const expectedOption = options.find((item) => item.id === 'CN')

		const result = selectCustonOption('CN')

		expect(optionFromMock).toHaveBeenCalledWith(expectedOption)
		expect(result).toEqual({ value: expectedOption })
	})

	it('returns empty option when id does not exist', () => {
		const result = selectCustonOption('UNKNOWN')

		expect(optionFromMock).toHaveBeenCalledWith(undefined)
		expect(result).toEqual({ value: undefined })
	})
})
