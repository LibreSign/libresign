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
	let entries: unknown = null
	let sharedMinimumTotalVerifiedFactors: number | undefined
	let globalCanCreateAccount: boolean | undefined
	if (typeof value === 'string') {
		const decoded = safeJsonParse(value)
		if (decoded && typeof decoded === 'object' && !Array.isArray(decoded)) {
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

	for (const rawEntry of entries) {
		if (!rawEntry || typeof rawEntry !== 'object') {
			continue
		}

		const candidate = rawEntry as Record<string, unknown>
		const name = typeof candidate.name === 'string' ? candidate.name.trim() : ''
		if (!name) {
			continue
		}

		const signatureMethods = normalizeSignatureMethods(candidate.signatureMethods)

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
			canCreateAccount: globalCanCreateAccount,
		},
	}
}

export function normalizeIdentifyMethodsPolicy(value: EffectivePolicyValue): IdentifyMethodPolicyEntry[] {
	return normalizeIdentifyMethodsPolicyConfig(value).factors
}

export function getEnabledIdentifyMethodNames(value: EffectivePolicyValue): string[] {
	return normalizeIdentifyMethodsPolicy(value)
		.filter((entry) => entry.enabled)
		.map((entry) => entry.name)
}

export function restrictIdentifyMethodsPolicyToNames(
	value: EffectivePolicyValue,
	allowedMethodNames: Iterable<string>,
): string {
	const normalizedConfig = normalizeIdentifyMethodsPolicyConfig(value)
	const allowedNames = new Set(
		Array.from(allowedMethodNames).filter((name): name is string => typeof name === 'string' && name.trim().length > 0),
	)

	return serializeIdentifyMethodsPolicy(
		normalizedConfig.factors.filter((entry) => allowedNames.has(entry.name)),
		normalizedConfig.global,
	)
}

