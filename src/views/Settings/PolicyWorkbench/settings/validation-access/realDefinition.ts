/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ValidationAccessRuleEditor from './ValidationAccessRuleEditor.vue'

function resolveValidationAccess(value: EffectivePolicyValue): boolean | null {
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

export const validationAccessRealDefinition: RealPolicySettingDefinition = {
	key: 'make_validation_url_private',
	// TRANSLATORS Policy title that controls who can open the signature validation page URL.
	title: t('libresign', 'Validation page access'),
	// TRANSLATORS Policy description explaining whether authentication is required to access document validation results.
	description: t('libresign', 'Control whether the validation page URL requires authentication.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: ValidationAccessRuleEditor,
	createEmptyValue: () => false,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveValidationAccess(value)
		return resolved ?? false
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveValidationAccess(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return false
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveValidationAccess(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning only signed-in accounts can open the validation page.
			return t('libresign', 'Authenticated only')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning validation page can be opened without signing in when someone has the URL.
			return t('libresign', 'Public')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit value is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and user scopes may define their own access rule.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes cannot override this access rule.
			: t('libresign', 'Groups and accounts must follow this value'),
}
