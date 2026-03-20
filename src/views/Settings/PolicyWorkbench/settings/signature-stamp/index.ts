/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureStampRuleEditor from './SignatureStampRuleEditor.vue'
import type { PolicySettingDefinition } from '../../types'

export const signatureStampDefinition: PolicySettingDefinition<'signature_stamp'> = {
	key: 'signature_stamp',
	title: t('libresign', 'Signature stamp'),
	description: t('libresign', 'Manage the visible signature block, template and dimensions with a richer policy model.'),
	menuHint: t('libresign', 'Complex policy example with dimensions, render modes, template text, and background behavior.'),
	editor: SignatureStampRuleEditor,
	createEmptyValue: () => ({
		enabled: true,
		renderMode: 'GRAPHIC_AND_DESCRIPTION',
		template: '{{ signer_name }} - {{ signed_at }}',
		templateFontSize: 10,
		signatureFontSize: 20,
		signatureWidth: 180,
		signatureHeight: 70,
		backgroundMode: 'default',
		showSigningDate: true,
	}),
	summarizeValue: (value) => {
		if (!value.enabled) {
			return t('libresign', 'Disabled')
		}

		return `${value.renderMode} - ${value.signatureWidth}x${value.signatureHeight}`
	},
	formatAllowOverride: (allowChildOverride) => allowChildOverride
		? t('libresign', 'Lower layers may override this rule')
		: t('libresign', 'Lower layers must inherit this value'),
}
