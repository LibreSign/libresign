/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import type { EffectivePolicyState } from '../../../types/index'
import { canRenderPersonalPreferencePolicy } from '../../../views/Preferences/personalPreferenceVisibility'

function createAdminAssignedUserPolicy(policyKey: string, effectiveValue: EffectivePolicyState['effectiveValue']): EffectivePolicyState {
	return {
		policyKey,
		effectiveValue,
		sourceScope: 'user',
		visible: true,
		editableByCurrentActor: false,
		allowedValues: [],
		blockedBy: 'system',
		canSaveAsUserDefault: false,
		canUseAsRequestOverride: false,
		preferenceWasCleared: false,
		groupCount: 0,
		userCount: 0,
		everyoneCount: 0,
		meta: {
			supportsUserPreference: false,
		},
	}
}

describe('personalPreferenceVisibility', () => {
	it('does not render tsa_settings when an administrator assigns it at user scope', () => {
		const policy = createAdminAssignedUserPolicy(
			'tsa_settings',
			'{"url":"https://freetsa.org/tsr","policy_oid":"","auth_type":"none","username":""}',
		)

		expect(canRenderPersonalPreferencePolicy('tsa_settings', policy)).toBe(false)
	})

	it('does not render crl_external_validation_enabled when an administrator assigns it at user scope', () => {
		const policy = createAdminAssignedUserPolicy('crl_external_validation_enabled', true)

		expect(canRenderPersonalPreferencePolicy('crl_external_validation_enabled', policy)).toBe(false)
	})

	it('does not render expiration-rules preferences when an administrator assigns them at user scope', () => {
		const maximumValidityPolicy = createAdminAssignedUserPolicy('maximum_validity', 86400)
		const renewalIntervalPolicy = createAdminAssignedUserPolicy('renewal_interval', 3600)
		const expiryInDaysPolicy = createAdminAssignedUserPolicy('expiry_in_days', 365)

		expect(canRenderPersonalPreferencePolicy('maximum_validity', maximumValidityPolicy)).toBe(false)
		expect(canRenderPersonalPreferencePolicy('renewal_interval', renewalIntervalPolicy)).toBe(false)
		expect(canRenderPersonalPreferencePolicy('expiry_in_days', expiryInDaysPolicy)).toBe(false)
	})
})