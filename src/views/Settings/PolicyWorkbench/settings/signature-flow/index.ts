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
	menuHint: t('libresign', 'Good policy-shell candidate because it combines a simple editor with multiple scopes.'),
	editor: SignatureFlowRuleEditor,
	createEmptyValue: () => ({
		enabled: true,
		flow: 'parallel',
	}),
	summarizeValue: (value) => {
		if (!value.enabled) {
			return t('libresign', 'Disabled')
		}

		return value.flow === 'parallel'
			? t('libresign', 'Parallel')
			: t('libresign', 'Sequential')
	},
	formatAllowOverride: (allowChildOverride) => allowChildOverride
		? t('libresign', 'Lower layers may override this rule')
		: t('libresign', 'Lower layers must inherit this value'),
}
