/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import RequestSignGroupsRuleEditor from './RequestSignGroupsRuleEditor.vue'
import {
	DEFAULT_REQUEST_SIGN_DENY_GROUPS,
	DEFAULT_REQUEST_SIGN_GROUPS,
	resolveDeniedRequestSignGroups,
	resolveRequestSignGroups,
	serializeRequestSignGroups,
} from './model'

export const requestSignGroupsRealDefinition: RealPolicySettingDefinition = {
	key: 'groups_request_sign',
	// TRANSLATORS Catalog title for policy controlling which groups can create signature requests.
	title: t('libresign', 'Signature request access'),
	// TRANSLATORS Catalog description: this policy delegates signature-request creation rights by scope.
	description: t('libresign', 'Define which groups may create signature requests within this scope. Administrators may authorize only groups they belong to.'),
	supportedScopes: ['system', 'group'],
	groupAdminBehavior: {
		hideNonRemovableGroupRules: () => true,
		preferHydratedVisibleGroupCount: true,
	},
	editor: RequestSignGroupsRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeRequestSignGroups({
		allowGroups: [],
		denyGroups: [],
	}),
	extractScopeTargets: (_scope, value) => resolveRequestSignGroups(value),
	syncCreateDraftValueFromTargets: (scope, targetIds, currentValue, isInstanceAdmin) => {
		if (scope !== 'group') {
			return currentValue
		}

		const currentAuthorizedGroups = resolveRequestSignGroups(currentValue)
		const currentDeniedGroups = resolveDeniedRequestSignGroups(currentValue)

		// Group admins should not have Authorized pre-filled with their own groups:
		// the system administrator already set that via allowChildOverride, and the
		// editor will hide the Authorized section for them. Pre-filling it would
		// create spurious conflict warnings (e.g. group appears in both allow + deny).
		if (!isInstanceAdmin) {
			return serializeRequestSignGroups({
				allowGroups: currentAuthorizedGroups,
				denyGroups: currentDeniedGroups,
			})
		}

		if (currentAuthorizedGroups.length > 0) {
			return currentValue
		}

		return serializeRequestSignGroups({
			allowGroups: targetIds,
			denyGroups: currentDeniedGroups,
		})
	},
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeRequestSignGroups(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveRequestSignGroups(value).length > 0,
	normalizeAllowChildOverride: (scope, allowChildOverride) => {
		if (scope === 'user') {
			return false
		}

		return allowChildOverride
	},
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeRequestSignGroups({
			allowGroups: DEFAULT_REQUEST_SIGN_GROUPS,
			denyGroups: DEFAULT_REQUEST_SIGN_DENY_GROUPS,
		})
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const allowGroups = resolveRequestSignGroups(value)
		const denyGroups = resolveDeniedRequestSignGroups(value)
		if (allowGroups.length === 0) {
			// TRANSLATORS Summary text when no requester groups are configured in this rule.
			return t('libresign', 'none configured')
		}

		if (allowGroups.length <= 2 && denyGroups.length === 0) {
			return allowGroups.join(', ')
		}

		if (denyGroups.length === 0) {
			// TRANSLATORS {count} is the number of groups authorized to create signature requests.
			return t('libresign', '{count} authorized requester groups', { count: String(allowGroups.length) })
		}

		// TRANSLATORS Summary for composed request-sign policy. {allowCount} is allowed groups count and {denyCount} denied groups count.
		return t('libresign', '{allowCount} allow · {denyCount} deny', {
			allowCount: String(allowGroups.length),
			denyCount: String(denyGroups.length),
		})
	},
	formatAllowOverride: (allowChildOverride: boolean) => allowChildOverride
		// TRANSLATORS Summary when system policy allows group admins to define requester-group rules.
		? t('libresign', 'Group admins can define scope-specific requester groups')
		// TRANSLATORS Summary when system policy blocks group-admin requester-group customization.
		: t('libresign', 'Group admins must inherit the system requester groups'),
}
