/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import DefaultUserFolderRuleEditor from './DefaultUserFolderRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import { DEFAULT_USER_FOLDER, normalizeDefaultUserFolder } from './model'

export const defaultUserFolderRealDefinition: RealPolicySettingDefinition = {
	key: 'default_user_folder',
	title: t('libresign', 'Customize default account folder'),
	description: t('libresign', 'Name of the folder that will contain the account\'s digital certificate, visible signature images, and other files related to LibreSign.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: DefaultUserFolderRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => DEFAULT_USER_FOLDER,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeDefaultUserFolder(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_USER_FOLDER
	},
	summarizeValue: (value: EffectivePolicyValue) => normalizeDefaultUserFolder(value),
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and accounts can set their own rule')
			: t('libresign', 'Groups and accounts must follow this value'),
}
