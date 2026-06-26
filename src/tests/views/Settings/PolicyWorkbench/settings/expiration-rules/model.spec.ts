/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_EXPIRY_IN_DAYS,
	DEFAULT_MAXIMUM_VALIDITY,
	DEFAULT_RENEWAL_INTERVAL,
	hasValidRequestExpirationCombination,
	isRequestExpirationDraftValue,
	normalizeNonNegativeInt,
	normalizePositiveInt,
	normalizeRequestExpirationDraftValue,
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
