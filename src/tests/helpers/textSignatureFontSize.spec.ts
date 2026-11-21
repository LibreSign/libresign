/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { computeTextSignatureFontSize, resolveTextSignatureLayout } from '../../helpers/textSignatureFontSize'

describe('computeTextSignatureFontSize', () => {
	const base = { canvasWidth: 700, canvasHeight: 200, referenceFontSize: 100 }

	it('caps a short text at 80% of the canvas height', () => {
		expect(computeTextSignatureFontSize({ ...base, measuredWidth: 150 })).toBe(160)
	})

	it('shrinks a long text to fit the width minus the margins', () => {
		// 688px available for 2000px at 100px => 34.4px
		expect(computeTextSignatureFontSize({ ...base, measuredWidth: 2000 })).toBeCloseTo(34.4)
	})

	it('scales linearly with the measurement reference', () => {
		const size = computeTextSignatureFontSize({ ...base, referenceFontSize: 50, measuredWidth: 1000 })
		expect(size).toBeCloseTo(34.4)
	})

	it('honours custom margin and ratio', () => {
		expect(computeTextSignatureFontSize({ ...base, measuredWidth: 1400, margin: 0 })).toBeCloseTo(50)
		expect(computeTextSignatureFontSize({ ...base, measuredWidth: 100, maxHeightRatio: 0.5 })).toBe(100)
	})

	it('falls back to the height cap when nothing can be measured', () => {
		expect(computeTextSignatureFontSize({ ...base, measuredWidth: 0 })).toBe(100)
		expect(computeTextSignatureFontSize({ ...base, canvasWidth: 10, measuredWidth: 500 })).toBe(100)
	})
})

describe('resolveTextSignatureLayout', () => {
	const base = { canvasWidth: 350, canvasHeight: 100, referenceFontSize: 100 }

	it('uses one line at the fitted size for a short name', () => {
		expect(resolveTextSignatureLayout({ ...base, measuredWidth: 150 })).toEqual({ fontSize: 80, wrap: false })
	})

	it('keeps the 30px wrapping for a long name on the default 350x100 canvas', () => {
		expect(resolveTextSignatureLayout({ ...base, measuredWidth: 1930 })).toEqual({ fontSize: 30, wrap: true })
	})
})
