/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ExternalSigningEnabledRuleEditor from './ExternalSigningEnabledRuleEditor.vue'
import ExternalSigningPolicySettings from './ExternalSigningPolicySettings.vue'

function resolveEnabled(value: EffectivePolicyValue): boolean | null {
	if (typeof value === 'boolean') {
		return value
	}
	if (value === 1 || value === '1' || value === 'true') {
		return true
	}
	if (value === 0 || value === '0' || value === 'false') {
		return false
	}
	return null
}

export const externalSigningRealDefinition: RealPolicySettingDefinition = {
	key: 'external_signing_enabled',
	// TRANSLATORS Policy card title for rules allowing accounts and groups to use external signature services.
	title: t('libresign', 'External signing providers'),
	// TRANSLATORS Policy card description: scopes control external signing authorization, while connections are configured separately.
	description: t('libresign', 'Manage who may use external signing providers, and configure the available integrations.'),
	supportedScopes: ['system', 'group', 'user'],
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	settingDialogExtras: ExternalSigningPolicySettings,
	editor: ExternalSigningEnabledRuleEditor,
	createEmptyValue: () => false,
	normalizeDraftValue: (value) => resolveEnabled(value) ?? false,
	hasSelectableDraftValue: (value) => resolveEnabled(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride) => allowChildOverride,
	getFallbackSystemDefault: (value, sourceScope) =>
		sourceScope === 'system' && value !== null && value !== undefined
			? resolveEnabled(value) ?? false
			: false,
	summarizeValue: (value) =>
		resolveEnabled(value) === true
			? t('libresign', 'Enabled')
			: t('libresign', 'Disabled'),
	formatAllowOverride: (allowed) => allowed
		? t('libresign', 'Groups and accounts can set their own rule')
		: t('libresign', 'Groups and accounts must follow this value'),
}
