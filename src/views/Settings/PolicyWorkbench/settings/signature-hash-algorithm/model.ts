/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const HASH_ALGORITHMS = ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'] as const
export type HashAlgorithm = typeof HASH_ALGORITHMS[number]

export const DEFAULT_HASH_ALGORITHM: HashAlgorithm = 'SHA256'

export function isHashAlgorithm(value: unknown): value is HashAlgorithm {
	return HASH_ALGORITHMS.includes(value as HashAlgorithm)
}

export function normalizeHashAlgorithm(value: EffectivePolicyValue): HashAlgorithm {
	if (typeof value === 'string') {
		const normalized = value.trim().toUpperCase()
		if (isHashAlgorithm(normalized)) {
			return normalized
		}
	}

	return DEFAULT_HASH_ALGORITHM
}
