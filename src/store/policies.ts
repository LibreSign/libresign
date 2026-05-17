/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	EffectivePolicyState,
	EffectivePolicyValue,
	EffectivePoliciesResponse,
	EffectivePoliciesState,
	GroupPolicyResponse,
	GroupPolicyState,
	GroupPolicyWriteResponse,
	SystemPolicyResponse,
	SystemPolicyState,
	SystemPolicyWriteResponse,
	UserPolicyResponse,
	UserPolicyState,
} from '../types/index'

function isEffectivePolicyState(value: unknown): value is EffectivePolicyState {
	if (typeof value !== 'object' || value === null) {
		return false
	}

	const candidate = value as Partial<EffectivePolicyState>
	return typeof candidate.policyKey === 'string'
		&& Array.isArray(candidate.allowedValues)
		&& typeof candidate.sourceScope === 'string'
		&& typeof candidate.visible === 'boolean'
		&& typeof candidate.editableByCurrentActor === 'boolean'
		&& typeof candidate.canSaveAsUserDefault === 'boolean'
		&& typeof candidate.canUseAsRequestOverride === 'boolean'
		&& typeof candidate.preferenceWasCleared === 'boolean'
		&& (candidate.blockedBy === null || typeof candidate.blockedBy === 'string')
}

function isGroupPolicyState(value: unknown): value is GroupPolicyState {
	if (typeof value !== 'object' || value === null) {
		return false
	}

	const candidate = value as Partial<GroupPolicyState>
	return typeof candidate.policyKey === 'string'
		&& candidate.scope === 'group'
		&& typeof candidate.targetId === 'string'
		&& typeof candidate.allowChildOverride === 'boolean'
		&& typeof candidate.visibleToChild === 'boolean'
		&& Array.isArray(candidate.allowedValues)
}

function isSystemPolicyState(value: unknown): value is SystemPolicyState {
	if (typeof value !== 'object' || value === null) {
		return false
	}

	const candidate = value as Partial<SystemPolicyState>
	return typeof candidate.policyKey === 'string'
		&& (candidate.scope === 'system' || candidate.scope === 'global')
		&& typeof candidate.allowChildOverride === 'boolean'
		&& typeof candidate.visibleToChild === 'boolean'
		&& Array.isArray(candidate.allowedValues)
}

function isUserPolicyState(value: unknown): value is UserPolicyState {
	if (typeof value !== 'object' || value === null) {
		return false
	}

	const candidate = value as Partial<UserPolicyState>
	return typeof candidate.policyKey === 'string'
		&& candidate.scope === 'user_policy'
		&& typeof candidate.targetId === 'string'
		&& typeof candidate.allowChildOverride === 'boolean'
}

function sanitizePolicies(rawPolicies: Record<string, unknown>): EffectivePoliciesState {
	const nextPolicies: EffectivePoliciesState = {}

	for (const [policyKey, candidate] of Object.entries(rawPolicies)) {
		if (isEffectivePolicyState(candidate)) {
			nextPolicies[policyKey] = candidate
		}
	}

	return nextPolicies
}

interface PolicyRequestPayload {
	value: EffectivePolicyValue
}

interface SystemPolicyRequestPayload extends PolicyRequestPayload {
	allowChildOverride?: boolean
}

interface GroupPolicyRequestPayload extends PolicyRequestPayload {
	allowChildOverride: boolean
}

