/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { isExternal } from '../../helpers/isExternal'
import type { RouteLocationLike } from '../../helpers/isExternal'

const route = (path: string): RouteLocationLike => ({ path })

describe('isExternal helper', () => {
	it('returns true when from path is root and to starts with /p/ (public route)', () => {
		const result = isExternal(route('/p/sign/abc123/pdf'), route('/'))
		expect(result).toBe(true)
	})

	it('returns false when from path is root and to does not start with /p/', () => {
		const result = isExternal(route('/sign/request'), route('/'))
		expect(result).toBe(false)
	})

	it('returns true when navigating from internal route to any route (from /p/)', () => {
		const result = isExternal(route('/certificates'), route('/p/sign/abc123/pdf'))
		expect(result).toBe(true)
	})

	it('returns false when navigating between internal routes only', () => {
		const result = isExternal(route('/certificates'), route('/sign/request'))
		expect(result).toBe(false)
	})

	it('detects external route to validation endpoint', () => {
		const result = isExternal(route('/p/validation/abc123'), route('/'))
		expect(result).toBe(true)
	})
})
