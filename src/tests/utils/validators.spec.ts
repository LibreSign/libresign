/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { validateEmail } from '../../utils/validators.js'

describe('validateEmail', () => {
	it('accepts valid email formats', () => {
		expect(validateEmail('user@example.com')).toBe(true)
		expect(validateEmail('first.last+tag@sub.domain.com')).toBe(true)
	})

	it('rejects invalid email formats', () => {
		expect(validateEmail('invalid')).toBe(false)
		expect(validateEmail('missing@tld')).toBe(false)
		expect(validateEmail('user@.com')).toBe(false)
		expect(validateEmail('user@domain.')).toBe(false)
	})
})
