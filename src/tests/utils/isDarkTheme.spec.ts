/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'

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

import { checkIfDarkTheme } from '../../utils/isDarkTheme.js'

describe('checkIfDarkTheme', () => {
	beforeEach(() => {
		vi.restoreAllMocks()
	})

	it('returns true when background invert indicates dark theme', () => {
		vi.spyOn(window, 'getComputedStyle').mockReturnValue({
			getPropertyValue: () => 'invert(100%)',
		} as Partial<CSSStyleDeclaration> as CSSStyleDeclaration)

		expect(checkIfDarkTheme(document.body)).toBe(true)
	})

	it('returns false when background invert indicates light theme', () => {
		vi.spyOn(window, 'getComputedStyle').mockReturnValue({
			getPropertyValue: () => 'no',
		} as Partial<CSSStyleDeclaration> as CSSStyleDeclaration)

		expect(checkIfDarkTheme(document.body)).toBe(false)
	})

	it('returns false when theme variable is missing', () => {
		vi.spyOn(window, 'getComputedStyle').mockReturnValue({
			getPropertyValue: () => '',
		} as Partial<CSSStyleDeclaration> as CSSStyleDeclaration)

		expect(checkIfDarkTheme(document.body)).toBe(false)
	})
})
