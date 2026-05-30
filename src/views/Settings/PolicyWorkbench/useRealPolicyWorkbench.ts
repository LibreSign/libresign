/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, nextTick, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import { realDefinitions } from './settings/realDefinitions'
import type { RealPolicyResolutionMode } from './settings/realTypes'
import {
	normalizeRequestExpirationDraftValue,
	type RequestExpirationDraftValue,
} from './settings/expiration-rules/model'
import {
	normalizeSigningExecutionSettings,
	normalizeWorkerConfig,
	resolveSigningMode,
	serializeWorkerConfig,
	type SigningExecutionSettingsValue,
} from './settings/signing-mode/model'
import {
	normalizeSignatureStampDraftValue,
	resolveCollectMetadataValue,
} from './settings/signature-text/model'
import { canRenderWorkbenchPolicyForGroupAdmin } from '../../Preferences/personalPreferenceVisibility'
import { usePoliciesStore } from '../../../store/policies'
import type { EffectivePolicyState, EffectivePolicyValue } from '../../../types/index'
import logger from '../../../logger.js'

type PolicyScope = 'system' | 'group' | 'user'
type PolicyResolutionMode = RealPolicyResolutionMode

interface PolicyImpactPreview {
	groupCount?: number
	userCount?: number
	activeChildRules?: number
	blockedChildRules?: number
}

interface PolicyStickySummary {
	currentBaseValue: string
	baseSource: string
	configurableLayers: string
	platformFallback: string
	resolutionMode: PolicyResolutionMode
	activeGroupExceptions: number
	activeUserExceptions: number
	activeBlockCount: number
}

interface PolicyRuleRecord {
	id: string
	scope: PolicyScope
	targetId: string | null
	allowChildOverride: boolean
	value: EffectivePolicyValue
	canRemove?: boolean
}

interface PersistedSystemPolicyRecord {
	scope?: string | null
	value?: EffectivePolicyValue | null
	allowChildOverride?: boolean
}

interface PolicyEditorDraft {
	scope: PolicyScope
	ruleId: string | null
	targetIds: string[]
	value: EffectivePolicyValue
	allowChildOverride: boolean
}

interface PolicySettingSummary {
	key: string
	title: string
	context?: string
	description: string
	defaultSummary: string
	groupCount: number
	userCount: number
	everyoneCount: number
}

interface PolicyTargetOption {
	id: string
	displayName: string
	subname?: string
	user?: string
	isNoUser?: boolean
}

interface GroupDetailsResponse {
	ocs?: {
		data?: {
			groups?: Array<{
				id: string
				displayname?: string
				usercount?: number
			}>
		}
	}
}

interface GroupListResponse {
	ocs?: {
		data?: {
			groups?: string[]
		}
	}
}

interface UserDetailsRecord {
	id: string
	displayname?: string
	'display-name'?: string
	email?: string
}

interface UserDetailsResponse {
	ocs?: {
		data?: {
			users?: Record<string, UserDetailsRecord | Record<string, unknown> | string>
		}
	}
}

function isUserDetailsRecord(candidate: unknown): candidate is UserDetailsRecord {
	if (!candidate || typeof candidate !== 'object') {
		return false
	}

	const record = candidate as Record<string, unknown>
	if (typeof record.id !== 'string' || record.id.length === 0) {
		return false
	}

	// Defensive filtering: if backend response includes mixed entities,
	// keep only user-like entries for the user rule target picker.
	if ('usercount' in record || record.isNoUser === true) {
		return false
	}

	return true
}

function inferSystemAllowOverride(policy: { allowedValues?: unknown[] } | null): boolean {
	if (!policy || !Array.isArray(policy.allowedValues)) {
		return true
	}

	// When lower layers are locked, backend narrows allowedValues to a single value.
	return policy.allowedValues.length !== 1
}

function toDraftSnapshot(draft: PolicyEditorDraft | null): string {
	if (!draft) {
		return ''
	}

	return JSON.stringify({
		scope: draft.scope,
		ruleId: draft.ruleId,
		targetIds: [...draft.targetIds].sort(),
		value: draft.value,
		allowChildOverride: draft.allowChildOverride,
	})
}

const REQUEST_EXPIRATION_POLICY_KEY = 'maximum_validity'
const REQUEST_EXPIRATION_RENEWAL_KEY = 'renewal_interval'
const SIGNING_EXECUTION_POLICY_KEY = 'signing_mode'
const SIGNING_EXECUTION_WORKER_KEY = 'worker_config'
const SIGNATURE_STAMP_POLICY_KEY = 'signature_stamp'
const COLLECT_METADATA_POLICY_KEY = 'collect_metadata'

function isRequestExpirationPolicyKey(policyKey: string): boolean {
	return policyKey === REQUEST_EXPIRATION_POLICY_KEY
}

function isUnifiedSigningExecutionPolicyKey(policyKey: string): boolean {
	return policyKey === SIGNING_EXECUTION_POLICY_KEY
}

function isSignatureStampPolicyKey(policyKey: string): boolean {
	return policyKey === SIGNATURE_STAMP_POLICY_KEY
}

function buildRequestExpirationValue(
	maximumValidity: EffectivePolicyValue | undefined,
	renewalInterval: EffectivePolicyValue | undefined,
): RequestExpirationDraftValue {
	const normalizedMaximum = normalizeRequestExpirationDraftValue(maximumValidity ?? null)
	const normalizedRenewal = normalizeRequestExpirationDraftValue({
		maximumValidity: 0,
		renewalInterval: renewalInterval ?? null,
	})

	return {
		maximumValidity: normalizedMaximum.maximumValidity,
		renewalInterval: normalizedRenewal.renewalInterval,
	}
}

function buildSigningExecutionValue(
	signingMode: EffectivePolicyValue | undefined,
	workerConfig: EffectivePolicyValue | undefined,
): SigningExecutionSettingsValue {
	const normalizedMode = normalizeSigningExecutionSettings(signingMode ?? null)
	const normalizedWorker = normalizeWorkerConfig(workerConfig ?? null)

	return {
		signingMode: resolveSigningMode(normalizedMode.signingMode),
		workerType: normalizedWorker.workerType,
		parallelWorkers: normalizedWorker.parallelWorkers,
	}
}

function buildSignatureStampDraftValue(
	signatureStampValue: EffectivePolicyValue | undefined,
	collectMetadataValue: EffectivePolicyValue | undefined,
) {
	return normalizeSignatureStampDraftValue(
		signatureStampValue,
		resolveCollectMetadataValue(collectMetadataValue, false),
	)
}

