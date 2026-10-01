/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CompoundPolicyWriteValues, EffectivePolicyValue } from '../../../types/index'
import type { RealPolicyCompoundBehavior } from './settings/realTypes'
import {
	normalizeRequestExpirationDraftValue,
	type RequestExpirationDraftValue,
} from './settings/expiration-rules/model'
import {
	normalizeSignatureStampDraftValue,
	resolveCollectMetadataValue,
} from './settings/signature-text/model'
import {
	normalizeSigningExecutionSettings,
	normalizeWorkerConfig,
	resolveSigningMode,
	type SigningExecutionSettingsValue,
} from './settings/signing-mode/model'

export type PolicyScope = 'system' | 'group' | 'user'

export interface PolicyRuleRecord {
	id: string
	scope: PolicyScope
	targetId: string | null
	allowChildOverride: boolean
	value: EffectivePolicyValue
	canRemove?: boolean
}

export interface PersistedSystemPolicyRecord {
	scope?: string | null
	value?: EffectivePolicyValue | null
	allowChildOverride?: boolean
}

export interface CompoundPolicyMemberRecords {
	policyKey: string
	systemPolicy: PersistedSystemPolicyRecord | null
	groupPolicies: PolicyRuleRecord[]
	userPolicies: PolicyRuleRecord[]
}

export interface CompoundPolicyHydrationContext {
	parent: CompoundPolicyMemberRecords
	children: CompoundPolicyMemberRecords[]
	compound: RealPolicyCompoundBehavior
}

export interface CompoundPolicyHydrationResult {
	explicitSystemRule: PolicyRuleRecord | null
	groupRules: PolicyRuleRecord[]
	userRules: PolicyRuleRecord[]
}

interface CompoundPolicySaveStore {
	saveSystemPolicyCompound: (parentPolicyKey: string, values: CompoundPolicyWriteValues, allowChildOverride?: Record<string, boolean>) => Promise<unknown>
	saveGroupPolicyCompound: (groupId: string, parentPolicyKey: string, values: CompoundPolicyWriteValues, allowChildOverride?: Record<string, boolean>) => Promise<unknown>
	saveUserPolicyForUserCompound: (userId: string, parentPolicyKey: string, values: CompoundPolicyWriteValues, allowChildOverride?: Record<string, boolean>) => Promise<unknown>
}

interface CompoundPolicyClearStore {
	saveSystemPolicy: (policyKey: string, value: EffectivePolicyValue, allowChildOverride: boolean) => Promise<unknown>
	clearGroupPolicy: (targetId: string, policyKey: string) => Promise<unknown>
	clearUserPolicyForUser: (targetId: string, policyKey: string) => Promise<unknown>
}

interface CompoundPolicyPreferenceClearStore {
	clearUserPreference: (policyKey: string) => Promise<unknown>
}

interface SaveCompoundPolicyValueContext {
	scope: PolicyScope
	policyKey: string
	value: EffectivePolicyValue
	targetIds: string[]
	allowChildOverride: boolean
	policiesStore: CompoundPolicySaveStore
	compound?: RealPolicyCompoundBehavior
	compositeChildren?: string[]
}

export const REQUEST_EXPIRATION_POLICY_KEY = 'maximum_validity'
export const REQUEST_EXPIRATION_RENEWAL_KEY = 'renewal_interval'
export const SIGNING_EXECUTION_POLICY_KEY = 'signing_mode'
export const SIGNING_EXECUTION_WORKER_KEY = 'worker_config'
export const SIGNATURE_STAMP_POLICY_KEY = 'signature_stamp'
export const COLLECT_METADATA_POLICY_KEY = 'collect_metadata'
export const REQUEST_SIGN_GROUPS_POLICY_KEY = 'groups_request_sign'

function hasPersistedValue(value: EffectivePolicyValue | null | undefined): value is EffectivePolicyValue {
	return value !== null && value !== undefined
}

function getCompoundPolicyCompanionKey(policyKey: string): string | null {
	if (isRequestExpirationPolicyKey(policyKey)) {
		return REQUEST_EXPIRATION_RENEWAL_KEY
	}

	if (isUnifiedSigningExecutionPolicyKey(policyKey)) {
		return SIGNING_EXECUTION_WORKER_KEY
	}

	if (isSignatureStampPolicyKey(policyKey)) {
		return COLLECT_METADATA_POLICY_KEY
	}

	return null
}

async function clearCompoundPolicyPair(
	scope: PolicyScope,
	policyKey: string,
	companionKey: string,
	policiesStore: CompoundPolicyClearStore,
	targetId?: string,
) {
	if (scope === 'system') {
		await Promise.all([
			policiesStore.saveSystemPolicy(policyKey, null, false),
			policiesStore.saveSystemPolicy(companionKey, null, false),
		])
		return
	}

	if (!targetId) {
		return
	}

	if (scope === 'group') {
		await Promise.all([
			policiesStore.clearGroupPolicy(targetId, policyKey),
			policiesStore.clearGroupPolicy(targetId, companionKey),
		])
		return
	}

	await Promise.all([
		policiesStore.clearUserPolicyForUser(targetId, policyKey),
		policiesStore.clearUserPolicyForUser(targetId, companionKey),
	])
}

