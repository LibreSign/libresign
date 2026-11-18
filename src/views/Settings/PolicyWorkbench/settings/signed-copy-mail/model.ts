/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const SIGNED_COPY_MAIL_POLICY_KEY = 'signed_copy_mail'

/** Same limit as the backend normalizer. */
export const MAX_ARCHIVE_ADDRESSES = 10

export type SignedCopyMailPolicyConfig = {
	enabled: boolean
	archive_addresses: string[]
}

export const SIGNED_COPY_MAIL_POLICY_DEFAULTS: SignedCopyMailPolicyConfig = {
	enabled: false,
	archive_addresses: [],
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/**
 * Normalizes a raw effective policy value to the canonical payload.
 *
 * @param value Raw effective policy value from API/runtime.
 */
export function normalizeSignedCopyMailPolicyConfig(value: EffectivePolicyValue): SignedCopyMailPolicyConfig {
	const normalized = coerceObject(value)

	return {
		enabled: normalized.enabled === true || normalized.enabled === 'true' || normalized.enabled === 1,
		archive_addresses: parseArchiveAddresses(normalized.archive_addresses),
	}
}

/**
 * Serializes the policy config to the canonical JSON string payload.
 *
 * @param config Policy value to serialize.
 */
export function serializeSignedCopyMailPolicyConfig(config: SignedCopyMailPolicyConfig): string {
	return JSON.stringify(normalizeSignedCopyMailPolicyConfig(config as unknown as EffectivePolicyValue))
}

/**
 * Splits free text or a list into valid, unique addresses.
 *
 * @param value Address list or text separated by commas, semicolons or whitespace.
 */
export function parseArchiveAddresses(value: unknown): string[] {
	const candidates = typeof value === 'string'
		? value.split(/[\s,;]+/)
		: Array.isArray(value) ? value : []
	const seen = new Set<string>()
	const addresses: string[] = []
	for (const candidate of candidates) {
		if (typeof candidate !== 'string') {
			continue
		}
		const address = candidate.trim()
		if (!EMAIL_PATTERN.test(address) || seen.has(address.toLowerCase())) {
			continue
		}
		seen.add(address.toLowerCase())
		addresses.push(address)
		if (addresses.length >= MAX_ARCHIVE_ADDRESSES) {
			break
		}
	}
	return addresses
}

/**
 * Coerces an effective policy value to an object payload.
 *
 * @param value Raw effective policy value.
 */
function coerceObject(value: EffectivePolicyValue): Record<string, unknown> {
	if (typeof value === 'string') {
		try {
			const parsed = JSON.parse(value.trim())
			if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
				return parsed as Record<string, unknown>
			}
		} catch {
			return {}
		}
		return {}
	}

	if (value && typeof value === 'object' && !Array.isArray(value)) {
		return value as Record<string, unknown>
	}

	return {}
}
