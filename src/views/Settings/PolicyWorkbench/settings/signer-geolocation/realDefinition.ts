/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignerGeolocationRuleEditor from './SignerGeolocationRuleEditor.vue'
import { normalizeSignerGeolocationValue, resolveSignerGeolocationMode } from './model'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export { normalizeSignerGeolocationValue, resolveSignerGeolocationMode } from './model'

export const signerGeolocationRealDefinition: RealPolicySettingDefinition = {
	key: 'signer_geolocation',
	// TRANSLATORS Policy title for device-reported location during signing.
	title: t('libresign', 'Signer geolocation'),
	// TRANSLATORS Policy description: optional lets requesters require device location per signer; it is not automatic soft collection.
	description: t('libresign', 'Control whether device-reported location is used when signing. Optional lets requesters require device location for selected signers.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	editor: SignerGeolocationRuleEditor,
	createEmptyValue: () => normalizeSignerGeolocationValue(null),
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeSignerGeolocationValue(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveSignerGeolocationMode(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeSignerGeolocationValue(policyValue)
		}

		return normalizeSignerGeolocationValue(null)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const mode = resolveSignerGeolocationMode(value)
		switch (mode) {
		case 'disabled':
			// TRANSLATORS Policy value meaning device geolocation is turned off.
			return t('libresign', 'Disabled')
		case 'optional':
			// TRANSLATORS Policy value meaning requesters may require device location for selected signers.
			return t('libresign', 'Optional')
		case 'required':
			// TRANSLATORS Policy value meaning device location is mandatory for every signer.
			return t('libresign', 'Required')
		default:
			// TRANSLATORS Fallback policy summary when no valid geolocation mode could be resolved.
			return t('libresign', 'Not configured')
		}
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			// TRANSLATORS Policy inheritance message indicating group and user scopes may define a different value.
			? t('libresign', 'Groups and accounts can set their own rule')
			// TRANSLATORS Policy inheritance message indicating child scopes must use the value defined at the current scope.
			: t('libresign', 'Groups and accounts must follow this value'),
}
