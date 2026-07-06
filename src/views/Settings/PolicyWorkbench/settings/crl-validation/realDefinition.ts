/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import CrlValidationRuleEditor from './CrlValidationRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

/**
 * Normalize the CRL validation policy value to a boolean when possible.
 *
 * @param value Raw effective policy value.
 * @return Normalized boolean value or null when the input is not interpretable.
 */
function resolveCrlValidation(value: EffectivePolicyValue): boolean | null {
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

export const crlValidationRealDefinition: RealPolicySettingDefinition = {
	key: 'crl_external_validation_enabled',
	// TRANSLATORS Policy title. CRL means Certificate Revocation List, a list of revoked digital certificates.
	title: t('libresign', 'External CRL validation'),
	// TRANSLATORS Policy description about checking external CRL URLs during certificate trust validation.
	description: t('libresign', 'Control whether external CRL distribution points are validated during certificate checks.'),
	supportedScopes: ['system', 'group'],
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && (policy?.canSaveAsUserDefault === true || policy?.meta?.canCreateDescendantRules === true),
	},
	editor: CrlValidationRuleEditor,
	createEmptyValue: () => true,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveCrlValidation(value)
		return resolved ?? true
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveCrlValidation(value) !== null,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return true
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveCrlValidation(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning external CRL checks are active.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning external CRL checks are skipped.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit value is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: () => {
		// TRANSLATORS Policy inheritance message indicating group and user scopes cannot override this CRL validation rule.
		return t('libresign', 'Lower-level customization is disabled for this setting')
	},
}
