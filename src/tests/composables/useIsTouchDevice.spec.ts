/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { useIsTouchDevice } from '../../composables/useIsTouchDevice.js'

describe('useIsTouchDevice composable', () => {
	it('returns a boolean ref-like computed value', () => {
		const { isTouchDevice } = useIsTouchDevice()

		expect(typeof isTouchDevice.value).toBe('boolean')
	})

	it('follows window touch capability detection logic', () => {
		const { isTouchDevice } = useIsTouchDevice()
		const expected = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0)

		expect(isTouchDevice.value).toBe(expected)
	})
})
