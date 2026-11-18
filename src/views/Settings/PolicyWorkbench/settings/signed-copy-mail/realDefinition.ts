/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import SignedCopyMailRuleEditor from './SignedCopyMailRuleEditor.vue'
import {
	normalizeSignedCopyMailPolicyConfig,
	serializeSignedCopyMailPolicyConfig,
	SIGNED_COPY_MAIL_POLICY_DEFAULTS,
	SIGNED_COPY_MAIL_POLICY_KEY,
} from './model'

export const signedCopyMailRealDefinition: RealPolicySettingDefinition = {
	key: SIGNED_COPY_MAIL_POLICY_KEY,
	// TRANSLATORS Policy title for mailing the fully signed document after the last signature.
	title: t('libresign', 'Email the signed document'),
	// TRANSLATORS Policy description. Archive addresses are optional email addresses that receive every signed document.
	description: t('libresign', 'Send the signed document as an attachment to chosen signers and archive addresses after the last signature.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: SignedCopyMailRuleEditor,
	supportedScopes: ['system', 'group', 'user'],
	createEmptyValue: () => serializeSignedCopyMailPolicyConfig(SIGNED_COPY_MAIL_POLICY_DEFAULTS),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeSignedCopyMailPolicyConfig(normalizeSignedCopyMailPolicyConfig(value)),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeSignedCopyMailPolicyConfig(SIGNED_COPY_MAIL_POLICY_DEFAULTS)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeSignedCopyMailPolicyConfig(value)
		if (!normalized.enabled) {
			// TRANSLATORS Policy summary meaning signed documents are not sent by email.
			return t('libresign', 'Disabled')
		}

		if (normalized.archive_addresses.length === 0) {
			// TRANSLATORS Policy summary meaning signed documents are sent by email to the chosen signers only.
			return t('libresign', 'Enabled')
		}

		// TRANSLATORS Policy summary when signed documents are sent by email. {addresses} is a comma-separated list of archive email addresses.
		return t('libresign', 'Enabled • archive: {addresses}', {
			addresses: normalized.archive_addresses.join(', '),
		})
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own signed document email rule.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the signed document email rule defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