export function mergeIdentifyMethodsEntriesWithCatalog(
	policyEntries: IdentifyMethodPolicyEntry[],
	catalogEntries: IdentifyMethodPolicyEntry[],
): IdentifyMethodPolicyEntry[] {
	if (catalogEntries.length === 0) {
		return policyEntries.map(cloneIdentifyMethodEntry)
	}

	const policyEntriesByName = new Map(policyEntries.map((entry) => [entry.name, entry]))
	const catalogEntryNames = new Set(catalogEntries.map((entry) => entry.name))

	const mergedEntries = catalogEntries.map((catalogEntry) => {
		const policyEntry = policyEntriesByName.get(catalogEntry.name)
		if (!policyEntry) {
			return buildCatalogEditorEntry(catalogEntry)
		}

		return mergeIdentifyMethodEntryWithCatalog(policyEntry, catalogEntry)
	})

	for (const policyEntry of policyEntries) {
		if (!catalogEntryNames.has(policyEntry.name)) {
			mergedEntries.push(cloneIdentifyMethodEntry(policyEntry))
		}
	}

	return mergedEntries
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

function normalizeSignatureMethods(value: unknown): Record<string, IdentifyMethodSignatureMethod> {
	if (!value || typeof value !== 'object' || Array.isArray(value)) {
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

	return signatureMethods
}

function buildCatalogEditorEntry(entry: IdentifyMethodPolicyEntry): IdentifyMethodPolicyEntry {
	const catalogEntry: IdentifyMethodPolicyEntry = {
		name: entry.name,
		enabled: false,
		signatureMethods: cloneSignatureMethods(entry.signatureMethods),
	}

	if (typeof entry.friendly_name === 'string') {
		catalogEntry.friendly_name = entry.friendly_name
	}

	if (entry.requirement) {
		catalogEntry.requirement = entry.requirement
	}

	if (entry.minimumTotalVerifiedFactors !== undefined) {
		catalogEntry.minimumTotalVerifiedFactors = entry.minimumTotalVerifiedFactors
	}

	if (entry.signatureMethodEnabled) {
		catalogEntry.signatureMethodEnabled = entry.signatureMethodEnabled
	}

	return catalogEntry
}

function mergeIdentifyMethodEntryWithCatalog(
	policyEntry: IdentifyMethodPolicyEntry,
	catalogEntry: IdentifyMethodPolicyEntry,
): IdentifyMethodPolicyEntry {
	const mergedEntry: IdentifyMethodPolicyEntry = {
		name: policyEntry.name,
		enabled: policyEntry.enabled,
		signatureMethods: mergeSignatureMethods(catalogEntry.signatureMethods, policyEntry.signatureMethods),
	}

	if (typeof policyEntry.friendly_name === 'string') {
		mergedEntry.friendly_name = policyEntry.friendly_name
	} else if (typeof catalogEntry.friendly_name === 'string') {
		mergedEntry.friendly_name = catalogEntry.friendly_name
	}

	if (policyEntry.requirement) {
		mergedEntry.requirement = policyEntry.requirement
	} else if (catalogEntry.requirement) {
		mergedEntry.requirement = catalogEntry.requirement
	}

	if (policyEntry.minimumTotalVerifiedFactors !== undefined) {
		mergedEntry.minimumTotalVerifiedFactors = policyEntry.minimumTotalVerifiedFactors
	} else if (catalogEntry.minimumTotalVerifiedFactors !== undefined) {
		mergedEntry.minimumTotalVerifiedFactors = catalogEntry.minimumTotalVerifiedFactors
	}

	if (policyEntry.signatureMethodEnabled) {
		mergedEntry.signatureMethodEnabled = policyEntry.signatureMethodEnabled
	} else if (catalogEntry.signatureMethodEnabled) {
		mergedEntry.signatureMethodEnabled = catalogEntry.signatureMethodEnabled
	}

	return mergedEntry
}

function mergeSignatureMethods(
	catalogSignatureMethods: Record<string, IdentifyMethodSignatureMethod>,
	policySignatureMethods: Record<string, IdentifyMethodSignatureMethod>,
): Record<string, IdentifyMethodSignatureMethod> {
	const mergedSignatureMethods: Record<string, IdentifyMethodSignatureMethod> = {}
	const signatureMethodNames = new Set([
		...Object.keys(catalogSignatureMethods),
		...Object.keys(policySignatureMethods),
	])

	for (const signatureMethodName of signatureMethodNames) {
		mergedSignatureMethods[signatureMethodName] = {
			...(catalogSignatureMethods[signatureMethodName]
				? { ...catalogSignatureMethods[signatureMethodName] }
				: {}),
			...(policySignatureMethods[signatureMethodName]
				? { ...policySignatureMethods[signatureMethodName] }
				: {}),
		}
	}

	return mergedSignatureMethods
}

function cloneIdentifyMethodEntry(entry: IdentifyMethodPolicyEntry): IdentifyMethodPolicyEntry {
	const clonedEntry: IdentifyMethodPolicyEntry = {
		name: entry.name,
		enabled: entry.enabled,
		signatureMethods: cloneSignatureMethods(entry.signatureMethods),
	}

	if (typeof entry.friendly_name === 'string') {
		clonedEntry.friendly_name = entry.friendly_name
	}

	if (entry.requirement) {
		clonedEntry.requirement = entry.requirement
	}

	if (entry.minimumTotalVerifiedFactors !== undefined) {
		clonedEntry.minimumTotalVerifiedFactors = entry.minimumTotalVerifiedFactors
	}

	if (entry.signatureMethodEnabled) {
		clonedEntry.signatureMethodEnabled = entry.signatureMethodEnabled
	}

	return clonedEntry
}

function cloneSignatureMethods(signatureMethods: Record<string, IdentifyMethodSignatureMethod>): Record<string, IdentifyMethodSignatureMethod> {
	return Object.fromEntries(
		Object.entries(signatureMethods).map(([signatureMethodName, signatureMethod]) => [
			signatureMethodName,
			{ ...signatureMethod },
		]),
	)
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

function safeJsonParse(value: string): unknown {
	try {
		return JSON.parse(value)
	} catch {
		return null
	}
}
