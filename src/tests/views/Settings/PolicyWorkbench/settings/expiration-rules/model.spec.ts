/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_EXPIRY_IN_DAYS,
	DEFAULT_MAXIMUM_VALIDITY,
	DEFAULT_RENEWAL_INTERVAL,
	durationToSeconds,
	hasValidRequestExpirationCombination,
	isRequestExpirationDraftValue,
	normalizeNonNegativeInt,
	normalizePositiveInt,
	normalizeRequestExpirationDraftValue,
	secondsToDuration,
	summarizeRequestExpirationDraftValue,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/model'

const tFn = (_app: string, text: string, vars?: Record<string, string>) => {
	if (!vars) {
		return text
	}

	return text.replace(/\{(\w+)\}/g, (_match, key) => vars[key] ?? `{${key}}`)
}

describe('expiration-rules model', () => {
	it('exposes the canonical defaults', () => {
		expect(DEFAULT_MAXIMUM_VALIDITY).toBe(0)
		expect(DEFAULT_RENEWAL_INTERVAL).toBe(0)
		expect(DEFAULT_EXPIRY_IN_DAYS).toBe(365)
	})

	describe('secondsToDuration - largest exact unit selection', () => {
		it('selects days for exact multiples of 86400 seconds', () => {
			expect(secondsToDuration(86400)).toEqual({ amount: 1, unit: 'days' })
			expect(secondsToDuration(604800)).toEqual({ amount: 7, unit: 'days' })
		})

		it('selects hours for exact multiples of 3600 seconds not divisible by days', () => {
			expect(secondsToDuration(3600)).toEqual({ amount: 1, unit: 'hours' })
			expect(secondsToDuration(86400 + 3600)).toEqual({ amount: 25, unit: 'hours' })
		})

		it('selects minutes for exact multiples of 60 seconds not divisible by hours', () => {
			expect(secondsToDuration(300)).toEqual({ amount: 5, unit: 'minutes' })
			expect(secondsToDuration(3600 + 120)).toEqual({ amount: 62, unit: 'minutes' })
		})

		it('selects seconds for non-divisible values', () => {
			expect(secondsToDuration(61)).toEqual({ amount: 61, unit: 'seconds' })
			expect(secondsToDuration(3661)).toEqual({ amount: 3661, unit: 'seconds' })
		})

		it('handles disabled, zero, negative, or invalid values', () => {
			expect(secondsToDuration(0)).toEqual({ amount: 0, unit: 'seconds' })
			expect(secondsToDuration(-500)).toEqual({ amount: 0, unit: 'seconds' })
			expect(secondsToDuration('invalid' as never)).toEqual({ amount: 0, unit: 'seconds' })
		})
	})

	describe('durationToSeconds - unit conversion and validation', () => {
		it('converts every unit correctly when given positive safe integers', () => {
			expect(durationToSeconds(61, 'seconds')).toBe(61)
			expect(durationToSeconds('5', 'minutes')).toBe(300)
			expect(durationToSeconds(1, 'hours')).toBe(3600)
			expect(durationToSeconds(7, 'days')).toBe(604800)
		})

		it('rejects zero and negative inputs', () => {
			expect(durationToSeconds(0, 'days')).toBeNull()
			expect(durationToSeconds(-5, 'hours')).toBeNull()
			expect(durationToSeconds('-10', 'seconds')).toBeNull()
		})

		it('rejects float, non-integer, and non-numeric inputs without silent rounding', () => {
			expect(durationToSeconds(1.5, 'days')).toBeNull()
			expect(durationToSeconds('1.5', 'minutes')).toBeNull()
			expect(durationToSeconds('abc', 'hours')).toBeNull()
			expect(durationToSeconds(null, 'seconds')).toBeNull()
			expect(durationToSeconds(undefined, 'days')).toBeNull()
		})

		it('rejects unsafe integer overflow', () => {
			expect(durationToSeconds(Number.MAX_SAFE_INTEGER, 'days')).toBeNull()
			expect(durationToSeconds(9007199254740991, 'hours')).toBeNull()
		})
	})

	describe('exact round trips', () => {
		it('preserves exact seconds through secondsToDuration and durationToSeconds', () => {
			const testValues = [61, 300, 3600, 86400, 604800, 3661]
			for (const seconds of testValues) {
				const duration = secondsToDuration(seconds)
				const roundTrip = durationToSeconds(duration.amount, duration.unit)
				expect(roundTrip).toBe(seconds)
			}
		})
	})

	it('normalizes non-negative integers from numbers and strings while clamping negatives', () => {
		expect(normalizeNonNegativeInt(42)).toBe(42)
		expect(normalizeNonNegativeInt(' 8 ')).toBe(8)
		expect(normalizeNonNegativeInt(-5)).toBe(0)
		expect(normalizeNonNegativeInt('invalid', 7)).toBe(7)
	})

	it('normalizes positive integers with fallback when values are disabled or invalid', () => {
		expect(normalizePositiveInt(12, DEFAULT_EXPIRY_IN_DAYS)).toBe(12)
		expect(normalizePositiveInt(' 30 ', DEFAULT_EXPIRY_IN_DAYS)).toBe(30)
		expect(normalizePositiveInt(0, DEFAULT_EXPIRY_IN_DAYS)).toBe(DEFAULT_EXPIRY_IN_DAYS)
		expect(normalizePositiveInt('invalid', DEFAULT_EXPIRY_IN_DAYS)).toBe(DEFAULT_EXPIRY_IN_DAYS)
	})

	it('recognizes and normalizes request expiration draft objects and legacy scalar values', () => {
		expect(isRequestExpirationDraftValue({ maximumValidity: 60, renewalInterval: 15 })).toBe(true)
		expect(isRequestExpirationDraftValue({ maximumValidity: 60 })).toBe(false)
		expect(isRequestExpirationDraftValue(null)).toBe(false)

		expect(normalizeRequestExpirationDraftValue({ maximumValidity: '120', renewalInterval: '30' } as never)).toEqual({
			maximumValidity: 120,
			renewalInterval: 30,
		})
		expect(normalizeRequestExpirationDraftValue('90')).toEqual({
			maximumValidity: 90,
			renewalInterval: 0,
		})
	})

	it('validates renewal/expiration combinations according to the canonical business rule', () => {
		expect(hasValidRequestExpirationCombination({ maximumValidity: 0, renewalInterval: 0 })).toBe(true)
		expect(hasValidRequestExpirationCombination({ maximumValidity: 60, renewalInterval: 30 })).toBe(true)
		expect(hasValidRequestExpirationCombination({ maximumValidity: 0, renewalInterval: 30 })).toBe(false)
	})

	it('summarizes normalized expiration and renewal values with disabled labels', () => {
		expect(summarizeRequestExpirationDraftValue({ maximumValidity: 120, renewalInterval: 30 }, tFn)).toBe('Expiration: 120 seconds | Renewal: 30 seconds')
		expect(summarizeRequestExpirationDraftValue({ maximumValidity: 0, renewalInterval: 0 }, tFn)).toBe('Expiration: Disabled | Renewal: Disabled')
	})
})
