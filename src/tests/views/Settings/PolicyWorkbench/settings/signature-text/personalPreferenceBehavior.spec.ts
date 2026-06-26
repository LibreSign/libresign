/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import type { EffectivePolicyState } from '../../../../../../views/Settings/PolicyWorkbench/settings/realTypes'
import {
	COLLECT_METADATA_POLICY_KEY,
	SIGNATURE_STAMP_POLICY_KEY,
	collectMetadataPersonalPreferenceBehavior,
	signatureStampPersonalPreferenceBehavior,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/personalPreferenceBehavior'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

function createPolicyState(overrides: Partial<EffectivePolicyState>): EffectivePolicyState {
	return {
		policyKey: overrides.policyKey ?? 'policy',
		effectiveValue: overrides.effectiveValue ?? null,
		inheritedValue: overrides.inheritedValue,
		sourceScope: overrides.sourceScope ?? 'system',
		visible: overrides.visible ?? true,
		editableByCurrentActor: overrides.editableByCurrentActor ?? true,
		allowedValues: overrides.allowedValues ?? [],
		canSaveAsUserDefault: overrides.canSaveAsUserDefault ?? true,
		canUseAsRequestOverride: overrides.canUseAsRequestOverride ?? true,
		preferenceWasCleared: overrides.preferenceWasCleared ?? false,
		blockedBy: overrides.blockedBy ?? null,
		meta: overrides.meta,
		groupCount: overrides.groupCount ?? 0,
		userCount: overrides.userCount ?? 0,
		everyoneCount: overrides.everyoneCount ?? 0,
	}
}

function createContext(policies: Record<string, EffectivePolicyState | null>) {
	return {
		getPolicy: vi.fn((policyKey: string) => policies[policyKey] ?? null),
		saveUserPreference: vi.fn(async () => undefined),
		clearUserPreference: vi.fn(async () => undefined),
	}
}

describe('signature-text personalPreferenceBehavior', () => {
	it('merges signature stamp and collect metadata into a single selected preference value', () => {
		const signatureStampValue = '{"template":"Signed with LibreSign","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}'
		const context = createContext({
			[SIGNATURE_STAMP_POLICY_KEY]: createPolicyState({ policyKey: SIGNATURE_STAMP_POLICY_KEY, effectiveValue: signatureStampValue }),
			[COLLECT_METADATA_POLICY_KEY]: createPolicyState({ policyKey: COLLECT_METADATA_POLICY_KEY, effectiveValue: true }),
		})

		expect(signatureStampPersonalPreferenceBehavior.resolveSelectedValue?.(null, context as never)).toEqual({
			signatureStampValue: signatureStampValue,
			collectMetadataEnabled: true,
		})
	})

	it('saves only the collect_metadata preference when the signature stamp text did not change', async () => {
		const signatureStampValue = '{"template":"Signed with LibreSign","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}'
		const context = createContext({
			[SIGNATURE_STAMP_POLICY_KEY]: createPolicyState({ policyKey: SIGNATURE_STAMP_POLICY_KEY, effectiveValue: signatureStampValue }),
			[COLLECT_METADATA_POLICY_KEY]: createPolicyState({ policyKey: COLLECT_METADATA_POLICY_KEY, effectiveValue: true }),
		})

		await signatureStampPersonalPreferenceBehavior.savePreference?.({
			signatureStampValue: signatureStampValue,
			collectMetadataEnabled: false,
		}, context as never)

		expect(context.saveUserPreference).toHaveBeenCalledTimes(1)
		expect(context.saveUserPreference).toHaveBeenCalledWith(COLLECT_METADATA_POLICY_KEY, false)
	})

	it('hides the standalone collect-metadata preference when the merged signature-stamp preference is renderable', () => {
		const context = createContext({
			[SIGNATURE_STAMP_POLICY_KEY]: createPolicyState({ policyKey: SIGNATURE_STAMP_POLICY_KEY, effectiveValue: 'stamp', canSaveAsUserDefault: true }),
			[COLLECT_METADATA_POLICY_KEY]: createPolicyState({ policyKey: COLLECT_METADATA_POLICY_KEY, effectiveValue: true, canSaveAsUserDefault: true }),
		})

		expect(collectMetadataPersonalPreferenceBehavior.shouldRender?.(context.getPolicy(COLLECT_METADATA_POLICY_KEY), context as never)).toBe(false)
	})
})
