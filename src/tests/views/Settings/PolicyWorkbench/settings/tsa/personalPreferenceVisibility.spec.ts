/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import type { EffectivePolicyState } from '../../../../../../types/index'
import { canRenderPersonalPreferencePolicy } from '../../../../../../views/Preferences/personalPreferenceVisibility'

/**
 * Builds a user-scoped policy assigned by an administrator for visibility checks.
 *
 * @param policyKey The policy key under test.
 * @param effectiveValue The effective value assigned at user scope.
 */
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

describe('tsa_settings personal preference visibility', () => {
	it('does not render tsa preference when administrator assigns it at user scope', () => {
		const policy = createAdminAssignedUserPolicy(
			'tsa_settings',
			'{"url":"https://freetsa.org/tsr","policy_oid":"","auth_type":"none","username":""}',
		)

		expect(canRenderPersonalPreferencePolicy('tsa_settings', policy)).toBe(false)
	})
})
