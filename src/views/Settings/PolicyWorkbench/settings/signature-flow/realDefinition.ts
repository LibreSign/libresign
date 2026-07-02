/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureFlowScalarRuleEditor from './SignatureFlowScalarRuleEditor.vue'
import { resolveSignatureFlowMode } from './model'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export { resolveSignatureFlowMode } from './model'

export const signatureFlowRealDefinition: RealPolicySettingDefinition = {
	key: 'signature_flow',
	// TRANSLATORS Policy title in admin settings that controls whether signers must follow an order or may sign in parallel.
	title: t('libresign', 'Signing order'),
	// TRANSLATORS Policy description shown under "Signing order". It explains that the rule decides between sequential signing and concurrent signing.
	description: t('libresign', 'Choose whether documents are signed in order or all at once.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	editor: SignatureFlowScalarRuleEditor,
	createEmptyValue: () => '' as unknown as EffectivePolicyValue,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const mode = resolveSignatureFlowMode(value)
		return mode ?? 'parallel'
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveSignatureFlowMode(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
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
			// TRANSLATORS Policy value label meaning all signers can sign the same document at the same time.
			return t('libresign', 'Parallel')
		case 'ordered_numeric':
			// TRANSLATORS Policy value label meaning signers must sign one after another in a configured order.
			return t('libresign', 'Sequential')
		case 'none':
			// TRANSLATORS Policy summary meaning no explicit rule is set at this level, so the instance-level default is used.
			return t('libresign', 'Using instance default')
		default:
			// TRANSLATORS Fallback policy summary shown when no valid value could be resolved.
			return t('libresign', 'Not configured')
		}
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and user scopes may define a different value.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must use the value defined at the current scope.
			: t('libresign', 'Groups and accounts must follow this value'),
}
