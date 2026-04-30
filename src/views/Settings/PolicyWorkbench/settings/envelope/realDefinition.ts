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
	title: t('libresign', 'Envelope feature'),
	description: t('libresign', 'Control whether envelope creation is available for document signing workflows.'),
	editor: EnvelopeRuleEditor,
	resolutionMode: 'precedence',
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
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
