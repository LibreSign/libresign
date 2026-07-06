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
	// TRANSLATORS Policy title for the default folder used to store LibreSign account files.
	title: t('libresign', 'Customize default account folder'),
	// TRANSLATORS Policy description explaining that the folder stores certificate files, visible signature images, and related LibreSign account files.
	description: t('libresign', 'Name of the folder that will contain the account\'s digital certificate, visible signature images, and other files related to LibreSign.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: DefaultUserFolderRuleEditor,
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
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may choose their own default folder name.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the default folder name defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
