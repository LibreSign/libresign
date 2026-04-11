/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { estimateContainerHeightForFirstRender } from '../../helpers/containerHeight'

describe('estimateContainerHeightForFirstRender', () => {
	it('returns the floor value when height is zero', () => {
		expect(estimateContainerHeightForFirstRender(0, 100)).toBe(160)
	})

	it('returns the floor value when height is negative', () => {
		expect(estimateContainerHeightForFirstRender(-50, 100)).toBe(160)
	})

	it('returns the floor value when height is NaN', () => {
		expect(estimateContainerHeightForFirstRender(NaN, 100)).toBe(160)
	})

	it('returns the floor value when zoom is zero', () => {
		expect(estimateContainerHeightForFirstRender(200, 0)).toBe(160)
	})

	it('returns the floor value when zoom is negative', () => {
		expect(estimateContainerHeightForFirstRender(200, -10)).toBe(160)
	})

	it('returns the floor value when zoom is NaN', () => {
		expect(estimateContainerHeightForFirstRender(200, NaN)).toBe(160)
	})

	it('returns the floor value when the formula result is below the minimum', () => {
		// 100 * 100 / 100 + 24 = 124, which is below the 160 floor
		expect(estimateContainerHeightForFirstRender(100, 100)).toBe(160)
	})

	it('returns the formula result when it exceeds the floor', () => {
		// 250 * 100 / 100 + 24 = 274
		expect(estimateContainerHeightForFirstRender(250, 100)).toBe(274)
	})

	it('scales with zoom level', () => {
		// 200 * 150 / 100 + 24 = 324
		expect(estimateContainerHeightForFirstRender(200, 150)).toBe(324)
	})

	it('rounds fractional results', () => {
		// 100 * 155 / 100 + 24 = 179, no fraction here; use 301 * 90 / 100 = 270.9 + 24 = 294.9 → rounds to 295
		expect(estimateContainerHeightForFirstRender(301, 90)).toBe(295)
	})
})
