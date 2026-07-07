/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../types/index'
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
	serializeWorkerConfig,
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

export interface CompoundPolicyHydrationContext {
	policyKey: string
	persistedSystemPolicy: PersistedSystemPolicyRecord | null
	companionSystemPolicy: PersistedSystemPolicyRecord | null
	persistedGroupPolicies: PolicyRuleRecord[]
	companionGroupPolicies: PolicyRuleRecord[]
	persistedUserPolicies: PolicyRuleRecord[]
	companionUserPolicies: PolicyRuleRecord[]
}

export interface CompoundPolicyHydrationResult {
	explicitSystemRule: PolicyRuleRecord | null
	groupRules: PolicyRuleRecord[]
	userRules: PolicyRuleRecord[]
}

interface CompoundPolicySaveStore {
	saveSystemPolicy: (policyKey: string, value: EffectivePolicyValue, allowChildOverride: boolean) => Promise<unknown>
	saveGroupPolicy: (targetId: string, policyKey: string, value: EffectivePolicyValue, allowChildOverride: boolean) => Promise<unknown>
	saveUserPolicyForUser: (targetId: string, policyKey: string, value: EffectivePolicyValue, allowChildOverride: boolean) => Promise<unknown>
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
	collectMetadataEffectiveValue?: EffectivePolicyValue | null | undefined
}

export const REQUEST_EXPIRATION_POLICY_KEY = 'maximum_validity'
export const REQUEST_EXPIRATION_RENEWAL_KEY = 'renewal_interval'
export const SIGNING_EXECUTION_POLICY_KEY = 'signing_mode'
export const SIGNING_EXECUTION_WORKER_KEY = 'worker_config'
export const SIGNATURE_STAMP_POLICY_KEY = 'signature_stamp'
export const COLLECT_METADATA_POLICY_KEY = 'collect_metadata'
export const REQUEST_SIGN_GROUPS_POLICY_KEY = 'groups_request_sign'

interface CompoundPolicyPair {
	companionKey: string
	primaryValue: EffectivePolicyValue
	companionValue: EffectivePolicyValue
	savedValue: EffectivePolicyValue
}

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

function resolveCompoundPolicyPair(
	policyKey: string,
	value: EffectivePolicyValue,
	collectMetadataEffectiveValue?: EffectivePolicyValue | null,
): CompoundPolicyPair | null {
	if (isRequestExpirationPolicyKey(policyKey)) {
		const normalizedValue = normalizeRequestExpirationDraftValue(value)
		return {
			companionKey: REQUEST_EXPIRATION_RENEWAL_KEY,
			primaryValue: normalizedValue.maximumValidity,
			companionValue: normalizedValue.renewalInterval,
			savedValue: normalizedValue,
		}
	}

	if (isUnifiedSigningExecutionPolicyKey(policyKey)) {
		const normalizedValue = normalizeSigningExecutionSettings(value)
		return {
			companionKey: SIGNING_EXECUTION_WORKER_KEY,
			primaryValue: normalizedValue.signingMode,
			companionValue: serializeWorkerConfig({
				workerType: normalizedValue.workerType,
				parallelWorkers: normalizedValue.parallelWorkers,
			}),
			savedValue: normalizedValue,
		}
	}

	if (isSignatureStampPolicyKey(policyKey)) {
		const normalizedValue = normalizeSignatureStampDraftValue(
			value,
			resolveCollectMetadataValue(collectMetadataEffectiveValue, false),
		)
		return {
			companionKey: COLLECT_METADATA_POLICY_KEY,
			primaryValue: normalizedValue.signatureStampValue,
			companionValue: normalizedValue.collectMetadataEnabled,
			savedValue: normalizedValue,
		}
	}

	return null
}

