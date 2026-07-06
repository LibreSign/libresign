/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ConfettiRuleEditor from './ConfettiRuleEditor.vue'

function resolveConfetti(value: EffectivePolicyValue): boolean | null {
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

export const confettiRealDefinition: RealPolicySettingDefinition = {
	key: 'show_confetti_after_signing',
	// TRANSLATORS Policy title for enabling or disabling the confetti celebration shown after signing completes.
	title: t('libresign', 'Confetti animation'),
	// TRANSLATORS Policy description explaining whether the post-signing confetti animation is shown to users.
	description: t('libresign', 'Control whether confetti animation is shown after a signature is completed.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: ConfettiRuleEditor,
	createEmptyValue: () => true,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveConfetti(value)
		return resolved ?? true
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveConfetti(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return true
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveConfetti(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning the post-signing confetti animation is active.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning the post-signing confetti animation is turned off.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit confetti rule is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may choose their own confetti setting.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must keep the confetti setting defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
