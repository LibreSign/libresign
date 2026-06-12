/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_REQUEST_SIGN_GROUPS = ['admin']
export const DEFAULT_REQUEST_SIGN_DENY_GROUPS: string[] = []

export type RequestSignGroupsPolicyValue = {
	allowGroups: string[]
	denyGroups: string[]
}

export function resolveRequestSignGroupsPolicy(value: EffectivePolicyValue | string[] | RequestSignGroupsPolicyValue): RequestSignGroupsPolicyValue {
	if (Array.isArray(value)) {
		return {
			allowGroups: normalizeGroupIds(value),
			denyGroups: [...DEFAULT_REQUEST_SIGN_DENY_GROUPS],
		}
	}

	if (typeof value === 'object' && value !== null) {
		const candidate = value as Partial<RequestSignGroupsPolicyValue>
		return {
			allowGroups: normalizeGroupIds(Array.isArray(candidate.allowGroups) ? candidate.allowGroups : []),
			denyGroups: normalizeGroupIds(Array.isArray(candidate.denyGroups) ? candidate.denyGroups : []),
		}
	}

	if (typeof value !== 'string') {
		return {
			allowGroups: [],
			denyGroups: [],
		}
	}

	const trimmed = value.trim()
	if (!trimmed) {
		return {
			allowGroups: [],
			denyGroups: [],
		}
	}

	try {
		const parsed = JSON.parse(trimmed)
		if (Array.isArray(parsed)) {
			return {
				allowGroups: normalizeGroupIds(parsed),
				denyGroups: [],
			}
		}

		if (typeof parsed === 'object' && parsed !== null) {
			const candidate = parsed as Partial<RequestSignGroupsPolicyValue>
			return {
				allowGroups: normalizeGroupIds(Array.isArray(candidate.allowGroups) ? candidate.allowGroups : []),
				denyGroups: normalizeGroupIds(Array.isArray(candidate.denyGroups) ? candidate.denyGroups : []),
			}
		}
	} catch {
		// Keep CSV fallback for legacy or manually edited values.
	}

	return {
		allowGroups: normalizeGroupIds(trimmed.split(',')),
		denyGroups: [],
	}
}

export function resolveRequestSignGroups(value: EffectivePolicyValue | string[]): string[] {
	return resolveRequestSignGroupsPolicy(value).allowGroups
}

export function resolveDeniedRequestSignGroups(value: EffectivePolicyValue | string[] | RequestSignGroupsPolicyValue): string[] {
	return resolveRequestSignGroupsPolicy(value).denyGroups
}

export function serializeRequestSignGroups(value: EffectivePolicyValue | string[] | RequestSignGroupsPolicyValue): string {
	const resolved = resolveRequestSignGroupsPolicy(value)
	return JSON.stringify({
		allowGroups: resolved.allowGroups,
		denyGroups: resolved.denyGroups,
	})
}

function normalizeGroupIds(raw: unknown[]): string[] {
	const normalized = raw
		.filter((candidate): candidate is string => typeof candidate === 'string')
		.map((candidate) => candidate.trim())
		.filter((candidate) => candidate.length > 0)

	return [...new Set(normalized)].sort((left, right) => left.localeCompare(right))
}
