/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import RequestSignGroupsRuleEditor from './RequestSignGroupsRuleEditor.vue'
import { DEFAULT_REQUEST_SIGN_GROUPS, resolveRequestSignGroups, serializeRequestSignGroups } from './model'

export const requestSignGroupsRealDefinition: RealPolicySettingDefinition = {
	key: 'groups_request_sign',
	title: t('libresign', 'Request access by group'),
	description: t('libresign', 'Control which groups can request signatures.'),
	supportedScopes: ['system', 'group'],
	editor: RequestSignGroupsRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeRequestSignGroups(DEFAULT_REQUEST_SIGN_GROUPS),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeRequestSignGroups(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveRequestSignGroups(value).length > 0,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeRequestSignGroups(DEFAULT_REQUEST_SIGN_GROUPS)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const groupIds = resolveRequestSignGroups(value)
		if (groupIds.length === 0) {
			return t('libresign', 'No groups allowed')
		}

		if (groupIds.length <= 2) {
			return groupIds.join(', ')
		}

		return t('libresign', '{count} groups allowed', { count: String(groupIds.length) })
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
