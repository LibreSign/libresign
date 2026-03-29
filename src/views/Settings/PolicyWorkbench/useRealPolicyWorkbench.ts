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

import SignatureFlowScalarRuleEditor from './settings/signature-flow/SignatureFlowScalarRuleEditor.vue'
import { usePoliciesStore } from '../../../store/policies'
import type { EffectivePolicyState, EffectivePolicyValue } from '../../../types/index'
import logger from '../../../logger.js'

type PolicyScope = 'system' | 'group' | 'user'
type PolicyResolutionMode = 'precedence' | 'merge' | 'conflict_requires_selection'

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

interface PolicySettingDefinition {
	key: string
	title: string
	description: string
	menuHint: string
	editor: unknown
	createEmptyValue: () => EffectivePolicyValue
	summarizeValue: (value: EffectivePolicyValue) => string
	formatAllowOverride: (allowChildOverride: boolean) => string
}

interface PolicySettingSummary {
	key: string
	title: string
	description: string
	menuHint: string
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

interface UserDetailsRecord {
	id: string
	displayname?: string
	'display-name'?: string
	email?: string
}

interface UserDetailsResponse {
	ocs?: {
		data?: {
			users?: Record<string, UserDetailsRecord>
		}
	}
}

const realDefinitions = {
	signature_flow: {
		key: 'signature_flow',
		title: t('libresign', 'Signing order'),
		description: t('libresign', 'Define whether signers work in parallel or in a sequential order.'),
		menuHint: t('libresign', 'Define the default signing flow and where overrides are allowed.'),
		editor: SignatureFlowScalarRuleEditor,
		createEmptyValue: () => '' as unknown as EffectivePolicyValue,
		summarizeValue: (value: EffectivePolicyValue) => {
			const flowValue = resolveSignatureFlowMode(value)
			switch (flowValue) {
			case 'parallel':
				return t('libresign', 'Simultaneous (Parallel)')
			case 'ordered_numeric':
				return t('libresign', 'Sequential')
			case 'none':
				return t('libresign', 'Let users choose')
			default:
				return t('libresign', 'Not configured')
			}
		},
		formatAllowOverride: (allowChildOverride: boolean) =>
			allowChildOverride
				? t('libresign', 'Lower layers may override this rule')
				: t('libresign', 'Lower layers must inherit this value'),
	},
}

function resolveSignatureFlowMode(value: EffectivePolicyValue): string | null {
	if (value === 0) {
		return 'none'
	}

	if (value === 1) {
		return 'parallel'
	}

	if (value === 2) {
		return 'ordered_numeric'
	}

	if (typeof value === 'string') {
		if (value === 'parallel' || value === 'ordered_numeric' || value === 'none') {
			return value
		}

		return null
	}

	if (value && typeof value === 'object' && 'flow' in (value as Record<string, unknown>)) {
		const candidate = (value as { flow?: unknown }).flow
		return typeof candidate === 'string' ? candidate : null
	}

	return null
}

function normalizeDraftValueForPolicy(policyKey: string, value: EffectivePolicyValue): EffectivePolicyValue {
	if (policyKey !== 'signature_flow') {
		return value
	}

	const mode = resolveSignatureFlowMode(value)
	return mode ?? 'parallel'
}

function inferSystemAllowOverride(policy: { allowedValues?: unknown[] } | null): boolean {
	if (!policy || !Array.isArray(policy.allowedValues)) {
		return true
	}

	// When lower layers are locked, backend narrows allowedValues to a single value.
	return policy.allowedValues.length !== 1
}

function getPolicyResolutionMode(policyKey: string): PolicyResolutionMode {
	if (policyKey === 'signature_flow') {
		return 'precedence'
	}

	return 'precedence'
}

function getSignatureFlowFallbackSystemDefault(policy: EffectivePolicyState | null): EffectivePolicyValue {
	if (policy?.sourceScope === 'system' && policy.effectiveValue !== null && policy.effectiveValue !== undefined) {
		return policy.effectiveValue
	}

	return 'none'
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
	const config = loadState<{ can_manage_group_policies?: boolean }>('libresign', 'config', {})
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

	const groups = ref<PolicyTargetOption[]>([])
	const users = ref<PolicyTargetOption[]>([])
	const loadingTargets = ref(false)
	const hydrateGroupRulesRequestId = ref(0)
	const editorInitialSnapshot = ref('')

	const visibleSettingSummaries = computed<PolicySettingSummary[]>(() => {
		return Object.values(realDefinitions).map((definition) => {
			const policy = policiesStore.getPolicy(definition.key)

			return {
				key: definition.key,
				title: definition.title,
				description: definition.description,
				menuHint: definition.menuHint,
				defaultSummary: policy?.effectiveValue ? definition.summarizeValue(policy.effectiveValue) : t('libresign', 'Not configured'),
				groupCount: groupRules.value.length,
				userCount: userRules.value.length,
			}
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
		if (!policy?.effectiveValue) {
			return null
		}

		// A baseline "none" value means there is no explicit global rule persisted.
		if (activeDefinition.value.key === 'signature_flow') {
			const mode = resolveSignatureFlowMode(policy.effectiveValue)
			const sourceScope = policy.sourceScope || 'system'
			if (mode === 'none' && sourceScope === 'system') {
				return null
			}
		}

		return {
			id: 'system-default',
			scope: 'system',
			targetId: null,
			allowChildOverride: inferSystemAllowOverride(policy),
			value: policy.effectiveValue,
		}
	})

	const policyResolutionMode = computed<PolicyResolutionMode>(() => {
		if (!activeDefinition.value) {
			return 'precedence'
		}

		return getPolicyResolutionMode(activeDefinition.value.key)
	})

	const systemDefaultRule = computed<PolicyRuleRecord | null>(() => {
		if (!activeDefinition.value) {
			return null
		}

		const policy = activePolicyState.value
		const fallbackValue = activeDefinition.value.key === 'signature_flow'
			? getSignatureFlowFallbackSystemDefault(policy)
			: policy?.effectiveValue

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
		const policy = activePolicyState.value
		if (!policy) {
			return false
		}

		if (policy.sourceScope === 'system') {
			return false
		}

		return inheritedSystemRule.value !== null
	})

	const effectiveSource = computed(() => {
		const sourceScope = activePolicyState.value?.sourceScope
		if (sourceScope === 'system') {
			return 'system'
		}

		if (sourceScope === 'group' || sourceScope === 'user') {
			return sourceScope
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
			configurableLayers: t('libresign', 'Global > Group > User'),
			platformFallback: fallbackLabel,
			resolutionMode: policyResolutionMode.value,
			activeGroupExceptions,
			activeUserExceptions,
			activeBlockCount,
		}
	})

	const createGroupOverrideDisabledReason = computed(() => {
		if (inheritedSystemRule.value?.allowChildOverride === false) {
			return t('libresign', 'Blocked by the global default.')
		}

		return null
	})

	const createUserOverrideDisabledReason = computed(() => {
		if (viewMode.value === 'system-admin') {
			return null
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
	})

		function hasSelectableDraftValue(draft: PolicyEditorDraft) {
			if (!activeDefinition.value) {
				return false
			}

			if (activeDefinition.value.key === 'signature_flow') {
				return resolveSignatureFlowMode(draft.value) !== null
			}

			return draft.value !== null && draft.value !== undefined
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

		if (isDraftDirty.value) {
			return true
		}

		return editorDraft.value.scope === 'system'
			&& editorMode.value === 'create'
			&& !hasGlobalDefault.value
	})

	async function hydratePersistedGroupRules(policyKey: string) {
		const currentRequestId = hydrateGroupRulesRequestId.value + 1
		hydrateGroupRulesRequestId.value = currentRequestId

		await loadTargets('group')

		const hydratedRules: PolicyRuleRecord[] = []
		for (const group of groups.value) {
			try {
				const persistedPolicy = await policiesStore.fetchGroupPolicy(group.id, policyKey)
				if (!persistedPolicy || persistedPolicy.value === null || persistedPolicy.value === undefined) {
					continue
				}

				hydratedRules.push({
					id: `group-${group.id}-persisted`,
					scope: 'group',
					targetId: group.id,
					allowChildOverride: persistedPolicy.allowChildOverride,
					value: persistedPolicy.value,
				})
			} catch (error) {
				logger.debug('Could not load persisted group policy for target', {
					error,
					policyKey,
					groupId: group.id,
				})
			}
		}

		if (currentRequestId !== hydrateGroupRulesRequestId.value || activeSettingKey.value !== policyKey) {
			return
		}

		groupRules.value = hydratedRules
		nextRuleNumber.value = hydratedRules.length + 1
	}

	function openSetting(key: string) {
		activeSettingKey.value = key
		groupRules.value = []
		userRules.value = []
		void hydratePersistedGroupRules(key)
	}

	function mergeSelectedTargets(scope: 'group' | 'user', fetchedTargets: PolicyTargetOption[]) {
		const existingTargets = scope === 'group' ? groups.value : users.value
		const selectedIds = editorDraft.value?.scope === scope ? editorDraft.value.targetIds : []
		const selectedTargets = existingTargets.filter((target) => {
			return selectedIds.includes(target.id) && !fetchedTargets.some((option) => option.id === target.id)
		})

		return [...selectedTargets, ...fetchedTargets]
	}

	async function fetchGroups(query = ''): Promise<PolicyTargetOption[]> {
		const { data } = await axios.get<GroupDetailsResponse>(generateOcsUrl('cloud/groups/details'), {
			params: {
				search: query,
				limit: 20,
				offset: 0,
			},
		})

		return (data.ocs?.data?.groups ?? [])
			.map((group) => ({
				id: group.id,
				displayName: group.displayname || group.id,
				subname: typeof group.usercount === 'number'
					? t('libresign', '{count} members', { count: String(group.usercount) })
					: undefined,
				isNoUser: true,
			}))
			.sort((left, right) => left.displayName.localeCompare(right.displayName))
	}

	async function fetchUsers(query = ''): Promise<PolicyTargetOption[]> {
		const { data } = await axios.get<UserDetailsResponse>(generateOcsUrl('cloud/users/details'), {
			params: {
				search: query,
				limit: 20,
				offset: 0,
			},
		})

		return Object.values(data.ocs?.data?.users ?? {})
			.map((user) => ({
				id: user.id,
				displayName: user['display-name'] || user.displayname || user.id,
				subname: user.email,
				user: user.id,
			}))
			.sort((left, right) => left.displayName.localeCompare(right.displayName))
	}

	async function loadTargets(scope: 'group' | 'user', query = '') {
		loadingTargets.value = true
		try {
			if (scope === 'group') {
				groups.value = mergeSelectedTargets('group', await fetchGroups(query))
				return
			}

			users.value = mergeSelectedTargets('user', await fetchUsers(query))
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

	function setViewMode(mode: 'system-admin' | 'group-admin') {
		viewMode.value = mode
	}

	function closeSetting() {
		activeSettingKey.value = null
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

		if (!ruleId && scope === 'group' && createGroupOverrideDisabledReason.value) {
			duplicateMessage.value = createGroupOverrideDisabledReason.value
			return
		}

		if (!ruleId && scope === 'user' && createUserOverrideDisabledReason.value) {
			duplicateMessage.value = createUserOverrideDisabledReason.value
			return
		}

		// Cancel any in-flight hydration result to avoid stale overwrite while editing.
		hydrateGroupRulesRequestId.value += 1

		const isEdit = !!ruleId
		editorMode.value = isEdit ? 'edit' : 'create'
		duplicateMessage.value = null

		let value: EffectivePolicyValue = activeDefinition.value.createEmptyValue()
		let targetIds: string[] = []
		let allowChildOverride = true

		if (isEdit && ruleId) {
			const rule = findRuleById(scope, ruleId)
			if (rule) {
				value = normalizeDraftValueForPolicy(activeDefinition.value.key, rule.value)
				allowChildOverride = rule.allowChildOverride
				targetIds = rule.targetId ? [rule.targetId] : []
			}
		} else if (scope === 'system') {
			const baselineRuleValue = inheritedSystemRule.value?.value ?? systemDefaultRule.value?.value
			if (baselineRuleValue !== null && baselineRuleValue !== undefined) {
				value = normalizeDraftValueForPolicy(activeDefinition.value.key, baselineRuleValue)
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

		if (scope === 'group' || scope === 'user') {
			void loadTargets(scope)
		}
	}

	function cancelEditor() {
		editorDraft.value = null
		editorMode.value = null
		duplicateMessage.value = null
		editorInitialSnapshot.value = ''
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
			editorDraft.value.allowChildOverride = allowChildOverride
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

		const { scope, value, allowChildOverride, targetIds } = editorDraft.value
		const policyKey = activeDefinition.value.key

		try {
			if (scope === 'system') {
				await policiesStore.saveSystemPolicy(policyKey, value, allowChildOverride)
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
				return policiesStore.saveUserPolicyForUser(targetId, policyKey, value)
			}))

			for (const targetId of targetIds) {
				upsertRule(userRules.value, 'user', targetId, value, true)
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
			await policiesStore.saveSystemPolicy(policyKey, null as unknown as EffectivePolicyValue)
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
		draftTargetLabel,
		duplicateMessage: duplicateMessage as any,
		canSaveDraft,
		isDraftDirty,
		openSetting,
		closeSetting,
		startEditor,
		cancelEditor,
		updateDraftValue,
		updateDraftTarget,
		updateDraftTargets,
		updateDraftAllowOverride,
		searchAvailableTargets,
		setViewMode,
		saveDraft,
		removeRule,
		resolveTargetLabel,
	})
}
