/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type SignatureFlowMode = 'none' | 'parallel' | 'ordered_numeric'
export type RequestSignatureFlowOverride = 'parallel' | 'ordered_numeric'

export function resolveSignatureFlowMode(value: EffectivePolicyValue | unknown): SignatureFlowMode | null {
	if (value === 0) {
		return 'none'
	}

	if (value === 1) {
		return 'parallel'
	}

	if (value === 2) {
		return 'ordered_numeric'
	}

	if (typeof value === 'string') {
		if (value === 'parallel' || value === 'ordered_numeric' || value === 'none') {
			return value
		}

		return null
	}

	if (value && typeof value === 'object' && 'flow' in (value as Record<string, unknown>)) {
		const nestedFlow = (value as { flow?: unknown }).flow
		return resolveSignatureFlowMode(nestedFlow)
	}

	return null
}

export function toRequestSignatureFlowOverride(flow: SignatureFlowMode | null): RequestSignatureFlowOverride {
	if (flow === 'ordered_numeric') {
		return 'ordered_numeric'
	}

	return 'parallel'
}
