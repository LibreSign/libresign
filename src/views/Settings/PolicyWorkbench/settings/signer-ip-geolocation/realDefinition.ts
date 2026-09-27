/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SignerIpGeolocationRuleEditor from './SignerIpGeolocationRuleEditor.vue'
import { normalizeSignerIpGeolocationValue, resolveSignerIpGeolocationMode } from './model'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export { normalizeSignerIpGeolocationValue, resolveSignerIpGeolocationMode } from './model'

export const signerIpGeolocationRealDefinition: RealPolicySettingDefinition = {
	key: 'signer_ip_geolocation',
	// TRANSLATORS Policy title for approximate location resolved from the signer IP address.
	title: t('libresign', 'IP-based approximate location'),
	// TRANSLATORS Policy description: IP geolocation uses the signer IP and the local GeoIP database; there is no per-signer override.
	description: t('libresign', 'Control whether an approximate location is stored from the signer IP address and the locally configured GeoIP database. There is no per-signer requester override.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
	},
	editor: SignerIpGeolocationRuleEditor,
	createEmptyValue: () => normalizeSignerIpGeolocationValue(null),
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeSignerIpGeolocationValue(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveSignerIpGeolocationMode(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeSignerIpGeolocationValue(policyValue)
		}

		return normalizeSignerIpGeolocationValue(null)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const mode = resolveSignerIpGeolocationMode(value)
		switch (mode) {
		case 'disabled':
			// TRANSLATORS Policy value meaning IP geolocation is turned off.
			return t('libresign', 'Disabled')
		case 'enabled':
			// TRANSLATORS Policy value meaning IP geolocation is stored for new requests.
			return t('libresign', 'Enabled')
		default:
			// TRANSLATORS Fallback policy summary when no valid IP geolocation mode could be resolved.
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
