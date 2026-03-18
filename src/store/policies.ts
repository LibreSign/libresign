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
	EffectivePoliciesResponse,
	EffectivePoliciesState,
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

function sanitizePolicies(rawPolicies: Record<string, unknown>): EffectivePoliciesState {
	const nextPolicies: EffectivePoliciesState = {}

	for (const [policyKey, candidate] of Object.entries(rawPolicies)) {
		if (isEffectivePolicyState(candidate)) {
			nextPolicies[policyKey] = candidate
		}
	}

	return nextPolicies
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
}
