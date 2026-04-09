/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureFlowRuleEditor from './SignatureFlowRuleEditor.vue'
import type { PolicySettingDefinition } from '../../types'

export const signatureFlowDefinition: PolicySettingDefinition<'signature_flow'> = {
	key: 'signature_flow',
	title: t('libresign', 'Signing order'),
	description: t('libresign', 'Define how signers receive and process the signature request.'),
	menuHint: t('libresign', 'Choose how signing order should work for everyone, groups, and users.'),
	editor: SignatureFlowRuleEditor,
	createEmptyValue: () => ({
		enabled: true,
		flow: 'parallel',
	}),
	summarizeValue: (value) => {
		if (!value.enabled) {
			return t('libresign', 'User choice')
		}

		return value.flow === 'parallel'
			? t('libresign', 'Parallel')
			: t('libresign', 'Sequential')
	},
	formatAllowOverride: (allowChildOverride) => allowChildOverride
		? t('libresign', 'Groups and users can set their own rule')
		: t('libresign', 'Groups and users must follow this value'),
}
