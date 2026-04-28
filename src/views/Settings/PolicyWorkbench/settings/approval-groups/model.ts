/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_APPROVAL_GROUPS = ['admin']

export function resolveApprovalGroups(value: EffectivePolicyValue | string[]): string[] {
	if (Array.isArray(value)) {
		const normalized = normalizeGroupIds(value)
		return normalized.length > 0 ? normalized : [...DEFAULT_APPROVAL_GROUPS]
	}

	if (typeof value !== 'string') {
		return [...DEFAULT_APPROVAL_GROUPS]
	}

	const trimmed = value.trim()
	if (!trimmed) {
		return [...DEFAULT_APPROVAL_GROUPS]
	}

	try {
		const parsed = JSON.parse(trimmed)
		if (Array.isArray(parsed)) {
			const normalized = normalizeGroupIds(parsed)
			return normalized.length > 0 ? normalized : [...DEFAULT_APPROVAL_GROUPS]
		}
	} catch {
		return [...DEFAULT_APPROVAL_GROUPS]
	}

	return [...DEFAULT_APPROVAL_GROUPS]
}

export function serializeApprovalGroups(value: EffectivePolicyValue | string[]): string {
	return JSON.stringify(resolveApprovalGroups(value))
}

function normalizeGroupIds(raw: unknown[]): string[] {
	const normalized = raw
		.filter((candidate): candidate is string => typeof candidate === 'string')
		.map((candidate) => candidate.trim())
		.filter((candidate) => candidate.length > 0)

	return [...new Set(normalized)].sort((left, right) => left.localeCompare(right))
}
