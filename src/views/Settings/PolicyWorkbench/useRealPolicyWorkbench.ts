/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import { realDefinitions } from './settings/realDefinitions'
import type { RealPolicyResolutionMode } from './settings/realTypes'
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

	const groups = ref<PolicyTargetOption[]>([])
	const users = ref<PolicyTargetOption[]>([])
	const canManageGroups = ref<boolean | null>(null)
	const loadingTargets = ref(false)
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

	const visibleSettingSummaries = computed<PolicySettingSummary[]>(() => {
		const isGroupAdminMode = viewMode.value === 'group-admin'

		return Object.values(realDefinitions)
			.map((definition) => {
				const policy = policiesStore.getPolicy(definition.key)
				const hasEffectiveValue = policy?.effectiveValue !== null && policy?.effectiveValue !== undefined
				const isActiveSetting = activeSettingKey.value === definition.key

				return {
					key: definition.key,
					title: definition.title,
					context: definition.context,
					description: definition.description,
					defaultSummary: hasEffectiveValue ? definition.summarizeValue(policy.effectiveValue) : t('libresign', 'Not configured'),
					groupCount: isActiveSetting ? groupRules.value.length : (policy?.groupCount ?? 0),
					userCount: isActiveSetting ? userRules.value.length : (policy?.userCount ?? 0),
				}
			})
			.filter((summary) => {
				if (!isGroupAdminMode) {
					return true
				}

				const policy = policiesStore.getPolicy(summary.key)
				return policy?.editableByCurrentActor === true
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
			return {
				id: 'system-inherited-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: inferSystemAllowOverride(policy),
				value: policy.effectiveValue,
			}
		}

		explicitSystemRule.value = {
			id: 'system-default',
			scope: 'system',
			targetId: null,
			allowChildOverride: inferSystemAllowOverride(policy),
			value: policy.effectiveValue,
		}

		return explicitSystemRule.value
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
		const fallbackValue = activeDefinition.value.getFallbackSystemDefault(
			policy?.effectiveValue,
			policy?.sourceScope,
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

		const activeGroupExceptions = groupRules.value.length
		const activeUserExceptions = userRules.value.length
		const activeBlockCount = [
			inheritedSystemRule.value?.allowChildOverride === false ? 1 : 0,
			...groupRules.value.map((rule) => rule.allowChildOverride ? 0 : 1),
		].reduce((sum, count) => sum + count, 0)

		const baseSource = hasGlobalDefault.value
			? t('libresign', 'Global default')
			: t('libresign', 'System default')

		return {
			currentBaseValue,
			baseSource,
			configurableLayers: t('libresign', 'Default > Group > User'),
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

	const createUserOverrideDisabledReason = computed(() => {
		if (viewMode.value === 'system-admin') {
			return null
		}

		if (activePolicyState.value?.editableByCurrentActor === false) {
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

	const visibleGroupRules = computed<PolicyRuleRecord[]>(() => groupRules.value)
	const visibleUserRules = computed<PolicyRuleRecord[]>(() => userRules.value)

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

		if (!isDraftDirty.value
			&& editorMode.value === 'create'
			&& editorDraft.value.scope === 'system'
			&& !isAllowOverrideMutable('system')) {
			return true
		}

		return isDraftDirty.value
	})

	async function hydratePersistedRules(policyKey: string) {
		const currentRequestId = hydratePersistedRulesRequestId.value + 1
		hydratePersistedRulesRequestId.value = currentRequestId

		await Promise.all([
			loadTargets('group', '', true),
			loadTargets('user', '', true),
		])

		const [persistedSystemPolicy, persistedGroupPolicies, persistedUserPolicies] = await Promise.all([
			policiesStore.fetchSystemPolicy(policyKey).catch((error) => {
				logger.debug('Could not load explicit system policy for workbench', {
					error,
					policyKey,
				})
				return null
			}),
			Promise.all(groups.value.map(async (group) => {
				try {
					const persistedPolicy = await policiesStore.fetchGroupPolicy(group.id, policyKey)
					if (!persistedPolicy || persistedPolicy.value === null || persistedPolicy.value === undefined) {
						return null
					}

					return {
						id: `group-${group.id}-persisted`,
						scope: 'group' as const,
						targetId: group.id,
						allowChildOverride: persistedPolicy.allowChildOverride,
						value: persistedPolicy.value,
					}
				} catch (error) {
					logger.debug('Could not load persisted group policy for target', {
						error,
						policyKey,
						groupId: group.id,
					})
					return null
				}
			})),
			Promise.all(users.value.map(async (user) => {
				try {
					const persistedPolicy = await policiesStore.fetchUserPolicyForUser(user.id, policyKey)
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
						policyKey,
						userId: user.id,
					})
					return null
				}
			})),
		])

		if (currentRequestId !== hydratePersistedRulesRequestId.value || activeSettingKey.value !== policyKey) {
			return
		}

		const hasPersistedRule = <TRule>(rule: TRule | null): rule is TRule => rule !== null

		explicitSystemRule.value = persistedSystemPolicy?.scope === 'global' && persistedSystemPolicy.value !== null && persistedSystemPolicy.value !== undefined
			? {
				id: 'system-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: persistedSystemPolicy.allowChildOverride,
				value: persistedSystemPolicy.value,
			}
			: null

		groupRules.value = persistedGroupPolicies.filter(hasPersistedRule)
		userRules.value = persistedUserPolicies.filter(hasPersistedRule)
		nextRuleNumber.value = groupRules.value.length + userRules.value.length + 1
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
		hydratePersistedRulesRequestId.value += 1

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
			if (baselineRuleValue !== null && baselineRuleValue !== undefined) {
				value = activeDefinition.value.normalizeDraftValue(baselineRuleValue)
			}
		} else if (scope === 'group') {
			targetIds = []
		} else if (scope === 'user') {
			targetIds = []
		}

		editorDraft.value = {
			scope,
			ruleId: ruleId || null,
			targetIds,
			value,
			allowChildOverride,
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

		editorDraft.value.targetIds = Array.from(new Set(targetIds.filter(Boolean)))
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
		if (!editorDraft.value || !activeDefinition.value || !canSaveDraft.value) {
			return
		}

		if (!isScopeSupported(editorDraft.value.scope)) {
			duplicateMessage.value = t('libresign', 'This setting cannot be configured at this scope.')
			return
		}

		const { scope, value, targetIds } = editorDraft.value
		const policyKey = activeDefinition.value.key
		const allowChildOverride = activeDefinition.value.normalizeAllowChildOverride(scope, editorDraft.value.allowChildOverride)

		try {
			if (scope === 'system') {
				await policiesStore.saveSystemPolicy(policyKey, value, allowChildOverride)
				if (viewMode.value === 'system-admin' && isScopeSupported('user')) {
					await policiesStore.clearUserPreference(policyKey)
				}
				explicitSystemRule.value = {
					id: 'system-default',
					scope: 'system',
					targetId: null,
					allowChildOverride,
					value,
				}
				await policiesStore.fetchEffectivePolicies()
				cancelEditor()
				return
			}

			if (scope === 'group') {
				await Promise.all(targetIds.map((targetId) => {
					return policiesStore.saveGroupPolicy(targetId, policyKey, value, allowChildOverride)
				}))

				for (const targetId of targetIds) {
					upsertRule(groupRules.value, 'group', targetId, value, allowChildOverride)
				}

				await policiesStore.fetchEffectivePolicies()
				cancelEditor()
				return
			}

			await Promise.all(targetIds.map((targetId) => {
				return policiesStore.saveUserPolicyForUser(targetId, policyKey, value, allowChildOverride)
			}))

			for (const targetId of targetIds) {
				upsertRule(userRules.value, 'user', targetId, value, allowChildOverride)
			}

			await policiesStore.fetchEffectivePolicies()
			cancelEditor()
		} catch (error) {
			console.error('Failed to save policy:', error)
		}
	}

	async function removeRule(ruleId: string) {
		if (!activeDefinition.value) {
			return
		}

		const policyKey = activeDefinition.value.key
		const inheritedSystemRuleId = inheritedSystemRule.value?.id
		const isEditingMode = editorMode.value === 'edit'

		const shouldCloseSystemEditor = isEditingMode && editorDraft.value?.scope === 'system'
		const shouldCloseGroupEditor = isEditingMode
			&& editorDraft.value?.scope === 'group'
			&& editorDraft.value.ruleId === ruleId
		const shouldCloseUserEditor = isEditingMode
			&& editorDraft.value?.scope === 'user'
			&& editorDraft.value.ruleId === ruleId

		if (ruleId === 'system-default' || (inheritedSystemRuleId !== null && ruleId === inheritedSystemRuleId)) {
			await policiesStore.saveSystemPolicy(policyKey, null as unknown as EffectivePolicyValue, false)
			explicitSystemRule.value = null
			highlightedRuleId.value = null
			await policiesStore.fetchEffectivePolicies()
			if (shouldCloseSystemEditor) {
				cancelEditor()
			}
			return
		}

		const groupIndex = groupRules.value.findIndex((rule) => rule.id === ruleId)
		if (groupIndex >= 0) {
			const targetId = groupRules.value[groupIndex]?.targetId
			if (targetId) {
				await policiesStore.clearGroupPolicy(targetId, policyKey)
			}
			groupRules.value.splice(groupIndex, 1)
			highlightedRuleId.value = null
			await policiesStore.fetchEffectivePolicies()
			if (shouldCloseGroupEditor) {
				cancelEditor()
			}
			return
		}

		const userIndex = userRules.value.findIndex((rule) => rule.id === ruleId)
		if (userIndex >= 0) {
			const targetId = userRules.value[userIndex]?.targetId
			if (targetId) {
				await policiesStore.clearUserPolicyForUser(targetId, policyKey)
			}
			userRules.value.splice(userIndex, 1)
			highlightedRuleId.value = null
			await policiesStore.fetchEffectivePolicies()
			if (shouldCloseUserEditor) {
				cancelEditor()
			}
		}
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
		resolveTargetLabel,
	})
}
