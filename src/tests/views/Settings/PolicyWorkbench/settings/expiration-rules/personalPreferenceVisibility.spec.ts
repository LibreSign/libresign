/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import type { EffectivePolicyState, EffectivePolicyValue } from '../../../../../../types/index'
import { canRenderPersonalPreferencePolicy } from '../../../../../../views/Preferences/personalPreferenceVisibility'

function createAdminAssignedUserPolicy(policyKey: string, effectiveValue: EffectivePolicyValue): EffectivePolicyState {
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

describe('expiration-rules personal preference visibility', () => {
	it('does not render request expiration preference when administrator assigns merged settings at user scope', () => {
		const maximumValidityPolicy = createAdminAssignedUserPolicy('maximum_validity', 86400)
		const renewalIntervalPolicy = createAdminAssignedUserPolicy('renewal_interval', 3600)

		expect(canRenderPersonalPreferencePolicy('maximum_validity', maximumValidityPolicy)).toBe(false)
		expect(canRenderPersonalPreferencePolicy('renewal_interval', renewalIntervalPolicy)).toBe(false)
	})

	it('does not render expiry in days preference when administrator assigns it at user scope', () => {
		const policy = createAdminAssignedUserPolicy('expiry_in_days', 365)

		expect(canRenderPersonalPreferencePolicy('expiry_in_days', policy)).toBe(false)
	})
})