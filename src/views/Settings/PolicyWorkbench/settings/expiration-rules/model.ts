/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_MAXIMUM_VALIDITY = 0
export const DEFAULT_RENEWAL_INTERVAL = 0
export const DEFAULT_EXPIRY_IN_DAYS = 365

export function normalizeNonNegativeInt(value: EffectivePolicyValue, fallback = 0): number {
	const parsed = parseIntValue(value)
	if (parsed === null) {
		return fallback
	}

	return Math.max(0, parsed)
}

export function normalizePositiveInt(value: EffectivePolicyValue, fallback: number): number {
	const parsed = parseIntValue(value)
	if (parsed === null || parsed <= 0) {
		return fallback
	}

	return parsed
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
