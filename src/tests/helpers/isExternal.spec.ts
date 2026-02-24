/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { isExternal } from '../../helpers/isExternal'

describe('isExternal helper', () => {
	it('returns true when from path is root and to starts with /p/ (public route)', () => {
		const result = isExternal(
			{ path: '/p/sign/abc123/pdf' } as any,
			{ path: '/' } as any
		)
		expect(result).toBe(true)
	})

	it('returns false when from path is root and to does not start with /p/', () => {
		const result = isExternal(
			{ path: '/sign/request' } as any,
			{ path: '/' } as any
		)
		expect(result).toBe(false)
	})

	it('returns true when navigating from internal route to any route (from /p/)', () => {
		const result = isExternal(
			{ path: '/certificates' } as any,
			{ path: '/p/sign/abc123/pdf' } as any
		)
		expect(result).toBe(true)
	})

	it('returns false when navigating between internal routes only', () => {
		const result = isExternal(
			{ path: '/certificates' } as any,
			{ path: '/sign/request' } as any
		)
		expect(result).toBe(false)
	})

	it('detects external route to validation endpoint', () => {
		const result = isExternal(
			{ path: '/p/validation/abc123' } as any,
			{ path: '/' } as any
		)
		expect(result).toBe(true)
	})
})
