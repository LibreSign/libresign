/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type SignerGeolocationMode = 'disabled' | 'optional' | 'required'

export type SignerGeolocationPolicyValue = {
	mode: SignerGeolocationMode
}

const MODES: SignerGeolocationMode[] = ['disabled', 'optional', 'required']

export function resolveSignerGeolocationMode(value: EffectivePolicyValue | unknown): SignerGeolocationMode | null {
	if (typeof value === 'string' && MODES.includes(value as SignerGeolocationMode)) {
		return value as SignerGeolocationMode
	}

	if (value && typeof value === 'object' && !Array.isArray(value) && 'mode' in value) {
		const mode = (value as { mode?: unknown }).mode
		if (typeof mode === 'string' && MODES.includes(mode as SignerGeolocationMode)) {
			return mode as SignerGeolocationMode
		}
	}

	return null
}

export function normalizeSignerGeolocationValue(value: EffectivePolicyValue | unknown): SignerGeolocationPolicyValue {
	return {
		mode: resolveSignerGeolocationMode(value) ?? 'disabled',
	}
}
