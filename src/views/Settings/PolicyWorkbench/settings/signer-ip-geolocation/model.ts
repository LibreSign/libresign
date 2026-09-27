/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type SignerIpGeolocationMode = 'disabled' | 'enabled'

export type SignerIpGeolocationPolicyValue = {
	mode: SignerIpGeolocationMode
}

const MODES: SignerIpGeolocationMode[] = ['disabled', 'enabled']

export function resolveSignerIpGeolocationMode(value: EffectivePolicyValue | unknown): SignerIpGeolocationMode | null {
	if (typeof value === 'string' && MODES.includes(value as SignerIpGeolocationMode)) {
		return value as SignerIpGeolocationMode
	}

	if (value && typeof value === 'object' && !Array.isArray(value) && 'mode' in value) {
		const mode = (value as { mode?: unknown }).mode
		if (typeof mode === 'string' && MODES.includes(mode as SignerIpGeolocationMode)) {
			return mode as SignerIpGeolocationMode
		}
	}

	return null
}

export function normalizeSignerIpGeolocationValue(value: EffectivePolicyValue | unknown): SignerIpGeolocationPolicyValue {
	return {
		mode: resolveSignerIpGeolocationMode(value) ?? 'disabled',
	}
}
