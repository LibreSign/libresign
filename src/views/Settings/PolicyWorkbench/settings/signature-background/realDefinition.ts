/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import SignatureBackgroundRuleEditor from './SignatureBackgroundRuleEditor.vue'

function resolveBackgroundType(value: EffectivePolicyValue): 'default' | 'custom' | 'deleted' {
	if (value === 'custom' || value === 'deleted') {
		return value
	}

	return 'default'
}

export const signatureBackgroundRealDefinition: RealPolicySettingDefinition = {
	key: 'signature_background_type',
	title: t('libresign', 'Signature background'),
	description: t('libresign', 'Configure whether signatures use the default, custom, or no background image.'),
	editor: SignatureBackgroundRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => 'default',
	normalizeDraftValue: (value: EffectivePolicyValue) => resolveBackgroundType(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return resolveBackgroundType(policyValue)
		}

		return 'default'
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveBackgroundType(value)
		if (resolved === 'custom') {
			return t('libresign', 'Custom background')
		}

		if (resolved === 'deleted') {
			return t('libresign', 'No background')
		}

		return t('libresign', 'Default background')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
