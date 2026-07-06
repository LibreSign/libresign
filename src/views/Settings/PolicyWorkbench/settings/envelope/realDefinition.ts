/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import EnvelopeRuleEditor from './EnvelopeRuleEditor.vue'

function resolveEnvelopeEnabled(value: EffectivePolicyValue): boolean | null {
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		if (value === 1) {
			return true
		}

		if (value === 0) {
			return false
		}

		return null
	}

	if (typeof value === 'string') {
		const normalized = value.trim().toLowerCase()
		if (['1', 'true'].includes(normalized)) {
			return true
		}

		if (['0', 'false', ''].includes(normalized)) {
			return false
		}
	}

	return null
}

export const envelopeRealDefinition: RealPolicySettingDefinition = {
	key: 'envelope_enabled',
	// TRANSLATORS Policy title for enabling signing envelopes that bundle multiple files into one request.
	title: t('libresign', 'Signing envelopes'),
	// TRANSLATORS Policy description explaining whether accounts may group several files into a single signing envelope.
	description: t('libresign', 'Allow accounts to group multiple files into envelopes for signing.'),
	supportedScopes: ['system', 'group', 'user'],
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: EnvelopeRuleEditor,
	createEmptyValue: () => true,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveEnvelopeEnabled(value)
		return resolved ?? true
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveEnvelopeEnabled(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return true
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveEnvelopeEnabled(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning signing envelopes are enabled.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning signing envelopes are disabled.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit envelope rule is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own envelope rule.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the envelope rule defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
