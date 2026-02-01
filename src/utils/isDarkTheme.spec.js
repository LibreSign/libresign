/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import { checkIfDarkTheme } from './isDarkTheme.js'

describe('checkIfDarkTheme', () => {
	beforeEach(() => {
		vi.restoreAllMocks()
	})

	it('returns true when background invert indicates dark theme', () => {
		vi.spyOn(window, 'getComputedStyle').mockReturnValue({
			getPropertyValue: () => 'invert(100%)',
		})

		expect(checkIfDarkTheme(document.body)).toBe(true)
	})

	it('returns false when background invert indicates light theme', () => {
		vi.spyOn(window, 'getComputedStyle').mockReturnValue({
			getPropertyValue: () => 'no',
		})

		expect(checkIfDarkTheme(document.body)).toBe(false)
	})

	it('returns false when theme variable is missing', () => {
		vi.spyOn(window, 'getComputedStyle').mockReturnValue({
			getPropertyValue: () => undefined,
		})

		expect(checkIfDarkTheme(document.body)).toBe(false)
	})
})
