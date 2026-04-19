/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_REQUEST_SIGN_GROUPS = ['admin']

export function resolveRequestSignGroups(value: EffectivePolicyValue | string[]): string[] {
	if (Array.isArray(value)) {
		return normalizeGroupIds(value)
	}

	if (typeof value !== 'string') {
		return []
	}

	const trimmed = value.trim()
	if (!trimmed) {
		return []
	}

	try {
		const parsed = JSON.parse(trimmed)
		if (Array.isArray(parsed)) {
			return normalizeGroupIds(parsed)
		}
	} catch {
		// Keep CSV fallback for legacy or manually edited values.
	}

	return normalizeGroupIds(trimmed.split(','))
}

export function serializeRequestSignGroups(value: EffectivePolicyValue | string[]): string {
	return JSON.stringify(resolveRequestSignGroups(value))
}

function normalizeGroupIds(raw: unknown[]): string[] {
	const normalized = raw
		.filter((candidate): candidate is string => typeof candidate === 'string')
		.map((candidate) => candidate.trim())
		.filter((candidate) => candidate.length > 0)

	return [...new Set(normalized)].sort((left, right) => left.localeCompare(right))
}
