/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import EmailSignerSignatureRuleEditor from './EmailSignerSignatureRuleEditor.vue'
import { resolveEmailSignerSignature } from './model'

export const emailSignerSignatureRealDefinition: RealPolicySettingDefinition = {
	key: 'persist_email_signer_signatures',
	// TRANSLATORS Policy title for letting signers without an account, identified by email, reuse their visible signature on later documents.
	title: t('libresign', 'Remember signatures of email signers'),
	// TRANSLATORS Policy description. The "confirm method" is the one configured for the Email identification factor, such as click to sign or email token.
	description: t('libresign', 'Allow signers identified by email to choose to save their visible signature and reuse it on later documents after passing the configured confirm method.'),
	supportedScopes: ['system'],
	groupAdminBehavior: {
		canRenderPolicy: () => false,
	},
	editor: EmailSignerSignatureRuleEditor,
	createEmptyValue: () => false,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveEmailSignerSignature(value)
		return resolved ?? false
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveEmailSignerSignature(value) !== null,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return false
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveEmailSignerSignature(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning signers identified by email can reuse their saved visible signature.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning signers identified by email must create their visible signature on every document.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit value is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: () => {
		// TRANSLATORS Policy inheritance message indicating group and user scopes cannot override this setting.
		return t('libresign', 'Lower-level customization is disabled for this setting')
	},
}