async function saveCompoundPolicyPair(
	scope: PolicyScope,
	policyKey: string,
	compoundPair: CompoundPolicyPair,
	targetIds: string[],
	allowChildOverride: boolean,
	policiesStore: CompoundPolicySaveStore,
) {
	if (scope === 'system') {
		await Promise.all([
			policiesStore.saveSystemPolicy(policyKey, compoundPair.primaryValue, allowChildOverride),
			policiesStore.saveSystemPolicy(compoundPair.companionKey, compoundPair.companionValue, allowChildOverride),
		])
		return
	}

	if (scope === 'group') {
		await Promise.all(targetIds.map((targetId) => {
			return Promise.all([
				policiesStore.saveGroupPolicy(targetId, policyKey, compoundPair.primaryValue, allowChildOverride),
				policiesStore.saveGroupPolicy(targetId, compoundPair.companionKey, compoundPair.companionValue, allowChildOverride),
			])
		}))
		return
	}

	await Promise.all(targetIds.map((targetId) => {
		return Promise.all([
			policiesStore.saveUserPolicyForUser(targetId, policyKey, compoundPair.primaryValue, allowChildOverride),
			policiesStore.saveUserPolicyForUser(targetId, compoundPair.companionKey, compoundPair.companionValue, allowChildOverride),
		])
	}))
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
	primaryRules: PolicyRuleRecord[],
	companionRules: PolicyRuleRecord[],
	scope: 'group' | 'user',
	buildValue: (primaryValue: EffectivePolicyValue | undefined, companionValue: EffectivePolicyValue | undefined) => EffectivePolicyValue,
	includeCompanionOnlyRules = true,
): PolicyRuleRecord[] {
	const mergedRules = new Map<string, PolicyRuleRecord>()

	for (const rule of primaryRules) {
		if (!rule.targetId) {
			continue
		}

		mergedRules.set(rule.targetId, {
			id: rule.id,
			scope,
			targetId: rule.targetId,
			allowChildOverride: rule.allowChildOverride,
			value: buildValue(rule.value, undefined),
			canRemove: rule.canRemove,
		})
	}

	for (const rule of companionRules) {
		if (!rule.targetId) {
			continue
		}

		const existingRule = mergedRules.get(rule.targetId)
		if (!existingRule && !includeCompanionOnlyRules) {
			continue
		}

		mergedRules.set(rule.targetId, {
			id: existingRule?.id ?? rule.id,
			scope,
			targetId: rule.targetId,
			allowChildOverride: existingRule?.allowChildOverride ?? rule.allowChildOverride,
			value: buildValue(existingRule?.value, rule.value),
			canRemove: existingRule?.canRemove ?? rule.canRemove,
		})
	}

	return Array.from(mergedRules.values())
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
	const compoundPair = resolveCompoundPolicyPair(
		context.policyKey,
		context.value,
		context.collectMetadataEffectiveValue,
	)
	if (!compoundPair) {
		return { handled: false }
	}

	await saveCompoundPolicyPair(
		context.scope,
		context.policyKey,
		compoundPair,
		context.targetIds,
		context.allowChildOverride,
		context.policiesStore,
	)

	return {
		handled: true,
		savedValue: compoundPair.savedValue,
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

export function hydrateCompoundPolicyRules(context: CompoundPolicyHydrationContext): CompoundPolicyHydrationResult | null {
	const {
		policyKey,
		persistedSystemPolicy,
		companionSystemPolicy,
		persistedGroupPolicies,
		companionGroupPolicies,
		persistedUserPolicies,
		companionUserPolicies,
	} = context

	if (isRequestExpirationPolicyKey(policyKey)) {
		const primaryHasValue = hasPersistedValue(persistedSystemPolicy?.value)
		const companionHasValue = hasPersistedValue(companionSystemPolicy?.value)
		return {
			explicitSystemRule: (persistedSystemPolicy?.scope === 'global' || companionSystemPolicy?.scope === 'global') && (primaryHasValue || companionHasValue)
				? {
					id: 'system-default',
					scope: 'system',
					targetId: null,
					allowChildOverride: persistedSystemPolicy?.allowChildOverride ?? companionSystemPolicy?.allowChildOverride ?? true,
					value: buildRequestExpirationValue(persistedSystemPolicy?.value ?? undefined, companionSystemPolicy?.value ?? undefined),
				}
				: null,
			groupRules: mergeCompoundRulesByTarget(
				persistedGroupPolicies,
				companionGroupPolicies,
				'group',
				buildRequestExpirationValue,
			),
			userRules: mergeCompoundRulesByTarget(
				persistedUserPolicies,
				companionUserPolicies,
				'user',
				buildRequestExpirationValue,
			),
		}
	}

	if (isUnifiedSigningExecutionPolicyKey(policyKey)) {
		const primaryHasValue = hasPersistedValue(persistedSystemPolicy?.value)
		const companionHasValue = hasPersistedValue(companionSystemPolicy?.value)
		return {
			explicitSystemRule: (persistedSystemPolicy?.scope === 'global' || companionSystemPolicy?.scope === 'global') && (primaryHasValue || companionHasValue)
				? {
					id: 'system-default',
					scope: 'system',
					targetId: null,
					allowChildOverride: persistedSystemPolicy?.allowChildOverride ?? companionSystemPolicy?.allowChildOverride ?? true,
					value: buildSigningExecutionValue(persistedSystemPolicy?.value ?? undefined, companionSystemPolicy?.value ?? undefined),
				}
				: null,
			groupRules: mergeCompoundRulesByTarget(
				persistedGroupPolicies,
				companionGroupPolicies,
				'group',
				buildSigningExecutionValue,
			),
			userRules: mergeCompoundRulesByTarget(
				persistedUserPolicies,
				companionUserPolicies,
				'user',
				buildSigningExecutionValue,
			),
		}
	}

	if (isSignatureStampPolicyKey(policyKey)) {
		const primaryHasValue = hasPersistedValue(persistedSystemPolicy?.value)
		return {
			explicitSystemRule: (persistedSystemPolicy?.scope === 'global' || companionSystemPolicy?.scope === 'global') && primaryHasValue
				? {
					id: 'system-default',
					scope: 'system',
					targetId: null,
					allowChildOverride: persistedSystemPolicy?.allowChildOverride ?? companionSystemPolicy?.allowChildOverride ?? true,
					value: buildSignatureStampDraftValue(persistedSystemPolicy?.value ?? undefined, companionSystemPolicy?.value ?? undefined),
				}
				: null,
			groupRules: persistedGroupPolicies
				.filter((rule) => !!rule.targetId)
				.map((rule) => {
					const collectMetadataRule = companionGroupPolicies.find((metadataRule) => metadataRule.targetId === rule.targetId)
					return {
						id: rule.id,
						scope: 'group' as const,
						targetId: rule.targetId,
						allowChildOverride: rule.allowChildOverride,
						value: buildSignatureStampDraftValue(rule.value, collectMetadataRule?.value),
						canRemove: rule.canRemove,
					}
				}),
			userRules: persistedUserPolicies
				.filter((rule) => !!rule.targetId)
				.map((rule) => {
					const collectMetadataRule = companionUserPolicies.find((metadataRule) => metadataRule.targetId === rule.targetId)
					return {
						id: rule.id,
						scope: 'user' as const,
						targetId: rule.targetId,
						allowChildOverride: rule.allowChildOverride,
						value: buildSignatureStampDraftValue(rule.value, collectMetadataRule?.value),
					}
				}),
		}
	}

	return null
}
