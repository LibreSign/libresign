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
	title: t('libresign', 'Validation page access'),
	description: t('libresign', 'Control whether the validation page URL requires authentication.'),
	editor: ValidationAccessRuleEditor,
	resolutionMode: 'precedence',
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
			return t('libresign', 'Authenticated only')
		}

		if (resolved === false) {
			return t('libresign', 'Public')
		}

		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