const _policiesStore = defineStore('policies', () => {
	const initialPolicies = loadState<EffectivePoliciesResponse>('libresign', 'effective_policies', { policies: {} })
	const policies = ref<EffectivePoliciesState>(sanitizePolicies(initialPolicies.policies ?? {}))

	const setPolicies = (nextPolicies: Record<string, unknown>): void => {
		policies.value = sanitizePolicies(nextPolicies)
	}

	const fetchEffectivePolicies = async (): Promise<void> => {
		try {
			const response = await axios.get<{ ocs?: { data?: EffectivePoliciesResponse } }>(generateOcsUrl('/apps/libresign/api/v1/policies/effective'))
			setPolicies(response.data?.ocs?.data?.policies ?? {})
		} catch (error: unknown) {
			console.error('Failed to load effective policies', error)
		}
	}

	const saveSystemPolicy = async (
		policyKey: string,
		value: EffectivePolicyValue,
		allowChildOverride?: boolean,
	): Promise<EffectivePolicyState | null> => {
		const payload: SystemPolicyRequestPayload & { allowChildOverride?: boolean } = { value }
		if (typeof allowChildOverride === 'boolean') {
			payload.allowChildOverride = allowChildOverride
		}
		const response = await axios.post<{ ocs?: { data?: SystemPolicyWriteResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/system/${policyKey}`),
			payload,
		)

		const savedPolicy = response.data?.ocs?.data?.policy
		if (!isEffectivePolicyState(savedPolicy)) {
			return null
		}

		policies.value = {
			...policies.value,
			[policyKey]: savedPolicy,
		}

		return savedPolicy
	}

	const fetchGroupPolicy = async (groupId: string, policyKey: string): Promise<GroupPolicyState | null> => {
		const response = await axios.get<{ ocs?: { data?: GroupPolicyResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/group/${groupId}/${policyKey}`),
		)

		const policy = response.data?.ocs?.data?.policy
		if (!isGroupPolicyState(policy)) {
			return null
		}

		return policy
	}

	const fetchSystemPolicy = async (policyKey: string): Promise<SystemPolicyState | null> => {
		const response = await axios.get<{ ocs?: { data?: SystemPolicyResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/system/${policyKey}`),
		)

		const policy = response.data?.ocs?.data?.policy
		if (!isSystemPolicyState(policy)) {
			return null
		}

		return policy
	}

	const fetchUserPolicyForUser = async (userId: string, policyKey: string): Promise<UserPolicyState | null> => {
		const response = await axios.get<{ ocs?: { data?: UserPolicyResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/user/${userId}/${policyKey}`),
		)

		const policy = response.data?.ocs?.data?.policy
		if (!isUserPolicyState(policy)) {
			return null
		}

		return policy
	}

	const saveGroupPolicy = async (
		groupId: string,
		policyKey: string,
		value: EffectivePolicyValue,
		allowChildOverride: boolean,
	): Promise<GroupPolicyState | null> => {
		const payload: GroupPolicyRequestPayload = { value, allowChildOverride }
		const response = await axios.put<{ ocs?: { data?: GroupPolicyWriteResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/group/${groupId}/${policyKey}`),
			payload,
		)

		const policy = response.data?.ocs?.data?.policy
		if (!isGroupPolicyState(policy)) {
			return null
		}

		return policy
	}

	const clearGroupPolicy = async (groupId: string, policyKey: string): Promise<GroupPolicyState | null> => {
		const response = await axios.delete<{ ocs?: { data?: GroupPolicyWriteResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/group/${groupId}/${policyKey}`),
		)

		const policy = response.data?.ocs?.data?.policy
		if (!isGroupPolicyState(policy)) {
			return null
		}

		return policy
	}

	const saveUserPreference = async (policyKey: string, value: EffectivePolicyValue): Promise<EffectivePolicyState | null> => {
		const payload: SystemPolicyRequestPayload = { value }
		const response = await axios.put<{ ocs?: { data?: SystemPolicyWriteResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/user/${policyKey}`),
			payload,
		)

		const savedPolicy = response.data?.ocs?.data?.policy
		if (!isEffectivePolicyState(savedPolicy)) {
			return null
		}

		policies.value = {
			...policies.value,
			[policyKey]: savedPolicy,
		}

		return savedPolicy
	}

	const clearUserPreference = async (policyKey: string): Promise<EffectivePolicyState | null> => {
		const response = await axios.delete<{ ocs?: { data?: SystemPolicyWriteResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/user/${policyKey}`),
		)

		const savedPolicy = response.data?.ocs?.data?.policy
		if (!isEffectivePolicyState(savedPolicy)) {
			return null
		}

		policies.value = {
			...policies.value,
			[policyKey]: savedPolicy,
		}

		return savedPolicy
	}

	const saveUserPolicyForUser = async (
		userId: string,
		policyKey: string,
		value: EffectivePolicyValue,
		allowChildOverride: boolean,
	): Promise<UserPolicyState | null> => {
		const payload: SystemPolicyRequestPayload & { allowChildOverride: boolean } = {
			value,
			allowChildOverride,
		}
		const response = await axios.put<{ ocs?: { data?: UserPolicyResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/user/${userId}/${policyKey}`),
			payload,
		)

		const savedPolicy = response.data?.ocs?.data?.policy
		if (!isUserPolicyState(savedPolicy)) {
			return null
		}

		return savedPolicy
	}

	const clearUserPolicyForUser = async (userId: string, policyKey: string): Promise<UserPolicyState | null> => {
		const response = await axios.delete<{ ocs?: { data?: UserPolicyResponse } }>(
			generateOcsUrl(`/apps/libresign/api/v1/policies/user/${userId}/${policyKey}`),
		)

		const savedPolicy = response.data?.ocs?.data?.policy
		if (!isUserPolicyState(savedPolicy)) {
			return null
		}

		return savedPolicy
	}

	const getPolicy = (policyKey: string): EffectivePolicyState | null => {
		const policy = policies.value[policyKey]
		if (!policy) {
			return null
		}

		return policy
	}

	const getEffectiveValue = (policyKey: string): EffectivePolicyState['effectiveValue'] | null => {
		return getPolicy(policyKey)?.effectiveValue ?? null
	}

	const canUseRequestOverride = (policyKey: string): boolean => {
		return getPolicy(policyKey)?.canUseAsRequestOverride ?? true
	}

	return {
		policies: computed(() => policies.value),
		setPolicies,
		fetchEffectivePolicies,
		fetchGroupPolicy,
		fetchSystemPolicy,
		fetchUserPolicyForUser,
		saveSystemPolicy,
		saveGroupPolicy,
		clearGroupPolicy,
		saveUserPreference,
		clearUserPreference,
		saveUserPolicyForUser,
		clearUserPolicyForUser,
		getPolicy,
		getEffectiveValue,
		canUseRequestOverride,
	}
})

export const usePoliciesStore = function(...args: Parameters<typeof _policiesStore>) {
	return _policiesStore(...args)
}

export {
	isEffectivePolicyState,
	isGroupPolicyState,
	isSystemPolicyState,
	isUserPolicyState,
}
