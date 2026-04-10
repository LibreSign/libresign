/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureFlowScalarRuleEditor from './SignatureFlowScalarRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export function resolveSignatureFlowMode(value: EffectivePolicyValue): string | null {
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
		const candidate = (value as { flow?: unknown }).flow
		return typeof candidate === 'string' ? candidate : null
	}

	return null
}

export const signatureFlowRealDefinition: RealPolicySettingDefinition = {
	key: 'signature_flow',
	title: t('libresign', 'Signing order'),
	description: t('libresign', 'Choose whether documents are signed in order or all at once.'),
	editor: SignatureFlowScalarRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => '' as unknown as EffectivePolicyValue,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const mode = resolveSignatureFlowMode(value)
		return mode ?? 'parallel'
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveSignatureFlowMode(value) !== null,
	normalizeAllowChildOverride: (scope, allowChildOverride: boolean) => {
		if (scope === 'system' || scope === 'group') {
			return false
		}

		return allowChildOverride
	},
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return 'none'
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const flowValue = resolveSignatureFlowMode(value)
		switch (flowValue) {
		case 'parallel':
			return t('libresign', 'Simultaneous (Parallel)')
		case 'ordered_numeric':
			return t('libresign', 'Sequential')
		case 'none':
			return t('libresign', 'User choice')
		default:
			return t('libresign', 'Not configured')
		}
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
