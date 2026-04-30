/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignatureHashAlgorithmRuleEditor from './SignatureHashAlgorithmRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import { DEFAULT_HASH_ALGORITHM, normalizeHashAlgorithm } from './model'

export const signatureHashAlgorithmRealDefinition: RealPolicySettingDefinition = {
	key: 'signature_hash_algorithm',
	title: t('libresign', 'Signature hash algorithm'),
	description: t('libresign', 'Hash algorithm used for signature.'),
	editor: SignatureHashAlgorithmRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => DEFAULT_HASH_ALGORITHM,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeHashAlgorithm(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_HASH_ALGORITHM
	},
	summarizeValue: (value: EffectivePolicyValue) => normalizeHashAlgorithm(value),
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
