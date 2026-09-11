/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ObserverProfileRuleEditor from './ObserverProfileRuleEditor.vue'

function resolveObserverProfile(value: EffectivePolicyValue): boolean | null {
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

export const observerProfileRealDefinition: RealPolicySettingDefinition = {
	key: 'enable_observer_profile',
	// TRANSLATORS Policy title for enabling observer participants on signature requests.
	title: t('libresign', 'Observer profile'),
	// TRANSLATORS Policy description explaining whether document owners can assign observer role to participants.
	description: t('libresign', 'Allow assigning observer role to participants who can view documents without signing.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: ObserverProfileRuleEditor,
	createEmptyValue: () => false,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveObserverProfile(value)
		return resolved ?? false
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveObserverProfile(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return false
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveObserverProfile(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning observer participants can be assigned.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning observer participants cannot be assigned.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary when observer profile is not configured.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message for observer profile child scopes.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message requiring child scopes to follow observer profile value.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
