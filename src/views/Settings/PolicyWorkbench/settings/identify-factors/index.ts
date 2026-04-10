/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import IdentifyFactorsRuleEditor from './IdentifyFactorsRuleEditor.vue'
import type { PolicySettingDefinition } from '../../types'

export const identifyFactorsDefinition: PolicySettingDefinition<'identify_factors'> = {
	key: 'identify_factors',
	title: t('libresign', 'Identification factors'),
	context: t('libresign', 'Identity matrix'),
	description: t('libresign', 'Configure which factors identify signers and how each factor maps to signature methods.'),
	editor: IdentifyFactorsRuleEditor,
	createEmptyValue: () => ({
		enabled: true,
		requireAnyTwo: false,
		factors: [
			{
				key: 'email',
				label: t('libresign', 'Email'),
				enabled: true,
				required: true,
				allowCreateAccount: true,
				signatureMethod: 'email_token',
			},
			{
				key: 'sms',
				label: t('libresign', 'SMS'),
				enabled: false,
				required: false,
				allowCreateAccount: false,
				signatureMethod: 'sms_token',
			},
			{
				key: 'whatsapp',
				label: t('libresign', 'WhatsApp'),
				enabled: false,
				required: false,
				allowCreateAccount: false,
				signatureMethod: 'whatsapp_token',
			},
			{
				key: 'document',
				label: t('libresign', 'Document data'),
				enabled: false,
				required: false,
				allowCreateAccount: false,
				signatureMethod: 'document_validation',
			},
		],
	}),
	summarizeValue: (value) => {
		if (!value.enabled) {
			return t('libresign', 'Disabled')
		}

		const enabledCount = value.factors.filter((factor) => factor.enabled).length
		const strategy = value.requireAnyTwo
			? t('libresign', 'any two')
			: t('libresign', 'single factor')
		return `${enabledCount} ${t('libresign', 'factors enabled')} - ${strategy}`
	},
	formatAllowOverride: (allowChildOverride) => allowChildOverride
		? t('libresign', 'Lower layers may override this rule')
		: t('libresign', 'Lower layers must inherit this value'),
}
