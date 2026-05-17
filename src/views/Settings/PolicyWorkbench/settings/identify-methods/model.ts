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
	requirement?: IdentifyMethodRequirement
	minimumTotalVerifiedFactors?: number
	signatureMethods: Record<string, IdentifyMethodSignatureMethod>
	signatureMethodEnabled?: string
}

export type IdentifyMethodsPolicyGlobalSettings = {
	canCreateAccount?: boolean
}

export type IdentifyMethodsPolicyConfig = {
	factors: IdentifyMethodPolicyEntry[]
	global: IdentifyMethodsPolicyGlobalSettings
}

export function normalizeIdentifyMethodsPolicyConfig(value: EffectivePolicyValue): IdentifyMethodsPolicyConfig {
	let entries: unknown = value
	let sharedMinimumTotalVerifiedFactors: number | undefined
	let globalCanCreateAccount: boolean | undefined
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
			if (candidate.can_create_account !== undefined) {
				globalCanCreateAccount = Boolean(candidate.can_create_account)
			}
		}
	} else if (value && typeof value === 'object' && !Array.isArray(value)) {
		const candidate = value as Record<string, unknown>
		if (Array.isArray(candidate.factors)) {
			entries = candidate.factors
			sharedMinimumTotalVerifiedFactors = normalizeMinimumTotalVerifiedFactors(candidate.minimumTotalVerifiedFactors)
		}
		if (candidate.can_create_account !== undefined) {
			globalCanCreateAccount = Boolean(candidate.can_create_account)
		}
	}

	if (!Array.isArray(entries)) {
		return {
			factors: [],
			global: {
				canCreateAccount: globalCanCreateAccount,
			},
		}
	}

	const normalized: IdentifyMethodPolicyEntry[] = []
	let legacyGlobalCanCreateAccount = globalCanCreateAccount

	for (const rawEntry of entries) {
		if (typeof rawEntry === 'string') {
			const name = rawEntry.trim()
			if (!name) {
				continue
			}

			normalized.push({
				name,
				enabled: true,
				minimumTotalVerifiedFactors: sharedMinimumTotalVerifiedFactors,
				signatureMethods: {},
			})
			continue
		}

		if (!rawEntry || typeof rawEntry !== 'object') {
			continue
		}

		const candidate = rawEntry as Record<string, unknown>
		const name = typeof candidate.name === 'string' ? candidate.name.trim() : ''
		if (!name) {
			continue
		}

		if (legacyGlobalCanCreateAccount === undefined && candidate.can_create_account !== undefined) {
			legacyGlobalCanCreateAccount = Boolean(candidate.can_create_account)
		}

		const signatureMethods = normalizeSignatureMethods(candidate.signatureMethods, candidate.availableSignatureMethods)

		normalized.push({
			name,
			friendly_name: typeof candidate.friendly_name === 'string' ? candidate.friendly_name : undefined,
			enabled: candidate.enabled === undefined ? true : Boolean(candidate.enabled),
			requirement: normalizeRequirement(candidate.requirement),
			minimumTotalVerifiedFactors: normalizeMinimumTotalVerifiedFactors(candidate.minimumTotalVerifiedFactors)
				?? sharedMinimumTotalVerifiedFactors,
			signatureMethods,
			signatureMethodEnabled: typeof candidate.signatureMethodEnabled === 'string'
				? candidate.signatureMethodEnabled
				: undefined,
		})
	}

	return {
		factors: normalized,
		global: {
			canCreateAccount: legacyGlobalCanCreateAccount,
		},
	}
}

export function normalizeIdentifyMethodsPolicy(value: EffectivePolicyValue): IdentifyMethodPolicyEntry[] {
	return normalizeIdentifyMethodsPolicyConfig(value).factors
}

export function serializeIdentifyMethodsPolicy(
	entries: IdentifyMethodPolicyEntry[],
	globalSettings: IdentifyMethodsPolicyGlobalSettings = {},
): string {
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

		if (entry.requirement) {
			normalizedEntry.requirement = entry.requirement
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

	const payload: Record<string, unknown> = {
		factors: normalizedEntries,
	}

	if (globalSettings.canCreateAccount !== undefined) {
		payload.can_create_account = Boolean(globalSettings.canCreateAccount)
	}

	return JSON.stringify(payload)
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

function normalizeRequirement(requirement: unknown): IdentifyMethodRequirement | undefined {
	if (requirement === 'required' || requirement === 'optional') {
		return requirement
	}

	return undefined
}

function normalizeMinimumTotalVerifiedFactors(value: unknown): number | undefined {
	if (typeof value === 'string') {
		const trimmedValue = value.trim()
		if (trimmedValue.length === 0) {
			return undefined
		}

		const parsedValue = Number(trimmedValue)
		if (!Number.isFinite(parsedValue)) {
			return undefined
		}

		value = parsedValue
	}

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
