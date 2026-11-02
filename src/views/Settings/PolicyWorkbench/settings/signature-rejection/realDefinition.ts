/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureRejectionRuleEditor from './SignatureRejectionRuleEditor.vue'
import type { EffectivePolicyState, EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import {
	composeSignatureRejectionValue,
	decomposeSignatureRejectionValue,
	DEFAULT_SIGNATURE_REJECTION,
	normalizeSignatureRejectionDraftValue,
	SIGNATURE_REJECTION_ENABLED_KEY,
	summarizeSignatureRejectionDraftValue,
} from './model'

export const signatureRejectionRealDefinition: RealPolicySettingDefinition = {
	key: SIGNATURE_REJECTION_ENABLED_KEY,
	// TRANSLATORS Policy title for letting signers refuse to sign a document.
	title: t('libresign', 'Signature rejection'),
	// TRANSLATORS Policy description for the signature rejection settings.
	description: t('libresign', 'Let signers reject a signing request and choose what happens next, whether they must explain why, and who is told.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	editor: SignatureRejectionRuleEditor,
	supportedScopes: ['system', 'group', 'user'],
	compound: {
		compose: composeSignatureRejectionValue,
		decompose: decomposeSignatureRejectionValue,
	},
	resolveEditorProps: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => {
		const allowedEnabledValues = Array.isArray(policy?.allowedValues)
			? policy.allowedValues.filter((value): value is boolean => typeof value === 'boolean')
			: []

		return allowedEnabledValues.length > 0
			? { ...baseEditorProps, allowedEnabledValues }
			: baseEditorProps
	},
	createEmptyValue: () => ({ ...DEFAULT_SIGNATURE_REJECTION }),
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeSignatureRejectionDraftValue(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeSignatureRejectionDraftValue(policyValue)
		}

		return { ...DEFAULT_SIGNATURE_REJECTION }
	},
	summarizeValue: (value: EffectivePolicyValue) => summarizeSignatureRejectionDraftValue(value, t),
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and account levels may define their own signature rejection rule.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must use this signature rejection rule.
			: t('libresign', 'Groups and accounts must follow this value'),
}
