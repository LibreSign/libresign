<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="preferences-view">
		<NcSettingsSection
			v-for="entry in preferenceEntries"
			:key="entry.definition.key"
			:name="entry.definition.title"
			:description="entry.definition.description">
			<NcNoteCard v-if="entry.policy?.preferenceWasCleared" type="info">
				{{ t('libresign', 'A previously saved preference was cleared because it is no longer compatible with a higher-level policy.') }}
			</NcNoteCard>

			<NcNoteCard v-if="errorMessage" type="error">
				{{ errorMessage }}
			</NcNoteCard>

			<div class="preferences-view__summary">
				<div>
					<strong>{{ t('libresign', 'Effective value') }}</strong>
					<p>{{ summarizeEffectiveValue(entry.definition.key) }}</p>
				</div>
				<div>
					<strong>{{ t('libresign', 'Source') }}</strong>
					<p>{{ sourceLabelFor(entry.policy) }}</p>
				</div>
			</div>

			<NcNoteCard v-if="!canSavePreferenceFor(entry.definition.key)" type="info">
				{{ t('libresign', 'Your current context does not allow saving a personal default for this setting.') }}
			</NcNoteCard>

			<div v-else class="preferences-view__options">
				<component
					:is="entry.definition.editor"
					:model-value="selectedPreferenceValues[entry.definition.key]"
					v-bind="editorPropsFor(entry.definition.key)"
					@update:modelValue="(value: EffectivePolicyValue) => onPreferenceChange(entry.definition.key, value)" />

				<div v-if="!shouldAutoSavePreferenceFor(entry.definition.key)" class="preferences-view__actions">
					<NcButton
						:variant="hasSavedPreferenceFor(entry.definition.key) ? 'secondary' : 'primary'"
						:disabled="saving"
						@click="savePreferenceByKey(entry.definition.key, selectedPreferenceValues[entry.definition.key])">
						{{ hasSavedPreferenceFor(entry.definition.key) ? t('libresign', 'Update saved preference') : t('libresign', 'Save as my default') }}
					</NcButton>
					<NcButton
						v-if="hasSavedPreferenceFor(entry.definition.key)"
						variant="secondary"
						:disabled="saving"
						@click="clearPreferenceByKey(entry.definition.key)">
						{{ t('libresign', 'Clear saved preference') }}
					</NcButton>
				</div>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../store/policies'
import type { EffectivePolicyState, EffectivePolicyValue, SignatureFlowMode } from '../../types/index'
import { realDefinitions } from '../Settings/PolicyWorkbench/settings/realDefinitions'

defineOptions({
	name: 'Preferences',
})

const policiesStore = usePoliciesStore()
const canRequestSign = loadState<boolean>('libresign', 'can_request_sign', false)
const saving = ref(false)
const errorMessage = ref('')
const selectedPreferenceValues = reactive<Record<string, EffectivePolicyValue>>({})

const preferencePolicyKeys = computed(() => {
	const policyKeysFromApi = Object.keys(policiesStore.policies ?? {})
	if (policyKeysFromApi.length > 0) {
		return policyKeysFromApi
	}

	return Object.keys(realDefinitions)
})

const preferenceEntries = computed(() => {
	return preferencePolicyKeys.value
		.filter((policyKey) => shouldRenderPreferencePolicy(policyKey))
		.map((policyKey) => realDefinitions[policyKey as keyof typeof realDefinitions])
		.filter((definition): definition is (typeof realDefinitions)[keyof typeof realDefinitions] => Boolean(definition))
		.map((definition) => ({
		definition,
		policy: policiesStore.getPolicy(definition.key),
		}))
})

function shouldRenderPreferencePolicy(policyKey: string): boolean {
	if (!canRequestSign) {
		return false
	}

	if (!realDefinitions[policyKey as keyof typeof realDefinitions]) {
		return false
	}

	const policy = policiesStore.getPolicy(policyKey)
	if (!policy) {
		return false
	}

	return policy.canSaveAsUserDefault || policy.sourceScope === 'user'
}

function sourceLabelFor(policy: EffectivePolicyState | null): string {
	switch (policy?.sourceScope) {
	case 'user':
		return t('libresign', 'Your saved preference')
	case 'user_policy':
		return t('libresign', 'Assigned user policy')
	case 'group':
		return t('libresign', 'Group policy')
	case 'request':
		return t('libresign', 'Request override')
	case 'system':
	default:
		return t('libresign', 'Global default')
	}
}

