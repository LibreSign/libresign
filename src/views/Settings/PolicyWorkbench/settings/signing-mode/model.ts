/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type SigningModeValue = 'sync' | 'async'
export type WorkerTypeValue = 'local' | 'external'

export function resolveSigningMode(value: EffectivePolicyValue): SigningModeValue {
	if (value === 'async') {
		return 'async'
	}

	return 'sync'
}

export function resolveWorkerType(value: EffectivePolicyValue): WorkerTypeValue {
	if (value === 'external') {
		return 'external'
	}

	return 'local'
}

export function resolveParallelWorkers(value: EffectivePolicyValue): number {
	if (typeof value === 'number' && Number.isInteger(value) && value >= 1 && value <= 32) {
		return value
	}

	if (typeof value === 'string') {
		const parsed = Number.parseInt(value, 10)
		if (Number.isInteger(parsed) && parsed >= 1 && parsed <= 32) {
			return parsed
		}
	}

	return 4
}
