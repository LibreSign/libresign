/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyState, EffectivePolicyValue } from '../../../../types/index'

export type RealPolicyScope = 'system' | 'group' | 'user'
export type RealPolicyResolutionMode = 'precedence' | 'merge' | 'conflict_requires_selection'
export type RealPolicyEditorDialogLayout = 'default' | 'wide'

export interface RealPolicySettingDefinition {
	key: string
	title: string
	context?: string
	description: string
	editor: unknown
	editorProps?: Record<string, unknown>
	resolveEditorProps?: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => Record<string, unknown>
	editorDialogLayout?: RealPolicyEditorDialogLayout
	resolutionMode: RealPolicyResolutionMode
	createEmptyValue: () => EffectivePolicyValue
	normalizeDraftValue: (value: EffectivePolicyValue) => EffectivePolicyValue
	hasSelectableDraftValue: (value: EffectivePolicyValue) => boolean
	normalizeAllowChildOverride: (scope: RealPolicyScope, allowChildOverride: boolean) => boolean
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => EffectivePolicyValue
	summarizeValue: (value: EffectivePolicyValue) => string
	formatAllowOverride: (allowChildOverride: boolean) => string
}
