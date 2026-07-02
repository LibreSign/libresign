/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type TsaSettingsConfig = {
	url: string
	policy_oid: string
	auth_type: 'none' | 'basic'
	username: string
}

export const DEFAULT_TSA_SETTINGS: TsaSettingsConfig = {
	url: '',
	policy_oid: '',
	auth_type: 'none',
	username: '',
}

export function normalizeTsaSettings(value: EffectivePolicyValue | TsaSettingsConfig): TsaSettingsConfig {
	if (typeof value === 'string') {
		try {
			const decoded = JSON.parse(value)
			if (decoded && typeof decoded === 'object') {
				value = decoded as EffectivePolicyValue
			}
		} catch {
			return { ...DEFAULT_TSA_SETTINGS }
		}
	}

	if (!value || typeof value !== 'object' || Array.isArray(value)) {
		return { ...DEFAULT_TSA_SETTINGS }
	}

	const raw = value as Record<string, unknown>
	const authType = raw.auth_type === 'basic' ? 'basic' : 'none'

	return {
		url: typeof raw.url === 'string' ? raw.url.trim() : '',
		policy_oid: typeof raw.policy_oid === 'string' ? raw.policy_oid.trim() : '',
		auth_type: authType,
		username: authType === 'basic' && typeof raw.username === 'string' ? raw.username.trim() : '',
	}
}

export function serializeTsaSettings(value: EffectivePolicyValue | TsaSettingsConfig): string {
	const normalized = normalizeTsaSettings(value)
	return JSON.stringify(normalized)
}