function mergeCompoundRulesByTarget(
	members: Array<{ policyKey: string, rules: PolicyRuleRecord[] }>,
	scope: 'group' | 'user',
	compose: RealPolicyCompoundBehavior['compose'],
	includeChildOnlyRules: boolean,
): PolicyRuleRecord[] {
	const mergedRules = new Map<string, { rule: PolicyRuleRecord, values: Record<string, EffectivePolicyValue | undefined> }>()

	for (const [index, member] of members.entries()) {
		const isParent = index === 0
		for (const rule of member.rules) {
			if (!rule.targetId) {
				continue
			}

			const existing = mergedRules.get(rule.targetId)
			if (!existing && !isParent && !includeChildOnlyRules) {
				continue
			}

			const entry = existing ?? { rule, values: {} }
			entry.values[member.policyKey] = rule.value
			mergedRules.set(rule.targetId, entry)
		}
	}

	return Array.from(mergedRules.values()).map(({ rule, values }) => ({
		id: rule.id,
		scope,
		targetId: rule.targetId,
		allowChildOverride: rule.allowChildOverride,
		value: compose(values),
		canRemove: rule.canRemove,
	}))
}

function hydrateCompoundSystemRule(
	members: CompoundPolicyMemberRecords[],
	compose: RealPolicyCompoundBehavior['compose'],
	includeChildOnlyRules: boolean,
): PolicyRuleRecord | null {
	const membersWithRequiredValue = includeChildOnlyRules ? members : members.slice(0, 1)
	const hasGlobalScope = members.some((member) => member.systemPolicy?.scope === 'global')
	const hasValue = membersWithRequiredValue.some((member) => hasPersistedValue(member.systemPolicy?.value))
	if (!hasGlobalScope || !hasValue) {
		return null
	}

	const values = Object.fromEntries(members.map((member) => [member.policyKey, member.systemPolicy?.value ?? undefined]))
	const allowChildOverride = members.find((member) => member.systemPolicy?.allowChildOverride !== undefined)?.systemPolicy?.allowChildOverride

	return {
		id: 'system-default',
		scope: 'system',
		targetId: null,
		allowChildOverride: allowChildOverride ?? true,
		value: compose(values),
	}
}

export function isRequestExpirationPolicyKey(policyKey: string): boolean {
	return policyKey === REQUEST_EXPIRATION_POLICY_KEY
}

export function isUnifiedSigningExecutionPolicyKey(policyKey: string): boolean {
	return policyKey === SIGNING_EXECUTION_POLICY_KEY
}

export function isSignatureStampPolicyKey(policyKey: string): boolean {
	return policyKey === SIGNATURE_STAMP_POLICY_KEY
}

export function buildRequestExpirationValue(
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

export function buildSigningExecutionValue(
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

export function buildSignatureStampDraftValue(
	signatureStampValue: EffectivePolicyValue | undefined,
	collectMetadataValue: EffectivePolicyValue | undefined,
) {
	return normalizeSignatureStampDraftValue(
		signatureStampValue,
		resolveCollectMetadataValue(collectMetadataValue, false),
	)
}

export async function saveCompoundPolicyValue(context: SaveCompoundPolicyValueContext): Promise<{ handled: true, savedValue: EffectivePolicyValue } | { handled: false }> {
	const { compound, compositeChildren = [] } = context
	if (!compound || compositeChildren.length === 0) {
		return { handled: false }
	}

	const values = compound.decompose(context.value)
	const allowChildOverride = Object.fromEntries(
		Object.keys(values).map((policyKey) => [policyKey, context.allowChildOverride]),
	)

	if (context.scope === 'system') {
		await context.policiesStore.saveSystemPolicyCompound(context.policyKey, values, allowChildOverride)
	} else if (context.scope === 'group') {
		await Promise.all(context.targetIds.map((targetId) => {
			return context.policiesStore.saveGroupPolicyCompound(targetId, context.policyKey, values, allowChildOverride)
		}))
	} else {
		await Promise.all(context.targetIds.map((targetId) => {
			return context.policiesStore.saveUserPolicyForUserCompound(targetId, context.policyKey, values, allowChildOverride)
		}))
	}

	return {
		handled: true,
		savedValue: compound.compose(values),
	}
}

export async function clearCompoundUserPreferences(
	policyKey: string,
	policiesStore: CompoundPolicyPreferenceClearStore,
): Promise<boolean> {
	const companionKey = getCompoundPolicyCompanionKey(policyKey)
	if (!companionKey) {
		return false
	}

	await Promise.all([
		policiesStore.clearUserPreference(policyKey),
		policiesStore.clearUserPreference(companionKey),
	])
	return true
}

export async function clearCompoundPolicyTarget(
	scope: PolicyScope,
	policyKey: string,
	targetId: string | undefined,
	policiesStore: CompoundPolicyClearStore,
): Promise<boolean> {
	const companionKey = getCompoundPolicyCompanionKey(policyKey)
	if (!companionKey) {
		return false
	}

	await clearCompoundPolicyPair(scope, policyKey, companionKey, policiesStore, targetId)
	return true
}

export function hydrateCompoundPolicyRules({ parent, children, compound }: CompoundPolicyHydrationContext): CompoundPolicyHydrationResult {
	const members = [parent, ...children]
	const includeChildOnlyRules = compound.includeChildOnlyRules ?? true

	return {
		explicitSystemRule: hydrateCompoundSystemRule(members, compound.compose, includeChildOnlyRules),
		groupRules: mergeCompoundRulesByTarget(
			members.map((member) => ({ policyKey: member.policyKey, rules: member.groupPolicies })),
			'group',
			compound.compose,
			includeChildOnlyRules,
		),
		userRules: mergeCompoundRulesByTarget(
			members.map((member) => ({ policyKey: member.policyKey, rules: member.userPolicies })),
			'user',
			compound.compose,
			includeChildOnlyRules,
		),
	}
}
