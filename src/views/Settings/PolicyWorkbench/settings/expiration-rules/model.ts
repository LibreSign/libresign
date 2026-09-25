/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_MAXIMUM_VALIDITY = 0
export const DEFAULT_RENEWAL_INTERVAL = 0
export const DEFAULT_EXPIRY_IN_DAYS = 365

export type TimeUnit = 'seconds' | 'minutes' | 'hours' | 'days'

export interface DurationWithUnit {
	amount: number
	unit: TimeUnit
}

export const TIME_UNITS: TimeUnit[] = ['seconds', 'minutes', 'hours', 'days']

export const SECONDS_PER_MINUTE = 60
export const SECONDS_PER_HOUR = 3600
export const SECONDS_PER_DAY = 86400

export interface RequestExpirationDraftValue {
	maximumValidity: number
	renewalInterval: number
}

export function secondsToDuration(value: EffectivePolicyValue | null | undefined): DurationWithUnit {
	const parsed = parseIntValue(value ?? 0)
	if (parsed === null || parsed <= 0 || !Number.isSafeInteger(parsed)) {
		return { amount: 0, unit: 'seconds' }
	}

	if (parsed % SECONDS_PER_DAY === 0) {
		return { amount: parsed / SECONDS_PER_DAY, unit: 'days' }
	}
	if (parsed % SECONDS_PER_HOUR === 0) {
		return { amount: parsed / SECONDS_PER_HOUR, unit: 'hours' }
	}
	if (parsed % SECONDS_PER_MINUTE === 0) {
		return { amount: parsed / SECONDS_PER_MINUTE, unit: 'minutes' }
	}
	return { amount: parsed, unit: 'seconds' }
}

export function durationToSeconds(amount: unknown, unit: TimeUnit): number | null {
	if (amount === null || amount === undefined || amount === '') {
		return null
	}

	let numAmount: number
	if (typeof amount === 'number') {
		numAmount = amount
	} else if (typeof amount === 'string') {
		const trimmed = amount.trim()
		if (!/^\d+$/.test(trimmed)) {
			return null
		}
		numAmount = Number(trimmed)
	} else {
		return null
	}

	if (!Number.isFinite(numAmount) || !Number.isInteger(numAmount) || !Number.isSafeInteger(numAmount) || numAmount <= 0) {
		return null
	}

	let multiplier: number
	switch (unit) {
		case 'days':
			multiplier = SECONDS_PER_DAY
			break
		case 'hours':
			multiplier = SECONDS_PER_HOUR
			break
		case 'minutes':
			multiplier = SECONDS_PER_MINUTE
			break
		case 'seconds':
			multiplier = 1
			break
		default:
			return null
	}

	const totalSeconds = numAmount * multiplier
	if (!Number.isFinite(totalSeconds) || !Number.isInteger(totalSeconds) || !Number.isSafeInteger(totalSeconds) || totalSeconds <= 0) {
		return null
	}

	return totalSeconds
}

export function normalizeNonNegativeInt(value: EffectivePolicyValue, fallback = 0): number {
	const parsed = parseIntValue(value)
	if (parsed === null || !Number.isSafeInteger(parsed)) {
		return fallback
	}

	return Math.max(0, parsed)
}

export function normalizePositiveInt(value: EffectivePolicyValue, fallback: number): number {
	const parsed = parseIntValue(value)
	if (parsed === null || parsed <= 0 || !Number.isSafeInteger(parsed)) {
		return fallback
	}

	return parsed
}

export function normalizeRequestExpirationDraftValue(value: EffectivePolicyValue): RequestExpirationDraftValue {
	if (isRequestExpirationDraftValue(value)) {
		return {
			maximumValidity: normalizeNonNegativeInt(value.maximumValidity, DEFAULT_MAXIMUM_VALIDITY),
			renewalInterval: normalizeNonNegativeInt(value.renewalInterval, DEFAULT_RENEWAL_INTERVAL),
		}
	}

	return {
		maximumValidity: normalizeNonNegativeInt(value, DEFAULT_MAXIMUM_VALIDITY),
		renewalInterval: DEFAULT_RENEWAL_INTERVAL,
	}
}

export function isRequestExpirationDraftValue(value: unknown): value is RequestExpirationDraftValue {
	if (!value || typeof value !== 'object') {
		return false
	}

	const candidate = value as Record<string, unknown>
	return 'maximumValidity' in candidate && 'renewalInterval' in candidate
}

export function hasValidRequestExpirationCombination(value: EffectivePolicyValue): boolean {
	const normalized = normalizeRequestExpirationDraftValue(value)
	if (normalized.renewalInterval <= 0) {
		return true
	}

	return normalized.maximumValidity > 0
}

export function summarizeRequestExpirationDraftValue(value: EffectivePolicyValue, tFn: (app: string, text: string, vars?: Record<string, string>) => string): string {
	const normalized = normalizeRequestExpirationDraftValue(value)
	const expirationLabel = normalized.maximumValidity > 0
		? tFn('libresign', '{value} seconds', { value: String(normalized.maximumValidity) })
		: tFn('libresign', 'Disabled')
	const renewalLabel = normalized.renewalInterval > 0
		? tFn('libresign', '{value} seconds', { value: String(normalized.renewalInterval) })
		: tFn('libresign', 'Disabled')

	return tFn('libresign', 'Expiration: {expiration} | Renewal: {renewal}', {
		expiration: expirationLabel,
		renewal: renewalLabel,
	})
}

function parseIntValue(value: EffectivePolicyValue): number | null {
	if (typeof value === 'number' && Number.isFinite(value)) {
		return Math.trunc(value)
	}

	if (typeof value === 'string') {
		const trimmed = value.trim()
		if (trimmed === '' || !/^-?\d+(\.\d+)?$/.test(trimmed)) {
			return null
		}

		return Math.trunc(Number(trimmed))
	}

	return null
}
