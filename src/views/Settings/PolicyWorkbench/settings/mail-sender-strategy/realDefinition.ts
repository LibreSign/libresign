/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import MailSenderStrategyRuleEditor from './MailSenderStrategyRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import { DEFAULT_MAIL_SENDER_STRATEGY, normalizeMailSenderStrategy } from './model'

export const mailSenderStrategyRealDefinition: RealPolicySettingDefinition = {
	key: 'mail_sender_strategy',
	// TRANSLATORS Policy title controlling which mail account sends signature request notification emails.
	title: t('libresign', 'Notification email sender'),
	// TRANSLATORS Policy description for choosing between the system mailer and the mail account of the person who requested the signature.
	description: t('libresign', 'Choose whether signature request emails are sent by the system mailer or by the mail account of the person who requested the signature.'),
	supportedScopes: ['system'],
	groupAdminBehavior: {
		canRenderPolicy: () => false,
	},
	editor: MailSenderStrategyRuleEditor,
	createEmptyValue: () => DEFAULT_MAIL_SENDER_STRATEGY,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeMailSenderStrategy(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeMailSenderStrategy(policyValue)
		}

		return DEFAULT_MAIL_SENDER_STRATEGY
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		if (normalizeMailSenderStrategy(value) === 'requester') {
			// TRANSLATORS Policy summary meaning notification emails are sent from the mail account of the person who requested the signature.
			return t('libresign', 'Requester mail account')
		}

		// TRANSLATORS Policy summary meaning notification emails are sent by the Nextcloud system mailer.
		return t('libresign', 'System mailer')
	},
	formatAllowOverride: () =>
		// TRANSLATORS Policy inheritance message indicating group and user scopes cannot override this setting.
		t('libresign', 'Lower-level customization is disabled for this setting'),
}
