/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyState, EffectivePolicyValue } from '../../../../types/index'

export type RealPolicyScope = 'system' | 'group' | 'user'
export type RealPolicyResolutionMode = 'precedence' | 'merge' | 'conflict_requires_selection'
export type RealPolicyEditorDialogLayout = 'default' | 'wide'

export type GroupAdminRenderablePolicyState = Pick<EffectivePolicyState, 'editableByCurrentActor' | 'canSaveAsUserDefault' | 'meta'> | null | undefined

export interface RealPolicyGroupAdminBehavior {
	canRenderPolicy?: (policy: GroupAdminRenderablePolicyState) => boolean
	hideNonRemovableGroupRules?: (policy: GroupAdminRenderablePolicyState) => boolean
	preferHydratedVisibleGroupCount?: boolean
	allowGroupRuleCreationFromDescendantDelegation?: boolean
}

export interface RealPolicyPersonalPreferenceContext {
	getPolicy: (policyKey: string) => EffectivePolicyState | null
	saveUserPreference: (policyKey: string, value: EffectivePolicyValue) => Promise<unknown>
	clearUserPreference: (policyKey: string) => Promise<unknown>
}

export interface RealPolicyPersonalPreferenceBehavior {
	shouldRender?: (policy: EffectivePolicyState | null, context: RealPolicyPersonalPreferenceContext) => boolean
	resolvePolicy?: (policy: EffectivePolicyState | null, context: RealPolicyPersonalPreferenceContext) => EffectivePolicyState | null
	resolveSelectedValue?: (policy: EffectivePolicyState | null, context: RealPolicyPersonalPreferenceContext) => EffectivePolicyValue
	normalizeValue?: (value: EffectivePolicyValue, context: RealPolicyPersonalPreferenceContext) => EffectivePolicyValue
	getEffectiveValue?: (policy: EffectivePolicyState | null, context: RealPolicyPersonalPreferenceContext) => EffectivePolicyValue
	canSave?: (policy: EffectivePolicyState | null, context: RealPolicyPersonalPreferenceContext) => boolean
	hasSavedPreference?: (policy: EffectivePolicyState | null, context: RealPolicyPersonalPreferenceContext) => boolean
	savePreference?: (value: EffectivePolicyValue, context: RealPolicyPersonalPreferenceContext) => Promise<void>
	clearPreference?: (context: RealPolicyPersonalPreferenceContext) => Promise<void>
}

export interface RealPolicyAllowOverrideContext {
	scope: RealPolicyScope
	editorMode: 'create' | 'edit' | null
	viewMode: 'system-admin' | 'group-admin'
}

export type RealPolicySettingCategory =
	| 'who-can-sign'
	| 'how-signing-works'
	| 'signer-experience'
	| 'what-gets-recorded'
	| 'time-and-limits'
	| 'trust-and-verification'
	| 'system-behavior'

export interface RealPolicySettingDefinition {
	key: string
	title: string
	context?: string
	category?: RealPolicySettingCategory
	description: string
	supportedScopes?: ReadonlyArray<RealPolicyScope>
	groupAdminBehavior?: RealPolicyGroupAdminBehavior
	personalPreferenceBehavior?: RealPolicyPersonalPreferenceBehavior
	editor: unknown
	editorProps?: Record<string, unknown>
	resolveEditorProps?: (policy: EffectivePolicyState | null, baseEditorProps: Record<string, unknown>) => Record<string, unknown>
	editorDialogLayout?: RealPolicyEditorDialogLayout
	resolutionMode: RealPolicyResolutionMode
	createEmptyValue: () => EffectivePolicyValue
	/**
	 * When true, the 'Scope groups' selector is hidden in the rule editor dialog and
	 * the editor drives target selection: the workbench automatically sets
	 * targetIds to the allow-group IDs extracted from the policy value each
	 * time the editor emits a new value.
	 *
	 * Only meaningful for group-scope rules.
	 */
	extractScopeTargets?: (scope: RealPolicyScope, value: EffectivePolicyValue) => string[]
	syncCreateDraftValueFromTargets?: (scope: RealPolicyScope, targetIds: string[], currentValue: EffectivePolicyValue, isInstanceAdmin: boolean) => EffectivePolicyValue
	normalizeDraftValue: (value: EffectivePolicyValue) => EffectivePolicyValue
	hasSelectableDraftValue: (value: EffectivePolicyValue) => boolean
	isBaselineSeedable?: (value: EffectivePolicyValue) => boolean
	normalizeAllowChildOverride: (scope: RealPolicyScope, allowChildOverride: boolean, context?: RealPolicyAllowOverrideContext) => boolean
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null, policyState?: EffectivePolicyState | null) => EffectivePolicyValue
	summarizeValue: (value: EffectivePolicyValue) => string
	formatAllowOverride: (allowChildOverride: boolean) => string
}
