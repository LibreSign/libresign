/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type IdentifyMethodRequirement = 'required' | 'optional'

export type IdentifyMethodSignatureMethod = {
	enabled?: boolean
	label?: string
}

export type IdentifyMethodPolicyEntry = {
	name: string
	friendly_name?: string
	enabled: boolean
	can_create_account?: boolean
	requirement?: IdentifyMethodRequirement
	mandatory?: boolean
	minimumTotalVerifiedFactors?: number
	signatureMethods: Record<string, IdentifyMethodSignatureMethod>
	signatureMethodEnabled?: string
}

export function normalizeIdentifyMethodsPolicy(value: EffectivePolicyValue): IdentifyMethodPolicyEntry[] {
	let entries: unknown = value
	let sharedMinimumTotalVerifiedFactors: number | undefined
	if (typeof value === 'string') {
		const decoded = safeJsonParse(value)
		if (Array.isArray(decoded)) {
			entries = decoded
		} else if (decoded && typeof decoded === 'object') {
			const candidate = decoded as Record<string, unknown>
			if (Array.isArray(candidate.factors)) {
				entries = candidate.factors
				sharedMinimumTotalVerifiedFactors = normalizeMinimumTotalVerifiedFactors(candidate.minimumTotalVerifiedFactors)
			}
		}
	} else if (value && typeof value === 'object' && !Array.isArray(value)) {
		const candidate = value as Record<string, unknown>
		if (Array.isArray(candidate.factors)) {
			entries = candidate.factors
			sharedMinimumTotalVerifiedFactors = normalizeMinimumTotalVerifiedFactors(candidate.minimumTotalVerifiedFactors)
		}
	}

	if (!Array.isArray(entries)) {
		return []
	}

	const normalized: IdentifyMethodPolicyEntry[] = []

	for (const rawEntry of entries) {
		if (!rawEntry || typeof rawEntry !== 'object') {
			continue
		}

		const candidate = rawEntry as Record<string, unknown>
		const name = typeof candidate.name === 'string' ? candidate.name.trim() : ''
		if (!name) {
			continue
		}

		const signatureMethods = normalizeSignatureMethods(candidate.signatureMethods, candidate.availableSignatureMethods)

		normalized.push({
			name,
			friendly_name: typeof candidate.friendly_name === 'string' ? candidate.friendly_name : undefined,
			enabled: Boolean(candidate.enabled),
			can_create_account: candidate.can_create_account === undefined ? undefined : Boolean(candidate.can_create_account),
			requirement: normalizeRequirement(candidate.requirement, candidate.mandatory),
			mandatory: candidate.mandatory === undefined ? undefined : Boolean(candidate.mandatory),
			minimumTotalVerifiedFactors: normalizeMinimumTotalVerifiedFactors(candidate.minimumTotalVerifiedFactors)
				?? sharedMinimumTotalVerifiedFactors,
			signatureMethods,
			signatureMethodEnabled: typeof candidate.signatureMethodEnabled === 'string'
				? candidate.signatureMethodEnabled
				: undefined,
		})
	}

	return normalized
}

export function serializeIdentifyMethodsPolicy(entries: IdentifyMethodPolicyEntry[]): string {
	const normalizedEntries = entries.map((entry) => {
		const signatureMethods: Record<string, IdentifyMethodSignatureMethod> = {}

		for (const [signatureMethodName, signatureMethod] of Object.entries(entry.signatureMethods)) {
			signatureMethods[signatureMethodName] = {
				enabled: Boolean(signatureMethod.enabled),
			}
		}

		const normalizedEntry: IdentifyMethodPolicyEntry = {
			name: entry.name,
			enabled: Boolean(entry.enabled),
			signatureMethods,
		}

		if (typeof entry.friendly_name === 'string') {
			normalizedEntry.friendly_name = entry.friendly_name
		}

		if (entry.can_create_account !== undefined) {
			normalizedEntry.can_create_account = Boolean(entry.can_create_account)
		}

		if (entry.requirement) {
			normalizedEntry.requirement = entry.requirement
			normalizedEntry.mandatory = entry.requirement === 'required'
		} else if (entry.mandatory !== undefined) {
			normalizedEntry.mandatory = Boolean(entry.mandatory)
		}

		if (entry.minimumTotalVerifiedFactors !== undefined) {
			const minimumTotalVerifiedFactors = normalizeMinimumTotalVerifiedFactors(entry.minimumTotalVerifiedFactors)
			if (minimumTotalVerifiedFactors !== undefined) {
				normalizedEntry.minimumTotalVerifiedFactors = minimumTotalVerifiedFactors
			}
		}

		if (entry.signatureMethodEnabled) {
			normalizedEntry.signatureMethodEnabled = entry.signatureMethodEnabled
		}

		return normalizedEntry
	})

	return JSON.stringify(normalizedEntries)
}

function normalizeSignatureMethods(value: unknown, legacyAvailableSignatureMethods?: unknown): Record<string, IdentifyMethodSignatureMethod> {
	if (Array.isArray(value)) {
		return normalizeSignatureMethodsFromList(value)
	}

	if (!value || typeof value !== 'object') {
		if (Array.isArray(legacyAvailableSignatureMethods)) {
			return normalizeSignatureMethodsFromList(legacyAvailableSignatureMethods)
		}
		return {}
	}

	const signatureMethods: Record<string, IdentifyMethodSignatureMethod> = {}

	for (const [signatureMethodName, rawConfig] of Object.entries(value as Record<string, unknown>)) {
		if (typeof rawConfig === 'string') {
			signatureMethods[signatureMethodName] = {
				enabled: false,
				label: rawConfig,
			}
			continue
		}

		if (!rawConfig || typeof rawConfig !== 'object') {
			continue
		}

		const candidate = rawConfig as Record<string, unknown>
		signatureMethods[signatureMethodName] = {
			enabled: Boolean(candidate.enabled),
			label: typeof candidate.label === 'string' ? candidate.label : undefined,
		}
	}

	if (Object.keys(signatureMethods).length === 0 && Array.isArray(legacyAvailableSignatureMethods)) {
		return normalizeSignatureMethodsFromList(legacyAvailableSignatureMethods)
	}

	return signatureMethods
}

function normalizeRequirement(requirement: unknown, mandatory: unknown): IdentifyMethodRequirement | undefined {
	if (requirement === 'required' || requirement === 'optional') {
		return requirement
	}

	if (mandatory === undefined) {
		return undefined
	}

	return Boolean(mandatory) ? 'required' : 'optional'
}

function normalizeMinimumTotalVerifiedFactors(value: unknown): number | undefined {
	if (typeof value !== 'number' || !Number.isFinite(value)) {
		return undefined
	}

	const normalized = Math.floor(value)
	if (normalized < 1) {
		return undefined
	}

	return normalized
}

function normalizeSignatureMethodsFromList(value: unknown[]): Record<string, IdentifyMethodSignatureMethod> {
	const signatureMethods: Record<string, IdentifyMethodSignatureMethod> = {}

	for (const signatureMethodName of value) {
		if (typeof signatureMethodName !== 'string' || signatureMethodName.trim().length === 0) {
			continue
		}

		signatureMethods[signatureMethodName] = {
			enabled: false,
		}
	}

	return signatureMethods
}

function safeJsonParse(value: string): unknown {
	try {
		return JSON.parse(value)
	} catch {
		return null
	}
}
