/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import AddFooterScalarRuleEditor from './AddFooterScalarRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export function resolveAddFooterEnabled(value: EffectivePolicyValue): boolean | null {
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		return value === 1
	}

	if (typeof value === 'string') {
		if (['1', 'true', 'yes', 'on'].includes(value.toLowerCase())) {
			return true
		}

		if (['0', 'false', 'no', 'off'].includes(value.toLowerCase())) {
			return false
		}
	}

	return null
}

export const addFooterRealDefinition: RealPolicySettingDefinition = {
	key: 'add_footer',
	title: t('libresign', 'Signature footer'),
	description: t('libresign', 'Control whether signed files include the LibreSign footer.'),
	editor: AddFooterScalarRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => true,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const enabled = resolveAddFooterEnabled(value)
		return enabled ?? true
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveAddFooterEnabled(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return true
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const enabled = resolveAddFooterEnabled(value)
		if (enabled === true) {
			return t('libresign', 'Enabled')
		}

		if (enabled === false) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}