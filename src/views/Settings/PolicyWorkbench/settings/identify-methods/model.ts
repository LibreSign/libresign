/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type IdentifyMethodSignatureMethod = {
	enabled?: boolean
	label?: string
}

export type IdentifyMethodPolicyEntry = {
	name: string
	friendly_name?: string
	enabled: boolean
	can_create_account?: boolean
	mandatory?: boolean
	signatureMethods: Record<string, IdentifyMethodSignatureMethod>
	signatureMethodEnabled?: string
}

export function normalizeIdentifyMethodsPolicy(value: EffectivePolicyValue): IdentifyMethodPolicyEntry[] {
	let entries: unknown = value
	if (typeof value === 'string') {
		const decoded = safeJsonParse(value)
		if (Array.isArray(decoded)) {
			entries = decoded
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

		const signatureMethods = normalizeSignatureMethods(candidate.signatureMethods)

		normalized.push({
			name,
			friendly_name: typeof candidate.friendly_name === 'string' ? candidate.friendly_name : undefined,
			enabled: Boolean(candidate.enabled),
			can_create_account: candidate.can_create_account === undefined ? undefined : Boolean(candidate.can_create_account),
			mandatory: candidate.mandatory === undefined ? undefined : Boolean(candidate.mandatory),
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

		if (entry.mandatory !== undefined) {
			normalizedEntry.mandatory = Boolean(entry.mandatory)
		}

		if (entry.signatureMethodEnabled) {
			normalizedEntry.signatureMethodEnabled = entry.signatureMethodEnabled
		}

		return normalizedEntry
	})

	return JSON.stringify(normalizedEntries)
}

function normalizeSignatureMethods(value: unknown): Record<string, IdentifyMethodSignatureMethod> {
	if (!value || typeof value !== 'object' || Array.isArray(value)) {
		return {}
	}

	const signatureMethods: Record<string, IdentifyMethodSignatureMethod> = {}

	for (const [signatureMethodName, rawConfig] of Object.entries(value as Record<string, unknown>)) {
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

function safeJsonParse(value: string): unknown {
	try {
		return JSON.parse(value)
	} catch {
		return null
	}
}
