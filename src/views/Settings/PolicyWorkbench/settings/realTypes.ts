/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../types/index'

export type RealPolicyScope = 'system' | 'group' | 'user'
export type RealPolicyResolutionMode = 'precedence' | 'merge' | 'conflict_requires_selection'

export interface RealPolicySettingDefinition {
	key: string
	title: string
	context?: string
	description: string
	editor: unknown
	resolutionMode: RealPolicyResolutionMode
	createEmptyValue: () => EffectivePolicyValue
	normalizeDraftValue: (value: EffectivePolicyValue) => EffectivePolicyValue
	hasSelectableDraftValue: (value: EffectivePolicyValue) => boolean
	normalizeAllowChildOverride: (scope: RealPolicyScope, allowChildOverride: boolean) => boolean
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => EffectivePolicyValue
	summarizeValue: (value: EffectivePolicyValue) => string
	formatAllowOverride: (allowChildOverride: boolean) => string
}