export function createRealPolicyWorkbenchState() {
	const policiesStore = usePoliciesStore()
	const currentUser = getCurrentUser()
	const isInstanceAdmin = currentUser?.isAdmin === true
	const config = loadState<{ can_manage_group_policies?: boolean, manageable_policy_group_ids?: string[] }>('libresign', 'config', {})
	const manageablePolicyGroupIds = new Set(
		Array.isArray(config.manageable_policy_group_ids)
			? config.manageable_policy_group_ids.filter((groupId): groupId is string => typeof groupId === 'string' && groupId.trim().length > 0)
			: [],
	)
	const initialViewMode: 'system-admin' | 'group-admin' = currentUser?.isAdmin
		? 'system-admin'
		: config.can_manage_group_policies
			? 'group-admin'
			: 'system-admin'
	const viewMode = ref<'system-admin' | 'group-admin'>(initialViewMode)
	const activeSettingKey = ref<string | null>(null)
	const editorDraft = ref<PolicyEditorDraft | null>(null)
	const editorMode = ref<'create' | 'edit' | null>(null)
	const highlightedRuleId = ref<string | null>(null)
	const duplicateMessage = ref<string | null>(null)
	const nextRuleNumber = ref(1)

	const groupRules = ref<PolicyRuleRecord[]>([])
	const userRules = ref<PolicyRuleRecord[]>([])
	const explicitSystemRule = ref<PolicyRuleRecord | null>(null)
	const hydratedRuleCounts = ref<Record<string, { groupCount: number, userCount: number, everyoneCount: number }>>({})

	const groups = ref<PolicyTargetOption[]>([])
	const users = ref<PolicyTargetOption[]>([])
	const canManageGroups = ref<boolean | null>(null)
	const loadingTargets = ref(false)
	const rulesLoading = ref(false)
	const hydratePersistedRulesRequestId = ref(0)
	const editorInitialSnapshot = ref('')
	const draftTouchVersion = ref(0)
	const editorInitialTouchVersion = ref(0)

	function filterManageableGroupTargets(targets: PolicyTargetOption[]): PolicyTargetOption[] {
		if (isInstanceAdmin || manageablePolicyGroupIds.size === 0) {
			return targets
		}

		return targets.filter((target) => manageablePolicyGroupIds.has(target.id))
	}

	// Hide delegated access seed rules from group-admin summaries and CRUD state.
	function shouldHideGroupRuleFromGroupAdmin(rule: PolicyRuleRecord, policyKey: string | null = activeSettingKey.value): boolean {
		if (viewMode.value !== 'group-admin' || rule.canRemove !== false || !policyKey) {
			return false
		}

		const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
		const policy = policiesStore.getPolicy(policyKey)
		return definition?.groupAdminBehavior?.hideNonRemovableGroupRules?.(policy as EffectivePolicyState | null) ?? false
	}

	// Count only the group rules the current actor is allowed to see for a policy.
	function countVisibleGroupRulesForPolicy(policyKey: string, rules: PolicyRuleRecord[]): number {
		return rules.filter((rule) => !shouldHideGroupRuleFromGroupAdmin(rule, policyKey)).length
	}

	// Prefer hydrated visible counts for delegated request-access summaries after opening a setting.
	function resolveSummaryGroupCount(policyKey: string, groupCount: number, cachedGroupCount: number | undefined, isActiveSetting: boolean): number {
		if (isActiveSetting) {
			return countVisibleGroupRulesForPolicy(policyKey, groupRules.value)
		}

		const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
		if (
			viewMode.value === 'group-admin'
			&& definition?.groupAdminBehavior?.preferHydratedVisibleGroupCount === true
			&& typeof cachedGroupCount === 'number'
		) {
			return cachedGroupCount
		}

		return Math.max(groupCount, cachedGroupCount ?? 0)
	}

	const visibleSettingSummaries = computed<PolicySettingSummary[]>(() => {
		const isGroupAdminMode = viewMode.value === 'group-admin'

		return Object.values(realDefinitions)
			.filter((definition) => {
				if (viewMode.value === 'group-admin' && definition.visibleInGroupAdmin === false) {
					return false
				}

				return true
			})
			.map((definition) => {
				const policy = policiesStore.getPolicy(definition.key)
				const isRequestExpiration = isRequestExpirationPolicyKey(definition.key)
				const isUnifiedSigningExecution = isUnifiedSigningExecutionPolicyKey(definition.key)
				const renewalPolicy = isRequestExpiration
					? policiesStore.getPolicy(REQUEST_EXPIRATION_RENEWAL_KEY)
					: null
				const workerPolicy = isUnifiedSigningExecution
					? policiesStore.getPolicy(SIGNING_EXECUTION_WORKER_KEY)
					: null
				const hasEffectiveValue = isRequestExpiration
					? (
						(policy?.effectiveValue !== null && policy?.effectiveValue !== undefined)
						|| (renewalPolicy?.effectiveValue !== null && renewalPolicy?.effectiveValue !== undefined)
					)
					: isUnifiedSigningExecution
						? (
							(policy?.effectiveValue !== null && policy?.effectiveValue !== undefined)
							|| (workerPolicy?.effectiveValue !== null && workerPolicy?.effectiveValue !== undefined)
						)
						: (policy?.effectiveValue !== null && policy?.effectiveValue !== undefined)
				const isActiveSetting = activeSettingKey.value === definition.key
				const summaryValue = isRequestExpiration
					? buildRequestExpirationValue(policy?.effectiveValue, renewalPolicy?.effectiveValue)
					: isUnifiedSigningExecution
						? buildSigningExecutionValue(policy?.effectiveValue, workerPolicy?.effectiveValue)
						: policy?.effectiveValue
				const groupCount = isRequestExpiration
					? Math.max(policy?.groupCount ?? 0, renewalPolicy?.groupCount ?? 0)
					: isUnifiedSigningExecution
						? Math.max(policy?.groupCount ?? 0, workerPolicy?.groupCount ?? 0)
						: (policy?.groupCount ?? 0)
				const userCount = isRequestExpiration
					? Math.max(policy?.userCount ?? 0, renewalPolicy?.userCount ?? 0)
					: isUnifiedSigningExecution
						? Math.max(policy?.userCount ?? 0, workerPolicy?.userCount ?? 0)
						: (policy?.userCount ?? 0)
				const cachedCounts = hydratedRuleCounts.value[definition.key]

				return {
					key: definition.key,
					title: definition.title,
					context: definition.context,
					description: definition.description,
					defaultSummary: hasEffectiveValue && summaryValue !== null && summaryValue !== undefined
						? (typeof definition.summarizeValue === 'function'
							? definition.summarizeValue(summaryValue)
							: String(summaryValue))
						// TRANSLATORS Fallback shown when policy has no configured value in current scope chain.
						: t('libresign', 'Not configured'),
					groupCount: resolveSummaryGroupCount(definition.key, groupCount, cachedCounts?.groupCount, isActiveSetting),
					userCount: isActiveSetting
						? userRules.value.length
						: Math.max(userCount, cachedCounts?.userCount ?? 0),
					everyoneCount: isActiveSetting
						? (explicitSystemRule.value ? 1 : 0)
						: Math.max(policy?.everyoneCount ?? 0, cachedCounts?.everyoneCount ?? 0),
				}
			})
			.filter((summary) => {
				if (!isGroupAdminMode) {
					return true
				}

				const policy = policiesStore.getPolicy(summary.key)
				return canRenderWorkbenchPolicyForGroupAdmin(summary.key, policy as EffectivePolicyState | null)
			})
	})

	const activeDefinition = computed(() => {
		if (!activeSettingKey.value) {
			return null
		}

		return realDefinitions[activeSettingKey.value as keyof typeof realDefinitions] || null
	})

	const activePolicyState = computed(() => {
		if (!activeDefinition.value) {
			return null
		}

		return policiesStore.getPolicy(activeDefinition.value.key)
	})

	const inheritedSystemRule = computed<PolicyRuleRecord | null>(() => {
		if (!activeDefinition.value) {
			return null
		}

		const policy = activePolicyState.value
		if (!policy || policy.effectiveValue === null || policy.effectiveValue === undefined) {
			return explicitSystemRule.value
		}

		const sourceScope = policy.sourceScope
		if (sourceScope === 'group' || sourceScope === 'user' || sourceScope === 'user_policy') {
			return explicitSystemRule.value
		}

		if (sourceScope === 'system') {
			const value = isRequestExpirationPolicyKey(activeDefinition.value.key)
				? buildRequestExpirationValue(
					policy.effectiveValue,
					policiesStore.getPolicy(REQUEST_EXPIRATION_RENEWAL_KEY)?.effectiveValue,
				)
				: isUnifiedSigningExecutionPolicyKey(activeDefinition.value.key)
					? buildSigningExecutionValue(
						policy.effectiveValue,
						policiesStore.getPolicy(SIGNING_EXECUTION_WORKER_KEY)?.effectiveValue,
					)
					: policy.effectiveValue

			return {
				id: 'system-inherited-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: inferSystemAllowOverride(policy),
				value,
			}
		}

		if (explicitSystemRule.value !== null) {
			return explicitSystemRule.value
		}

		const explicitValue = isRequestExpirationPolicyKey(activeDefinition.value.key)
			? buildRequestExpirationValue(
				policy.effectiveValue,
				policiesStore.getPolicy(REQUEST_EXPIRATION_RENEWAL_KEY)?.effectiveValue,
			)
			: isUnifiedSigningExecutionPolicyKey(activeDefinition.value.key)
				? buildSigningExecutionValue(
					policy.effectiveValue,
					policiesStore.getPolicy(SIGNING_EXECUTION_WORKER_KEY)?.effectiveValue,
				)
				: policy.effectiveValue

		return {
			id: 'system-default',
			scope: 'system',
			targetId: null,
			allowChildOverride: inferSystemAllowOverride(policy),
			value: explicitValue,
		}
	})

	const policyResolutionMode = computed<PolicyResolutionMode>(() => {
		if (!activeDefinition.value) {
			return 'precedence'
		}

		return activeDefinition.value.resolutionMode
	})

	const systemDefaultRule = computed<PolicyRuleRecord | null>(() => {
		if (!activeDefinition.value) {
			return null
		}

		const policy = activePolicyState.value
		const fallbackValue = isRequestExpirationPolicyKey(activeDefinition.value.key)
			? buildRequestExpirationValue(
				activeDefinition.value.getFallbackSystemDefault(policy?.effectiveValue, policy?.sourceScope, policy),
				policiesStore.getPolicy(REQUEST_EXPIRATION_RENEWAL_KEY)?.effectiveValue,
			)
			: isUnifiedSigningExecutionPolicyKey(activeDefinition.value.key)
				? buildSigningExecutionValue(
					activeDefinition.value.getFallbackSystemDefault(policy?.effectiveValue, policy?.sourceScope, policy),
					policiesStore.getPolicy(SIGNING_EXECUTION_WORKER_KEY)?.effectiveValue,
				)
				: activeDefinition.value.getFallbackSystemDefault(
					policy?.effectiveValue,
					policy?.sourceScope,
					policy,
				)

		if (fallbackValue === null || fallbackValue === undefined) {
			return null
		}

		return {
			id: 'policy-system-default',
			scope: 'system',
			targetId: null,
			allowChildOverride: true,
			value: fallbackValue,
		}
	})

	const hasGlobalDefault = computed(() => {
		if (explicitSystemRule.value !== null) {
			return true
		}

		const policy = activePolicyState.value
		if (!policy) {
			return false
		}

		if (!policy.sourceScope) {
			return inheritedSystemRule.value !== null
		}

		return policy.sourceScope === 'global'
	})

	const effectiveSource = computed(() => {
		const sourceScope = activePolicyState.value?.sourceScope
		if (sourceScope === 'system') {
			return 'system'
		}

		if (sourceScope === 'group' || sourceScope === 'user') {
			return sourceScope
		}

		if (sourceScope === 'user_policy') {
			return 'user'
		}

		return hasGlobalDefault.value ? 'global' : 'system'
	})

	const summary = computed<PolicyStickySummary | null>(() => {
		if (!activeDefinition.value) {
			return null
		}

		const fallbackLabel = systemDefaultRule.value
			? activeDefinition.value.summarizeValue(systemDefaultRule.value.value)
			: t('libresign', 'Not configured')
		const currentBaseValue = inheritedSystemRule.value
			? activeDefinition.value.summarizeValue(inheritedSystemRule.value.value)
			: fallbackLabel

		const activePolicyKey = activeDefinition.value.key
		const visibleGroupRulesForSummary = groupRules.value.filter((rule) => !shouldHideGroupRuleFromGroupAdmin(rule, activePolicyKey))
		const activeGroupExceptions = visibleGroupRulesForSummary.length
		const activeUserExceptions = userRules.value.length
		const activeBlockCount = [
			inheritedSystemRule.value?.allowChildOverride === false ? 1 : 0,
			...visibleGroupRulesForSummary.map((rule) => rule.allowChildOverride ? 0 : 1),
		].reduce((sum, count) => sum + count, 0)

		const baseSource = hasGlobalDefault.value
			// TRANSLATORS Label for base value source when inherited from platform-wide default policy.
			? t('libresign', 'Global default')
			// TRANSLATORS Label for base value source when coming from LibreSign system-level default.
			: t('libresign', 'System default')

		return {
			currentBaseValue,
			baseSource,
			// TRANSLATORS Scope precedence order for policy inheritance.
			configurableLayers: t('libresign', 'Default > Group > Account'),
			platformFallback: fallbackLabel,
			resolutionMode: policyResolutionMode.value,
			activeGroupExceptions,
			activeUserExceptions,
			activeBlockCount,
		}
	})

	const createGroupOverrideDisabledReason = computed(() => {
		if (isInstanceAdmin) {
			return null
		}

		if (activePolicyState.value?.editableByCurrentActor === false) {
			return t('libresign', 'Blocked by the global default.')
		}

		if (inheritedSystemRule.value?.allowChildOverride === false) {
			return t('libresign', 'Blocked by the global default.')
		}

		return null
	})

	const canCreateUserOverridesAtCurrentScope = computed(() => {
		if (!activeDefinition.value) {
			return false
		}

		return canRenderWorkbenchPolicyForGroupAdmin(
			activeDefinition.value.key,
			activePolicyState.value as EffectivePolicyState | null,
		)
	})

	const createUserOverrideDisabledReason = computed(() => {
		if (viewMode.value === 'system-admin') {
			return null
		}

		if (!canCreateUserOverridesAtCurrentScope.value) {
			return t('libresign', 'Blocked by the global default.')
		}

		if (inheritedSystemRule.value?.allowChildOverride === false) {
			return t('libresign', 'Blocked by a locked default rule.')
		}

		const blockingGroup = groupRules.value.find((rule) => !rule.allowChildOverride)
		if (blockingGroup?.targetId) {
			return t('libresign', 'Blocked by the {group} group rule.', {
				group: resolveTargetLabel('group', blockingGroup.targetId),
			})
		}

		return null
	})

	const visibleGroupRules = computed<PolicyRuleRecord[]>(() => {
		return groupRules.value.filter((rule) => !shouldHideGroupRuleFromGroupAdmin(rule))
	})
	const visibleUserRules = computed<PolicyRuleRecord[]>(() => userRules.value)

	/** Cache summary counters using the same visibility rules shown in the UI. */
	function cacheCurrentRuleCounts(policyKey: string) {
		cacheRuleCountsWithEveryone(
			policyKey,
			countVisibleGroupRulesForPolicy(policyKey, groupRules.value),
			userRules.value.length,
			explicitSystemRule.value ? 1 : 0,
		)
	}

	function filterTargetsForCreate(scope: 'group' | 'user', targets: PolicyTargetOption[]): PolicyTargetOption[] {
		if (!editorDraft.value || editorDraft.value.scope !== scope || editorMode.value !== 'create') {
			return targets
		}

		const selectedTargetIds = new Set(editorDraft.value.targetIds)
		const assignedTargetIds = new Set(
			(scope === 'group' ? groupRules.value : userRules.value)
				.map((rule) => rule.targetId)
				.filter((targetId): targetId is string => !!targetId),
		)

		return targets.filter((target) => {
			return selectedTargetIds.has(target.id) || !assignedTargetIds.has(target.id)
		})
	}

	const availableTargets = computed<PolicyTargetOption[]>(() => {
		if (!editorDraft.value) {
			return []
		}

		if (editorDraft.value.scope === 'group') {
			return filterTargetsForCreate('group', groups.value)
		}

		if (editorDraft.value.scope === 'user') {
			return filterTargetsForCreate('user', users.value)
		}

		return []
	})

	const draftTargetLabel = computed(() => {
		if (!editorDraft.value || editorDraft.value.targetIds.length === 0) {
			return null
		}

		const labels = editorDraft.value.targetIds
			.map((targetId) => availableTargets.value.find((target) => target.id === targetId)?.displayName ?? targetId)

		if (labels.length === 1) {
			return labels[0]
		}

		return t('libresign', '{count} targets selected', { count: String(labels.length) })
	})

	const isDraftDirty = computed(() => {
		if (!editorDraft.value) {
			return false
		}

		return toDraftSnapshot(editorDraft.value) !== editorInitialSnapshot.value
			|| draftTouchVersion.value !== editorInitialTouchVersion.value
	})

	function hasSelectableDraftValue(draft: PolicyEditorDraft) {
		if (!activeDefinition.value) {
			return false
		}

		return activeDefinition.value.hasSelectableDraftValue(draft.value)
	}

	function isScopeSupported(scope: PolicyScope): boolean {
		if (!activeDefinition.value?.supportedScopes || activeDefinition.value.supportedScopes.length === 0) {
			return true
		}

		return activeDefinition.value.supportedScopes.includes(scope)
	}

	function isAllowOverrideMutable(scope: PolicyScope): boolean {
		if (!activeDefinition.value) {
			return true
		}

		const normalizedTrue = activeDefinition.value.normalizeAllowChildOverride(scope, true)
		const normalizedFalse = activeDefinition.value.normalizeAllowChildOverride(scope, false)

		return normalizedTrue !== normalizedFalse
	}

	const canSaveDraft = computed(() => {
		if (!editorDraft.value) {
			return false
		}

		if (!hasSelectableDraftValue(editorDraft.value)) {
			return false
		}

		if (editorDraft.value.scope !== 'system' && editorDraft.value.targetIds.length === 0) {
			return false
		}

		if (editorMode.value === 'create' && editorDraft.value.scope !== 'system') {
			return true
		}

		if (
			editorMode.value === 'create'
			&& editorDraft.value.scope === 'system'
		) {
			const baselineForSave = inheritedSystemRule.value?.value ?? systemDefaultRule.value?.value
			if (!shouldUseBaselineForCreate('system', baselineForSave)) {
				return true
			}
		}

		if (!isDraftDirty.value
			&& editorMode.value === 'create'
			&& editorDraft.value.scope === 'system'
			&& !isAllowOverrideMutable('system')) {
			return true
		}

		return isDraftDirty.value
	})

	function cacheRuleCountsWithEveryone(policyKey: string, groupCount: number, userCount: number, everyoneCount: number) {
		hydratedRuleCounts.value = {
			...hydratedRuleCounts.value,
			[policyKey]: {
				groupCount,
				userCount,
				everyoneCount,
			},
		}
	}

	function commitHydratedRules(policyKey: string, nextGroupRules: PolicyRuleRecord[], nextUserRules: PolicyRuleRecord[]) {
		groupRules.value = nextGroupRules
		userRules.value = nextUserRules
		nextRuleNumber.value = groupRules.value.length + userRules.value.length + 1
		cacheCurrentRuleCounts(policyKey)
	}

	function isHydrationStale(requestId: number, policyKey: string): boolean {
		return requestId !== hydratePersistedRulesRequestId.value || activeSettingKey.value !== policyKey
	}

	function finishHydration(requestId: number, policyKey: string) {
		if (!isHydrationStale(requestId, policyKey)) {
			rulesLoading.value = false
		}
	}

	function cancelPendingHydration() {
		hydratePersistedRulesRequestId.value += 1
		rulesLoading.value = false
	}

	function applyRequestExpirationHydration(
		policyKey: string,
		persistedSystemPolicy: PersistedSystemPolicyRecord | null,
		renewalSystemPolicy: PersistedSystemPolicyRecord | null,
		persistedGroupPolicies: PolicyRuleRecord[],
		renewalGroupPolicies: PolicyRuleRecord[],
		persistedUserPolicies: PolicyRuleRecord[],
		renewalUserPolicies: PolicyRuleRecord[],
	) {
		const maxHasValue = persistedSystemPolicy?.value !== null && persistedSystemPolicy?.value !== undefined
		const renewalHasValue = renewalSystemPolicy?.value !== null && renewalSystemPolicy?.value !== undefined
		explicitSystemRule.value = (persistedSystemPolicy?.scope === 'global' || renewalSystemPolicy?.scope === 'global') && (maxHasValue || renewalHasValue)
			? {
				id: 'system-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: persistedSystemPolicy?.allowChildOverride ?? renewalSystemPolicy?.allowChildOverride ?? true,
				value: buildRequestExpirationValue(
					persistedSystemPolicy?.value,
					renewalSystemPolicy?.value,
				),
			}
			: null

		const mergedGroupRules = new Map<string, PolicyRuleRecord>()
		for (const rule of persistedGroupPolicies) {
			if (!rule.targetId) {
				continue
			}

			mergedGroupRules.set(rule.targetId, {
				id: rule.id,
				scope: 'group',
				targetId: rule.targetId,
				allowChildOverride: rule.allowChildOverride,
				value: buildRequestExpirationValue(rule.value, null),
				canRemove: rule.canRemove,
			})
		}

		for (const rule of renewalGroupPolicies) {
			if (!rule.targetId) {
				continue
			}

			const existing = mergedGroupRules.get(rule.targetId)
			mergedGroupRules.set(rule.targetId, {
				id: existing?.id ?? rule.id,
				scope: 'group',
				targetId: rule.targetId,
				allowChildOverride: existing?.allowChildOverride ?? rule.allowChildOverride,
				value: buildRequestExpirationValue(existing?.value, rule.value),
				canRemove: existing?.canRemove ?? rule.canRemove,
			})
		}

		const mergedUserRules = new Map<string, PolicyRuleRecord>()
		for (const rule of persistedUserPolicies) {
			if (!rule.targetId) {
				continue
			}

			mergedUserRules.set(rule.targetId, {
				id: rule.id,
				scope: 'user',
				targetId: rule.targetId,
				allowChildOverride: rule.allowChildOverride,
				value: buildRequestExpirationValue(rule.value, null),
			})
		}

		for (const rule of renewalUserPolicies) {
			if (!rule.targetId) {
				continue
			}

			const existing = mergedUserRules.get(rule.targetId)
			mergedUserRules.set(rule.targetId, {
				id: existing?.id ?? rule.id,
				scope: 'user',
				targetId: rule.targetId,
				allowChildOverride: existing?.allowChildOverride ?? rule.allowChildOverride,
				value: buildRequestExpirationValue(existing?.value, rule.value),
			})
		}

		commitHydratedRules(policyKey, Array.from(mergedGroupRules.values()), Array.from(mergedUserRules.values()))
	}

	function applySigningExecutionHydration(
		policyKey: string,
		persistedSystemPolicy: PersistedSystemPolicyRecord | null,
		workerSystemPolicy: PersistedSystemPolicyRecord | null,
		persistedGroupPolicies: PolicyRuleRecord[],
		workerGroupPolicies: PolicyRuleRecord[],
		persistedUserPolicies: PolicyRuleRecord[],
		workerUserPolicies: PolicyRuleRecord[],
	) {
		const modeHasValue = persistedSystemPolicy?.value !== null && persistedSystemPolicy?.value !== undefined
		const workerHasValue = workerSystemPolicy?.value !== null && workerSystemPolicy?.value !== undefined
		explicitSystemRule.value = (persistedSystemPolicy?.scope === 'global' || workerSystemPolicy?.scope === 'global') && (modeHasValue || workerHasValue)
			? {
				id: 'system-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: persistedSystemPolicy?.allowChildOverride ?? workerSystemPolicy?.allowChildOverride ?? true,
				value: buildSigningExecutionValue(
					persistedSystemPolicy?.value,
					workerSystemPolicy?.value,
				),
			}
			: null

		const mergedGroupRules = new Map<string, PolicyRuleRecord>()
		for (const rule of persistedGroupPolicies) {
			if (!rule.targetId) {
				continue
			}

			mergedGroupRules.set(rule.targetId, {
				id: rule.id,
				scope: 'group',
				targetId: rule.targetId,
				allowChildOverride: rule.allowChildOverride,
				value: buildSigningExecutionValue(rule.value, null),
				canRemove: rule.canRemove,
			})
		}

		for (const rule of workerGroupPolicies) {
			if (!rule.targetId) {
				continue
			}

			const existing = mergedGroupRules.get(rule.targetId)
			mergedGroupRules.set(rule.targetId, {
				id: existing?.id ?? rule.id,
				scope: 'group',
				targetId: rule.targetId,
				allowChildOverride: existing?.allowChildOverride ?? rule.allowChildOverride,
				value: buildSigningExecutionValue(existing?.value, rule.value),
				canRemove: existing?.canRemove ?? rule.canRemove,
			})
		}

		const mergedUserRules = new Map<string, PolicyRuleRecord>()
		for (const rule of persistedUserPolicies) {
			if (!rule.targetId) {
				continue
			}

			mergedUserRules.set(rule.targetId, {
				id: rule.id,
				scope: 'user',
				targetId: rule.targetId,
				allowChildOverride: rule.allowChildOverride,
				value: buildSigningExecutionValue(rule.value, null),
			})
		}

		for (const rule of workerUserPolicies) {
			if (!rule.targetId) {
				continue
			}

			const existing = mergedUserRules.get(rule.targetId)
			mergedUserRules.set(rule.targetId, {
				id: existing?.id ?? rule.id,
				scope: 'user',
				targetId: rule.targetId,
				allowChildOverride: existing?.allowChildOverride ?? rule.allowChildOverride,
				value: buildSigningExecutionValue(existing?.value, rule.value),
			})
		}

		commitHydratedRules(policyKey, Array.from(mergedGroupRules.values()), Array.from(mergedUserRules.values()))
	}

	function applySignatureStampHydration(
		policyKey: string,
		persistedSystemPolicy: PersistedSystemPolicyRecord | null,
		collectMetadataSystemPolicy: PersistedSystemPolicyRecord | null,
		persistedGroupPolicies: PolicyRuleRecord[],
		collectMetadataGroupPolicies: PolicyRuleRecord[],
		persistedUserPolicies: PolicyRuleRecord[],
		collectMetadataUserPolicies: PolicyRuleRecord[],
	) {
		explicitSystemRule.value = (persistedSystemPolicy?.scope === 'global' || collectMetadataSystemPolicy?.scope === 'global')
			&& (persistedSystemPolicy?.value !== null && persistedSystemPolicy?.value !== undefined)
			? {
				id: 'system-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: persistedSystemPolicy?.allowChildOverride ?? collectMetadataSystemPolicy?.allowChildOverride ?? true,
				value: buildSignatureStampDraftValue(
					persistedSystemPolicy?.value,
					collectMetadataSystemPolicy?.value,
				),
			}
			: null

		const mergedGroupRules = new Map<string, PolicyRuleRecord>()
		for (const rule of persistedGroupPolicies) {
			if (!rule.targetId) {
				continue
			}

			const collectMetadataRule = collectMetadataGroupPolicies.find((metadataRule) => metadataRule.targetId === rule.targetId)
			mergedGroupRules.set(rule.targetId, {
				id: rule.id,
				scope: 'group',
				targetId: rule.targetId,
				allowChildOverride: rule.allowChildOverride,
				value: buildSignatureStampDraftValue(rule.value, collectMetadataRule?.value),
				canRemove: rule.canRemove,
			})
		}

		const mergedUserRules = new Map<string, PolicyRuleRecord>()
		for (const rule of persistedUserPolicies) {
			if (!rule.targetId) {
				continue
			}

			const collectMetadataRule = collectMetadataUserPolicies.find((metadataRule) => metadataRule.targetId === rule.targetId)
			mergedUserRules.set(rule.targetId, {
				id: rule.id,
				scope: 'user',
				targetId: rule.targetId,
				allowChildOverride: rule.allowChildOverride,
				value: buildSignatureStampDraftValue(rule.value, collectMetadataRule?.value),
			})
		}

		commitHydratedRules(policyKey, Array.from(mergedGroupRules.values()), Array.from(mergedUserRules.values()))
	}

	function applyDefaultHydration(
		policyKey: string,
		persistedSystemPolicy: PersistedSystemPolicyRecord | null,
		persistedGroupPolicies: PolicyRuleRecord[],
		persistedUserPolicies: PolicyRuleRecord[],
	) {
		explicitSystemRule.value = persistedSystemPolicy?.scope === 'global' && persistedSystemPolicy.value !== null && persistedSystemPolicy.value !== undefined
			? {
				id: 'system-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: persistedSystemPolicy.allowChildOverride ?? true,
				value: persistedSystemPolicy.value,
			}
			: null

		commitHydratedRules(policyKey, persistedGroupPolicies, persistedUserPolicies)
	}

	async function hydratePersistedRules(policyKey: string) {
		const currentRequestId = hydratePersistedRulesRequestId.value + 1
		hydratePersistedRulesRequestId.value = currentRequestId
		rulesLoading.value = true
		const shouldMergeRequestExpiration = isRequestExpirationPolicyKey(policyKey)
		const shouldMergeSigningExecution = isUnifiedSigningExecutionPolicyKey(policyKey)
		const shouldMergeSignatureStamp = isSignatureStampPolicyKey(policyKey)

		const targetsPreloaded = await Promise.all([
			loadTargets('group', ''),
			loadTargets('user', ''),
		]).then(() => true).catch((error) => {
			logger.debug('Could not preload policy workbench targets', {
				error,
				policyKey,
			})
			finishHydration(currentRequestId, policyKey)
			return false
		})

		if (!targetsPreloaded) {
			return
		}

		if (isHydrationStale(currentRequestId, policyKey)) {
			finishHydration(currentRequestId, policyKey)
			return
		}

		const fetchSystemPolicySafe = async (key: string) => {
			return policiesStore.fetchSystemPolicy(key).catch((error) => {
				logger.debug('Could not load explicit system policy for workbench', {
					error,
					policyKey: key,
				})
				return null
			})
		}

		const fetchPersistedGroupRulesForKey = async (key: string): Promise<PolicyRuleRecord[]> => {
			if (typeof policiesStore.fetchGroupPoliciesByPolicyKey === 'function') {
				return policiesStore.fetchGroupPoliciesByPolicyKey(key).then((policies) => {
					return policies
						.filter((policy) => policy.value !== null && policy.value !== undefined)
						.map((policy) => ({
							id: `group-${policy.targetId}-persisted`,
							scope: 'group' as const,
							targetId: policy.targetId,
							allowChildOverride: policy.allowChildOverride,
							value: policy.value,
							canRemove: policy.deletableByCurrentActor,
						}))
				}).catch((error) => {
					logger.debug('Could not bulk load persisted group policies for workbench', {
						error,
						policyKey: key,
					})
					return []
				})
			}

			const records = await Promise.all(groups.value.map(async (group) => {
				try {
					const persistedPolicy = await policiesStore.fetchGroupPolicy(group.id, key)
					if (!persistedPolicy || persistedPolicy.value === null || persistedPolicy.value === undefined) {
						return null
					}

					return {
						id: `group-${group.id}-persisted`,
						scope: 'group' as const,
						targetId: group.id,
						allowChildOverride: persistedPolicy.allowChildOverride,
						value: persistedPolicy.value,
						canRemove: persistedPolicy.deletableByCurrentActor,
					}
				} catch (error) {
					logger.debug('Could not load persisted group policy for target', {
						error,
						policyKey: key,
						groupId: group.id,
					})
					return null
				}
			}))

			return records.filter((record): record is NonNullable<typeof record> => record !== null)
		}

		const fetchPersistedUserRulesForKey = async (key: string): Promise<PolicyRuleRecord[]> => {
			if (typeof policiesStore.fetchUserPoliciesByPolicyKey === 'function') {
				return policiesStore.fetchUserPoliciesByPolicyKey(key).then((policies) => {
					return policies
						.filter((policy) => policy.value !== null && policy.value !== undefined)
						.map((policy) => ({
							id: `user-${policy.targetId}-persisted`,
							scope: 'user' as const,
							targetId: policy.targetId,
							allowChildOverride: policy.allowChildOverride,
							value: policy.value,
						}))
				}).catch((error) => {
					logger.debug('Could not bulk load persisted user policies for workbench', {
						error,
						policyKey: key,
					})
					return []
				})
			}

			const records = await Promise.all(users.value.map(async (user) => {
				try {
					const persistedPolicy = await policiesStore.fetchUserPolicyForUser(user.id, key)
					if (!persistedPolicy || persistedPolicy.value === null || persistedPolicy.value === undefined) {
						return null
					}

					return {
						id: `user-${user.id}-persisted`,
						scope: 'user' as const,
						targetId: user.id,
						allowChildOverride: persistedPolicy.allowChildOverride,
						value: persistedPolicy.value,
					}
				} catch (error) {
					logger.debug('Could not load persisted user policy for target', {
						error,
						policyKey: key,
						userId: user.id,
					})
					return null
				}
			}))

			return records.filter((record): record is NonNullable<typeof record> => record !== null)
		}

		const hydratedPayload = await Promise.all([
			fetchSystemPolicySafe(policyKey),
			fetchPersistedGroupRulesForKey(policyKey),
			fetchPersistedUserRulesForKey(policyKey),
			shouldMergeRequestExpiration ? fetchSystemPolicySafe(REQUEST_EXPIRATION_RENEWAL_KEY) : Promise.resolve(null),
			shouldMergeRequestExpiration ? fetchPersistedGroupRulesForKey(REQUEST_EXPIRATION_RENEWAL_KEY) : Promise.resolve([]),
			shouldMergeRequestExpiration ? fetchPersistedUserRulesForKey(REQUEST_EXPIRATION_RENEWAL_KEY) : Promise.resolve([]),
			shouldMergeSigningExecution ? fetchSystemPolicySafe(SIGNING_EXECUTION_WORKER_KEY) : Promise.resolve(null),
			shouldMergeSigningExecution ? fetchPersistedGroupRulesForKey(SIGNING_EXECUTION_WORKER_KEY) : Promise.resolve([]),
			shouldMergeSigningExecution ? fetchPersistedUserRulesForKey(SIGNING_EXECUTION_WORKER_KEY) : Promise.resolve([]),
			shouldMergeSignatureStamp ? fetchSystemPolicySafe(COLLECT_METADATA_POLICY_KEY) : Promise.resolve(null),
			shouldMergeSignatureStamp ? fetchPersistedGroupRulesForKey(COLLECT_METADATA_POLICY_KEY) : Promise.resolve([]),
			shouldMergeSignatureStamp ? fetchPersistedUserRulesForKey(COLLECT_METADATA_POLICY_KEY) : Promise.resolve([]),
		]).catch((error) => {
			logger.debug('Could not hydrate persisted policy rules', {
				error,
				policyKey,
			})
			finishHydration(currentRequestId, policyKey)
			return null
		})

		if (!hydratedPayload) {
			return
		}

		if (isHydrationStale(currentRequestId, policyKey)) {
			finishHydration(currentRequestId, policyKey)
			return
		}

		const [persistedSystemPolicy, persistedGroupPolicies, persistedUserPolicies, renewalSystemPolicy, renewalGroupPolicies, renewalUserPolicies, workerSystemPolicy, workerGroupPolicies, workerUserPolicies, collectMetadataSystemPolicy, collectMetadataGroupPolicies, collectMetadataUserPolicies] = hydratedPayload

		if (shouldMergeRequestExpiration) {
			applyRequestExpirationHydration(
				policyKey,
				persistedSystemPolicy,
				renewalSystemPolicy,
				persistedGroupPolicies,
				renewalGroupPolicies,
				persistedUserPolicies,
				renewalUserPolicies,
			)
			finishHydration(currentRequestId, policyKey)
			return
		}

		if (shouldMergeSigningExecution) {
			applySigningExecutionHydration(
				policyKey,
				persistedSystemPolicy,
				workerSystemPolicy,
				persistedGroupPolicies,
				workerGroupPolicies,
				persistedUserPolicies,
				workerUserPolicies,
			)
			finishHydration(currentRequestId, policyKey)
			return
		}

		if (shouldMergeSignatureStamp) {
			applySignatureStampHydration(
				policyKey,
				persistedSystemPolicy,
				collectMetadataSystemPolicy,
				persistedGroupPolicies,
				collectMetadataGroupPolicies,
				persistedUserPolicies,
				collectMetadataUserPolicies,
			)
			finishHydration(currentRequestId, policyKey)
			return
		}

		applyDefaultHydration(
			policyKey,
			persistedSystemPolicy,
			persistedGroupPolicies,
			persistedUserPolicies,
		)
		finishHydration(currentRequestId, policyKey)
	}

	function openSetting(key: string) {
		activeSettingKey.value = key
		explicitSystemRule.value = null
		groupRules.value = []
		userRules.value = []
		void hydratePersistedRules(key)
	}

	function mergeSelectedTargets(scope: 'group' | 'user', fetchedTargets: PolicyTargetOption[]) {
		const existingTargets = scope === 'group' ? groups.value : users.value
		const selectedIds = editorDraft.value?.scope === scope ? editorDraft.value.targetIds : []
		const selectedTargets = existingTargets.filter((target) => {
			return selectedIds.includes(target.id) && !fetchedTargets.some((option) => option.id === target.id)
		})

		return [...selectedTargets, ...fetchedTargets]
	}

	async function fetchGroups(query = '', limit = 20, offset = 0): Promise<PolicyTargetOption[]> {
		if (isInstanceAdmin) {
			const { data } = await axios.get<GroupDetailsResponse>(generateOcsUrl('cloud/groups/details'), {
				params: {
					search: query,
					limit,
					offset,
				},
			})

			return filterManageableGroupTargets((data.ocs?.data?.groups ?? [])
				.map((group) => ({
					id: group.id,
					displayName: group.displayname || group.id,
					subname: typeof group.usercount === 'number'
						? t('libresign', '{count} members', { count: String(group.usercount) })
						: undefined,
					isNoUser: true,
				}))
				.sort((left, right) => left.displayName.localeCompare(right.displayName)))
		}

		const { data } = await axios.get<GroupListResponse>(generateOcsUrl('cloud/groups'), {
			params: {
				search: query,
				limit,
				offset,
			},
		})

		return filterManageableGroupTargets((data.ocs?.data?.groups ?? [])
			.map((groupId) => ({
				id: groupId,
				displayName: groupId,
				isNoUser: true,
			}))
			.sort((left, right) => left.displayName.localeCompare(right.displayName)))
	}

	async function fetchUsers(query = '', limit = 20, offset = 0): Promise<PolicyTargetOption[]> {
		const { data } = await axios.get<UserDetailsResponse>(generateOcsUrl('cloud/users/details'), {
			params: {
				search: query,
				limit,
				offset,
			},
		})

		return Object.values(data.ocs?.data?.users ?? {})
			.filter(isUserDetailsRecord)
			.map((user) => ({
				id: user.id,
				displayName: user['display-name'] || user.displayname || user.id,
				subname: user.email,
				user: user.id,
			}))
			.sort((left, right) => left.displayName.localeCompare(right.displayName))
	}

	async function fetchAllTargets(scope: 'group' | 'user'): Promise<PolicyTargetOption[]> {
		const pageSize = 20
		let offset = 0
		const aggregatedTargets: PolicyTargetOption[] = []
		const seenTargetIds = new Set<string>()

		for (let page = 0; page < 100; page += 1) {
			const pageTargets = scope === 'group'
				? await fetchGroups('', pageSize, offset)
				: await fetchUsers('', pageSize, offset)

			for (const target of pageTargets) {
				if (seenTargetIds.has(target.id)) {
					continue
				}

				seenTargetIds.add(target.id)
				aggregatedTargets.push(target)
			}

			if (pageTargets.length < pageSize) {
				break
			}

			offset += pageSize
		}

		return aggregatedTargets.sort((left, right) => left.displayName.localeCompare(right.displayName))
	}

	async function loadTargets(scope: 'group' | 'user', query = '', includeAll = false) {
		loadingTargets.value = true
		try {
			if (scope === 'group') {
				const fetchedGroups = includeAll && query === ''
					? await fetchAllTargets('group')
					: await fetchGroups(query)
				groups.value = mergeSelectedTargets('group', fetchedGroups)
				return
			}

			const fetchedUsers = includeAll && query === ''
				? await fetchAllTargets('user')
				: await fetchUsers(query)
			users.value = mergeSelectedTargets('user', fetchedUsers)
		} catch (error) {
			logger.debug('Could not load policy workbench targets', {
				error,
				scope,
				query,
			})
		} finally {
			loadingTargets.value = false
		}
	}

	function searchAvailableTargets(query: string) {
		const scope = editorDraft.value?.scope
		if (scope !== 'group' && scope !== 'user') {
			return
		}

		void loadTargets(scope, query)
	}

	async function probeGroupAccess() {
		if (isInstanceAdmin || viewMode.value !== 'group-admin') {
			return
		}

		if (manageablePolicyGroupIds.size > 0) {
			canManageGroups.value = true
			return
		}

		try {
			const result = await fetchGroups('', 1, 0)
			canManageGroups.value = result.length > 0
		} catch {
			canManageGroups.value = false
		}
	}

	function setViewMode(mode: 'system-admin' | 'group-admin') {
		viewMode.value = mode
	}

	function closeSetting() {
		activeSettingKey.value = null
		groupRules.value = []
		userRules.value = []
		explicitSystemRule.value = null
		editorDraft.value = null
		editorMode.value = null
		duplicateMessage.value = null
		rulesLoading.value = false
	}

	function resolveTargetLabel(scope: 'group' | 'user', targetId: string): string {
		const targets = scope === 'group' ? groups.value : users.value
		return targets.find((target) => target.id === targetId)?.displayName || targetId
	}

	function findRuleById(scope: PolicyScope, ruleId: string): PolicyRuleRecord | null {
		if (scope === 'group') {
			return groupRules.value.find((rule) => rule.id === ruleId) ?? null
		}

		if (scope === 'user') {
			return userRules.value.find((rule) => rule.id === ruleId) ?? null
		}

		return inheritedSystemRule.value?.id === ruleId ? inheritedSystemRule.value : null
	}

	function shouldUseBaselineForCreate(scope: PolicyScope, baselineValue: EffectivePolicyValue | null | undefined): baselineValue is EffectivePolicyValue {
		if (baselineValue === null || baselineValue === undefined) {
			return false
		}

		const seedable = activeDefinition.value?.isBaselineSeedable?.(baselineValue)
		if (scope === 'system') {
			return seedable ?? true
		}

		return seedable ?? false
	}

	function startEditor({ scope, ruleId }: { scope: PolicyScope, ruleId?: string }) {
		if (!activeDefinition.value) {
			return
		}

		if (!isScopeSupported(scope)) {
			duplicateMessage.value = t('libresign', 'This setting cannot be configured at this scope.')
			return
		}

		if (!ruleId && scope === 'group' && createGroupOverrideDisabledReason.value) {
			duplicateMessage.value = createGroupOverrideDisabledReason.value
			return
		}

		if (!ruleId && scope === 'user' && createUserOverrideDisabledReason.value) {
			duplicateMessage.value = createUserOverrideDisabledReason.value
			return
		}

		// Cancel any in-flight hydration result to avoid stale overwrite while editing.
		cancelPendingHydration()

		const isEdit = !!ruleId
		editorMode.value = isEdit ? 'edit' : 'create'
		duplicateMessage.value = null

		let value: EffectivePolicyValue = activeDefinition.value.createEmptyValue()
		let targetIds: string[] = []
		let allowChildOverride = activeDefinition.value.normalizeAllowChildOverride(scope, true)

		if (isEdit && ruleId) {
			const rule = findRuleById(scope, ruleId)
			if (rule) {
				value = activeDefinition.value.normalizeDraftValue(rule.value)
				allowChildOverride = activeDefinition.value.normalizeAllowChildOverride(scope, rule.allowChildOverride)
				targetIds = rule.targetId ? [rule.targetId] : []
			}
		} else if (scope === 'system') {
			const baselineRuleValue = inheritedSystemRule.value?.value ?? systemDefaultRule.value?.value
			if (shouldUseBaselineForCreate('system', baselineRuleValue)) {
				value = activeDefinition.value.normalizeDraftValue(baselineRuleValue)
			}
		} else if (scope === 'group') {
			const baselineRuleValue = inheritedSystemRule.value?.value ?? systemDefaultRule.value?.value
			if (shouldUseBaselineForCreate('group', baselineRuleValue)) {
				value = activeDefinition.value.normalizeDraftValue(baselineRuleValue)
			}
			targetIds = []
		} else if (scope === 'user') {
			const baselineRuleValue = inheritedSystemRule.value?.value ?? systemDefaultRule.value?.value
			if (shouldUseBaselineForCreate('user', baselineRuleValue)) {
				value = activeDefinition.value.normalizeDraftValue(baselineRuleValue)
			}
			targetIds = []
		}

		editorDraft.value = {
			scope,
			ruleId: ruleId || null,
			targetIds,
			value,
			allowChildOverride,
		}

		if (
			!ruleId
			&& !shouldUseBaselineForCreate(scope, editorDraft.value.value)
		) {
			editorDraft.value.value = activeDefinition.value.createEmptyValue()
		}

		if (isSignatureStampPolicyKey(activeDefinition.value.key)) {
			const fallbackCollectMetadataValue = isEdit && ruleId
				? undefined
				: policiesStore.getPolicy(COLLECT_METADATA_POLICY_KEY)?.effectiveValue
			editorDraft.value.value = buildSignatureStampDraftValue(
				editorDraft.value.value,
				fallbackCollectMetadataValue,
			)
		}

		editorInitialSnapshot.value = toDraftSnapshot(editorDraft.value)
		editorInitialTouchVersion.value = draftTouchVersion.value

		if (scope === 'group' || scope === 'user') {
			void loadTargets(scope)
		}
	}

	function cancelEditor() {
		editorDraft.value = null
		editorMode.value = null
		duplicateMessage.value = null
		editorInitialSnapshot.value = ''
		editorInitialTouchVersion.value = draftTouchVersion.value
	}

	function markDraftTouched() {
		if (!editorDraft.value) {
			return
		}

		draftTouchVersion.value += 1
	}

	function updateDraftValue(value: EffectivePolicyValue) {
		if (editorDraft.value) {
			editorDraft.value.value = value
		}
	}

	function updateDraftTargets(targetIds: string[]) {
		if (!editorDraft.value) {
			return
		}

		const normalizedTargetIds = Array.from(new Set(targetIds.filter(Boolean)))
		editorDraft.value.targetIds = normalizedTargetIds

		if (activeDefinition.value?.syncCreateDraftValueFromTargets && editorMode.value === 'create') {
			editorDraft.value.value = activeDefinition.value.syncCreateDraftValueFromTargets(
				editorDraft.value.scope,
				normalizedTargetIds,
				editorDraft.value.value,
			)
		}
	}

	function updateDraftTarget(targetId: string | null) {
		updateDraftTargets(targetId ? [targetId] : [])
	}

	function updateDraftAllowOverride(allowChildOverride: boolean) {
		if (editorDraft.value) {
			editorDraft.value.allowChildOverride = activeDefinition.value
				? activeDefinition.value.normalizeAllowChildOverride(editorDraft.value.scope, allowChildOverride)
				: allowChildOverride
		}
	}

	function upsertRule(ruleList: PolicyRuleRecord[], scope: 'group' | 'user', targetId: string, value: EffectivePolicyValue, allowChildOverride: boolean) {
		const existingRule = ruleList.find((rule) => rule.targetId === targetId)
		if (existingRule) {
			existingRule.value = value
			existingRule.allowChildOverride = allowChildOverride
			highlightedRuleId.value = existingRule.id
			return
		}

		const id = `${scope}-${targetId}-${String(nextRuleNumber.value)}`
		nextRuleNumber.value += 1

		ruleList.push({
			id,
			scope,
			targetId,
			allowChildOverride,
			value,
		})

		highlightedRuleId.value = id
	}

	async function saveDraft() {
		await nextTick()

		if (!editorDraft.value || !activeDefinition.value || !canSaveDraft.value) {
			return
		}

		if (!isScopeSupported(editorDraft.value.scope)) {
			duplicateMessage.value = t('libresign', 'This setting cannot be configured at this scope.')
			return
		}

		const { scope, value, targetIds } = editorDraft.value
		const policyKey = activeDefinition.value.key
		const isRequestExpiration = isRequestExpirationPolicyKey(policyKey)
		const isUnifiedSigningExecution = isUnifiedSigningExecutionPolicyKey(policyKey)
		const isSignatureStamp = isSignatureStampPolicyKey(policyKey)
		const normalizedRequestExpirationValue = isRequestExpiration
			? normalizeRequestExpirationDraftValue(value)
			: null
		const normalizedSigningExecutionValue = isUnifiedSigningExecution
			? normalizeSigningExecutionSettings(value)
			: null
		const normalizedSignatureStampValue = isSignatureStamp
			? normalizeSignatureStampDraftValue(
				value,
				resolveCollectMetadataValue(policiesStore.getPolicy(COLLECT_METADATA_POLICY_KEY)?.effectiveValue, false),
			)
			: null
		const allowChildOverride = activeDefinition.value.normalizeAllowChildOverride(scope, editorDraft.value.allowChildOverride)

		try {
			if (scope === 'system') {
				if (isRequestExpiration && normalizedRequestExpirationValue) {
					await Promise.all([
						policiesStore.saveSystemPolicy(policyKey, normalizedRequestExpirationValue.maximumValidity, allowChildOverride),
						policiesStore.saveSystemPolicy(REQUEST_EXPIRATION_RENEWAL_KEY, normalizedRequestExpirationValue.renewalInterval, allowChildOverride),
					])
				} else if (isUnifiedSigningExecution && normalizedSigningExecutionValue) {
					await Promise.all([
						policiesStore.saveSystemPolicy(policyKey, normalizedSigningExecutionValue.signingMode, allowChildOverride),
						policiesStore.saveSystemPolicy(
							SIGNING_EXECUTION_WORKER_KEY,
							serializeWorkerConfig({
								workerType: normalizedSigningExecutionValue.workerType,
								parallelWorkers: normalizedSigningExecutionValue.parallelWorkers,
							}),
							allowChildOverride,
						),
					])
				} else if (isSignatureStamp && normalizedSignatureStampValue) {
					await Promise.all([
						policiesStore.saveSystemPolicy(policyKey, normalizedSignatureStampValue.signatureStampValue, allowChildOverride),
						policiesStore.saveSystemPolicy(COLLECT_METADATA_POLICY_KEY, normalizedSignatureStampValue.collectMetadataEnabled, allowChildOverride),
					])
				} else {
					await policiesStore.saveSystemPolicy(policyKey, value, allowChildOverride)
				}
				if (viewMode.value === 'system-admin' && isScopeSupported('user')) {
					if (isRequestExpiration) {
						await Promise.all([
							policiesStore.clearUserPreference(policyKey),
							policiesStore.clearUserPreference(REQUEST_EXPIRATION_RENEWAL_KEY),
						])
					} else if (isUnifiedSigningExecution) {
						await Promise.all([
							policiesStore.clearUserPreference(policyKey),
							policiesStore.clearUserPreference(SIGNING_EXECUTION_WORKER_KEY),
						])
					} else if (isSignatureStamp) {
						await Promise.all([
							policiesStore.clearUserPreference(policyKey),
							policiesStore.clearUserPreference(COLLECT_METADATA_POLICY_KEY),
						])
					} else {
						await policiesStore.clearUserPreference(policyKey)
					}
				}
				explicitSystemRule.value = {
					id: 'system-default',
					scope: 'system',
					targetId: null,
					allowChildOverride,
					value: normalizedRequestExpirationValue ?? normalizedSigningExecutionValue ?? normalizedSignatureStampValue ?? value,
				}
				await policiesStore.fetchEffectivePolicies()
				cancelEditor()
				return
			}

			if (scope === 'group') {
				if (isRequestExpiration && normalizedRequestExpirationValue) {
					await Promise.all(targetIds.map((targetId) => {
						return Promise.all([
							policiesStore.saveGroupPolicy(targetId, policyKey, normalizedRequestExpirationValue.maximumValidity, allowChildOverride),
							policiesStore.saveGroupPolicy(targetId, REQUEST_EXPIRATION_RENEWAL_KEY, normalizedRequestExpirationValue.renewalInterval, allowChildOverride),
						])
					}))
				} else if (isUnifiedSigningExecution && normalizedSigningExecutionValue) {
					await Promise.all(targetIds.map((targetId) => {
						return Promise.all([
							policiesStore.saveGroupPolicy(targetId, policyKey, normalizedSigningExecutionValue.signingMode, allowChildOverride),
							policiesStore.saveGroupPolicy(
								targetId,
								SIGNING_EXECUTION_WORKER_KEY,
								serializeWorkerConfig({
									workerType: normalizedSigningExecutionValue.workerType,
									parallelWorkers: normalizedSigningExecutionValue.parallelWorkers,
								}),
								allowChildOverride,
							),
						])
					}))
				} else if (isSignatureStamp && normalizedSignatureStampValue) {
					await Promise.all(targetIds.map((targetId) => {
						return Promise.all([
							policiesStore.saveGroupPolicy(targetId, policyKey, normalizedSignatureStampValue.signatureStampValue, allowChildOverride),
							policiesStore.saveGroupPolicy(targetId, COLLECT_METADATA_POLICY_KEY, normalizedSignatureStampValue.collectMetadataEnabled, allowChildOverride),
						])
					}))
				} else {
					await Promise.all(targetIds.map((targetId) => {
						return policiesStore.saveGroupPolicy(targetId, policyKey, value, allowChildOverride)
					}))
				}

				for (const targetId of targetIds) {
					upsertRule(
						groupRules.value,
						'group',
						targetId,
						normalizedRequestExpirationValue ?? normalizedSigningExecutionValue ?? normalizedSignatureStampValue ?? value,
						allowChildOverride,
					)
				}
				cacheCurrentRuleCounts(policyKey)

				await policiesStore.fetchEffectivePolicies()
				cancelEditor()
				return
			}

			if (isRequestExpiration && normalizedRequestExpirationValue) {
				await Promise.all(targetIds.map((targetId) => {
					return Promise.all([
						policiesStore.saveUserPolicyForUser(targetId, policyKey, normalizedRequestExpirationValue.maximumValidity, allowChildOverride),
						policiesStore.saveUserPolicyForUser(targetId, REQUEST_EXPIRATION_RENEWAL_KEY, normalizedRequestExpirationValue.renewalInterval, allowChildOverride),
					])
				}))
			} else if (isUnifiedSigningExecution && normalizedSigningExecutionValue) {
				await Promise.all(targetIds.map((targetId) => {
					return Promise.all([
						policiesStore.saveUserPolicyForUser(targetId, policyKey, normalizedSigningExecutionValue.signingMode, allowChildOverride),
						policiesStore.saveUserPolicyForUser(
							targetId,
							SIGNING_EXECUTION_WORKER_KEY,
							serializeWorkerConfig({
								workerType: normalizedSigningExecutionValue.workerType,
								parallelWorkers: normalizedSigningExecutionValue.parallelWorkers,
							}),
							allowChildOverride,
						),
					])
				}))
			} else if (isSignatureStamp && normalizedSignatureStampValue) {
				await Promise.all(targetIds.map((targetId) => {
					return Promise.all([
						policiesStore.saveUserPolicyForUser(targetId, policyKey, normalizedSignatureStampValue.signatureStampValue, allowChildOverride),
						policiesStore.saveUserPolicyForUser(targetId, COLLECT_METADATA_POLICY_KEY, normalizedSignatureStampValue.collectMetadataEnabled, allowChildOverride),
					])
				}))
			} else {
				await Promise.all(targetIds.map((targetId) => {
					return policiesStore.saveUserPolicyForUser(targetId, policyKey, value, allowChildOverride)
				}))
			}

			for (const targetId of targetIds) {
				upsertRule(
					userRules.value,
					'user',
					targetId,
					normalizedRequestExpirationValue ?? normalizedSigningExecutionValue ?? normalizedSignatureStampValue ?? value,
					allowChildOverride,
				)
			}
			cacheCurrentRuleCounts(policyKey)

			await policiesStore.fetchEffectivePolicies()
			cancelEditor()
		} catch (error) {
			console.error('Failed to save policy:', error)
		}
	}

	async function removeRules(ruleIds: string[]) {
		if (!activeDefinition.value) {
			return
		}

		const uniqueRuleIds = [...new Set(ruleIds)].filter((ruleId) => typeof ruleId === 'string' && ruleId.length > 0)
		if (uniqueRuleIds.length === 0) {
			return
		}

		// Deletion can start directly from the CRUD table before persisted-rule hydration
		// finishes. Cancel the in-flight hydration so stale GET responses do not
		// recreate the just-deleted system rule in the table.
		cancelPendingHydration()

		const policyKey = activeDefinition.value.key
		const isRequestExpiration = isRequestExpirationPolicyKey(policyKey)
		const isUnifiedSigningExecution = isUnifiedSigningExecutionPolicyKey(policyKey)
		const isSignatureStamp = isSignatureStampPolicyKey(policyKey)
		const inheritedSystemRuleId = inheritedSystemRule.value?.id
		const shouldCloseSystemEditor = editorMode.value === 'edit' && editorDraft.value?.scope === 'system'
		const shouldCloseGroupEditor = editorMode.value === 'edit' && editorDraft.value?.scope === 'group'
		const shouldCloseUserEditor = editorMode.value === 'edit' && editorDraft.value?.scope === 'user'
		let shouldRefreshPolicies = false
		let shouldCloseEditor = false

		for (const ruleId of uniqueRuleIds) {
			if (ruleId === 'system-default' || (inheritedSystemRuleId !== null && ruleId === inheritedSystemRuleId)) {
				if (isRequestExpiration) {
					await Promise.all([
						policiesStore.saveSystemPolicy(policyKey, null, false),
						policiesStore.saveSystemPolicy(REQUEST_EXPIRATION_RENEWAL_KEY, null, false),
					])
				} else if (isUnifiedSigningExecution) {
					await Promise.all([
						policiesStore.saveSystemPolicy(policyKey, null, false),
						policiesStore.saveSystemPolicy(SIGNING_EXECUTION_WORKER_KEY, null, false),
					])
				} else if (isSignatureStamp) {
					await Promise.all([
						policiesStore.saveSystemPolicy(policyKey, null, false),
						policiesStore.saveSystemPolicy(COLLECT_METADATA_POLICY_KEY, null, false),
					])
				} else {
					await policiesStore.saveSystemPolicy(policyKey, null, false)
				}
				explicitSystemRule.value = null
				highlightedRuleId.value = null
				shouldRefreshPolicies = true
				shouldCloseEditor = shouldCloseEditor || shouldCloseSystemEditor
				continue
			}

			const groupIndex = groupRules.value.findIndex((rule) => rule.id === ruleId)
			if (groupIndex >= 0) {
				const targetId = groupRules.value[groupIndex]?.targetId
				if (targetId) {
					if (isRequestExpiration) {
						await Promise.all([
							policiesStore.clearGroupPolicy(targetId, policyKey),
							policiesStore.clearGroupPolicy(targetId, REQUEST_EXPIRATION_RENEWAL_KEY),
						])
					} else if (isUnifiedSigningExecution) {
						await Promise.all([
							policiesStore.clearGroupPolicy(targetId, policyKey),
							policiesStore.clearGroupPolicy(targetId, SIGNING_EXECUTION_WORKER_KEY),
						])
					} else if (isSignatureStamp) {
						await Promise.all([
							policiesStore.clearGroupPolicy(targetId, policyKey),
							policiesStore.clearGroupPolicy(targetId, COLLECT_METADATA_POLICY_KEY),
						])
					} else {
						await policiesStore.clearGroupPolicy(targetId, policyKey)
					}
				}
				groupRules.value.splice(groupIndex, 1)
				highlightedRuleId.value = null
				shouldRefreshPolicies = true
				shouldCloseEditor = shouldCloseEditor || shouldCloseGroupEditor
				continue
			}

			const userIndex = userRules.value.findIndex((rule) => rule.id === ruleId)
			if (userIndex >= 0) {
				const targetId = userRules.value[userIndex]?.targetId
				if (targetId) {
					if (isRequestExpiration) {
						await Promise.all([
							policiesStore.clearUserPolicyForUser(targetId, policyKey),
							policiesStore.clearUserPolicyForUser(targetId, REQUEST_EXPIRATION_RENEWAL_KEY),
						])
					} else if (isUnifiedSigningExecution) {
						await Promise.all([
							policiesStore.clearUserPolicyForUser(targetId, policyKey),
							policiesStore.clearUserPolicyForUser(targetId, SIGNING_EXECUTION_WORKER_KEY),
						])
					} else if (isSignatureStamp) {
						await Promise.all([
							policiesStore.clearUserPolicyForUser(targetId, policyKey),
							policiesStore.clearUserPolicyForUser(targetId, COLLECT_METADATA_POLICY_KEY),
						])
					} else {
						await policiesStore.clearUserPolicyForUser(targetId, policyKey)
					}
				}
				userRules.value.splice(userIndex, 1)
				highlightedRuleId.value = null
				shouldRefreshPolicies = true
				shouldCloseEditor = shouldCloseEditor || shouldCloseUserEditor
			}
		}

		if (shouldRefreshPolicies) {
			cacheCurrentRuleCounts(policyKey)
			await policiesStore.fetchEffectivePolicies()
		}

		if (shouldCloseEditor) {
			cancelEditor()
		}
	}

	async function removeRule(ruleId: string) {
		await removeRules([ruleId])
	}

	return reactive({
		activeDefinition,
		editorDraft,
		editorMode: editorMode as any,
		inheritedSystemRule,
		systemDefaultRule,
		hasGlobalDefault,
		effectiveSource,
		policyResolutionMode,
		summary,
		visibleGroupRules,
		visibleUserRules,
		createGroupOverrideDisabledReason,
		createUserOverrideDisabledReason,
		visibleSettingSummaries,
		highlightedRuleId: highlightedRuleId as any,
		viewMode: viewMode as any,
		availableTargets,
		loadingTargets,
		rulesLoading,
		canManageGroups,
		draftTargetLabel,
		duplicateMessage: duplicateMessage as any,
		canSaveDraft,
		isDraftDirty,
		openSetting,
		closeSetting,
		startEditor,
		cancelEditor,
		updateDraftValue,
		markDraftTouched,
		updateDraftTarget,
		updateDraftTargets,
		updateDraftAllowOverride,
		searchAvailableTargets,
		probeGroupAccess,
		setViewMode,
		saveDraft,
		removeRule,
		removeRules,
		resolveTargetLabel,
	})
}