function summarizeEffectiveValue(policyKey: string): string {
	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	const policy = policiesStore.getPolicy(policyKey)
	if (!definition || !policy) {
		return t('libresign', 'Not configured')
	}

	return definition.summarizeValue(policy.effectiveValue)
}

function canSavePreferenceFor(policyKey: string): boolean {
	return policiesStore.getPolicy(policyKey)?.canSaveAsUserDefault ?? false
}

function hasSavedPreferenceFor(policyKey: string): boolean {
	return policiesStore.getPolicy(policyKey)?.sourceScope === 'user'
}

function syncSelectedPreference(policyKey: string): void {
	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	if (!definition) {
		return
	}

	const policy = policiesStore.getPolicy(policyKey)
	selectedPreferenceValues[policyKey] = definition.normalizeDraftValue(policy?.effectiveValue ?? null)
}

function syncAllSelectedPreferences(): void {
	for (const entry of preferenceEntries.value) {
		syncSelectedPreference(entry.definition.key)
	}
}

function editorPropsFor(policyKey: string): Record<string, unknown> {
	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	const baseEditorProps = definition?.editorProps ?? {}
	const resolvedEditorProps = definition?.resolveEditorProps?.(policiesStore.getPolicy(policyKey), baseEditorProps) ?? baseEditorProps

	return {
		...resolvedEditorProps,
		editorScope: 'user',
		editorMode: 'edit',
	}
}

function shouldAutoSavePreferenceFor(policyKey: string): boolean {
	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	return (definition?.editorProps as Record<string, unknown> | undefined)?.preferenceAutoSave === true
}

function onPreferenceChange(policyKey: string, value: EffectivePolicyValue): void {
	selectedPreferenceValues[policyKey] = value
	if (shouldAutoSavePreferenceFor(policyKey) && canSavePreferenceFor(policyKey)) {
		void savePreferenceByKey(policyKey, value)
		return
	}

	if (hasSavedPreferenceFor(policyKey)) {
		void savePreferenceByKey(policyKey, value)
	}
}

async function savePreferenceByKey(policyKey: string, value: EffectivePolicyValue): Promise<void> {
	await savePreferenceValue(policyKey, value, t('libresign', 'Could not save your preference. Try again.'))
	syncSelectedPreference(policyKey)
}

async function clearPreferenceByKey(policyKey: string): Promise<void> {
	await clearPreferenceValue(policyKey, t('libresign', 'Could not clear your preference. Try again.'))
	syncSelectedPreference(policyKey)
}

// Backward-compatible helpers used by existing tests.
async function savePreference(flow: SignatureFlowMode): Promise<void> {
	await savePreferenceByKey('signature_flow', flow)
}

async function clearPreference(): Promise<void> {
	await clearPreferenceByKey('signature_flow')
}

async function savePreferenceValue(policyKey: string, value: EffectivePolicyValue, errorText: string): Promise<void> {
	const policy = policiesStore.getPolicy(policyKey)
	if (!policy?.canSaveAsUserDefault) {
		return
	}

	saving.value = true
	errorMessage.value = ''
	try {
		await policiesStore.saveUserPreference(policyKey, value)
	} catch (error) {
		console.error(`Failed to save ${policyKey} preference`, error)
		errorMessage.value = errorText
	} finally {
		saving.value = false
	}
}

async function clearPreferenceValue(policyKey: string, errorText: string): Promise<void> {
	saving.value = true
	errorMessage.value = ''
	try {
		await policiesStore.clearUserPreference(policyKey)
	} catch (error) {
		console.error(`Failed to clear ${policyKey} preference`, error)
		errorMessage.value = errorText
	} finally {
		saving.value = false
	}
}

onMounted(async () => {
	await policiesStore.fetchEffectivePolicies()
	syncAllSelectedPreferences()
})

defineExpose({
	canSavePreferenceFor,
	clearPreference,
	errorMessage,
	onPreferenceChange,
	savePreference,
	sourceLabelFor,
	selectedPreferenceValues,
	preferenceEntries,
	summarizeEffectiveValue,
	syncSelectedPreference,
	syncAllSelectedPreferences,
})
</script>

<style scoped lang="scss">
.preferences-view {
	padding: 24px;

	&__summary {
		display: grid;
		gap: 16px;
		grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
		margin-bottom: 20px;
	}

	&__options {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	&__option-copy p {
		margin: 4px 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
		margin-top: 8px;
	}
}
</style>
