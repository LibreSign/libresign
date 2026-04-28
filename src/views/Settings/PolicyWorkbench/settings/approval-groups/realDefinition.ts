/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ApprovalGroupsRuleEditor from './ApprovalGroupsRuleEditor.vue'
import { DEFAULT_APPROVAL_GROUPS, resolveApprovalGroups, serializeApprovalGroups } from './model'

export const approvalGroupsRealDefinition: RealPolicySettingDefinition = {
	key: 'approval_group',
	title: t('libresign', 'Identification document approvers'),
	description: t('libresign', 'Choose which groups can approve submitted identification documents when this flow is enabled.'),
	supportedScopes: ['system', 'group'],
	editor: ApprovalGroupsRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeApprovalGroups(DEFAULT_APPROVAL_GROUPS),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeApprovalGroups(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveApprovalGroups(value).length > 0,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeApprovalGroups(DEFAULT_APPROVAL_GROUPS)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const groupIds = resolveApprovalGroups(value)
		if (groupIds.length === 0) {
			return t('libresign', 'No approver groups configured')
		}

		if (groupIds.length <= 2) {
			return groupIds.join(', ')
		}

		return t('libresign', '{count} approver groups', { count: String(groupIds.length) })
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
