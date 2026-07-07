/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import { collectMetadataPersonalPreferenceBehavior } from '../signature-text/personalPreferenceBehavior'
import CollectMetadataRuleEditor from './CollectMetadataRuleEditor.vue'

function resolveCollectMetadata(value: EffectivePolicyValue): boolean | null {
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

export const collectMetadataRealDefinition: RealPolicySettingDefinition = {
	key: 'collect_metadata',
	// TRANSLATORS Policy title for storing signer technical metadata such as IP address and browser information.
	title: t('libresign', 'Collect signer metadata'),
	// TRANSLATORS Policy description explaining whether signer IP address and browser user agent are saved when documents are signed.
	description: t('libresign', 'Control whether signer IP address and user agent are stored when signing documents.'),
	personalPreferenceBehavior: collectMetadataPersonalPreferenceBehavior,
	editor: CollectMetadataRuleEditor,
	createEmptyValue: () => false,
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		const resolved = resolveCollectMetadata(value)
		return resolved ?? false
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveCollectMetadata(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return false
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveCollectMetadata(value)
		if (resolved === true) {
			// TRANSLATORS Policy value meaning signer metadata collection is active.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning signer metadata collection is turned off.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit metadata collection rule is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own metadata collection rule.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the metadata collection value defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
