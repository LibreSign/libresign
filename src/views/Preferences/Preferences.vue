<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="preferencesReady" class="preferences-view">
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

			<NcNoteCard v-if="!canSavePreferenceFor(entry.definition.key)" type="info">
				{{ t('libresign', 'Your current context does not allow saving a personal default for this setting.') }}
			</NcNoteCard>

			<div v-else class="preferences-view__options">
				<div
					v-if="canUndoAutoSaveFor(entry.definition.key)"
					class="preferences-view__undo-row">
					<NcButton
						variant="tertiary"
						:disabled="saving"
						@click="undoAutoSaveByKey(entry.definition.key)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
						</template>
						{{ undoLabelFor(entry.definition.key) }}
					</NcButton>
				</div>

				<div class="preferences-view__editor-shell" :class="{ 'preferences-view__editor-shell--saved': isAutoSaveSavedFor(entry.definition.key) }">
					<div
						v-if="isAutoSaveSavingFor(entry.definition.key) || isAutoSaveSavedFor(entry.definition.key)"
						class="preferences-view__autosave-status"
						:class="{ 'preferences-view__autosave-status--saved': isAutoSaveSavedFor(entry.definition.key) }"
						role="status"
						aria-live="polite">
						<NcLoadingIcon v-if="isAutoSaveSavingFor(entry.definition.key)" :size="16" />
						<NcIconSvgWrapper v-else :path="mdiCheckCircleOutline" :size="16" />
						<span>
							{{ isAutoSaveSavingFor(entry.definition.key) ? t('libresign', 'Saving your preference...') : t('libresign', 'Preference saved') }}
						</span>
					</div>

					<component
						:is="entry.definition.editor"
						:model-value="selectedPreferenceValues[entry.definition.key]"
						v-bind="editorPropsFor(entry.definition.key)"
						@update:modelValue="(value) => onPreferenceChange(entry.definition.key, value)" />
				</div>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'

import { t } from '@nextcloud/l10n'
import { mdiCheckCircleOutline, mdiUndoVariant } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../store/policies'
import type { EffectivePolicyValue, SignatureFlowMode } from '../../types/index'
import { realDefinitions } from '../Settings/PolicyWorkbench/settings/realDefinitions'
import type { RealPolicyPersonalPreferenceContext } from '../Settings/PolicyWorkbench/settings/realTypes'
import { canRenderPersonalPreferencePolicy } from './personalPreferenceVisibility'

defineOptions({
	name: 'Preferences',
})

const policiesStore = usePoliciesStore()
const preferencesReady = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const selectedPreferenceValues = reactive<Record<string, EffectivePolicyValue>>({})
const autoSaveSavingByKey = reactive<Record<string, boolean>>({})
const autoSaveSavedByKey = reactive<Record<string, boolean>>({})
const autoSaveFeedbackTimers = new Map<string, ReturnType<typeof setTimeout>>()
const preferenceBehaviorContext: RealPolicyPersonalPreferenceContext = {
	getPolicy: (policyKey: string) => policiesStore.getPolicy(policyKey),
	saveUserPreference: (policyKey: string, value: EffectivePolicyValue) => policiesStore.saveUserPreference(policyKey, value),
	clearUserPreference: (policyKey: string) => policiesStore.clearUserPreference(policyKey),
}

function getPreferenceBehaviorFor(policyKey: string) {
	return realDefinitions[policyKey as keyof typeof realDefinitions]?.personalPreferenceBehavior
}

function getResolvedPreferencePolicy(policyKey: string) {
	const policy = policiesStore.getPolicy(policyKey)
	const behavior = getPreferenceBehaviorFor(policyKey)
	return behavior?.resolvePolicy?.(policy, preferenceBehaviorContext) ?? policy
}

const preferencePolicyKeys = computed(() => {
	const policyKeysFromApi = Object.keys(policiesStore.policies ?? {})
	if (policyKeysFromApi.length > 0) {
		return policyKeysFromApi
	}

	return Object.keys(realDefinitions)
})

const preferenceEntries = computed(() => {
	return preferencePolicyKeys.value
		.filter((policyKey: string) => shouldRenderPreferencePolicy(policyKey))
		.map((policyKey: string) => realDefinitions[policyKey as keyof typeof realDefinitions])
		.filter((definition: (typeof realDefinitions)[keyof typeof realDefinitions] | undefined): definition is (typeof realDefinitions)[keyof typeof realDefinitions] => Boolean(definition))
		.map((definition: (typeof realDefinitions)[keyof typeof realDefinitions]) => ({
		definition,
		policy: getResolvedPreferencePolicy(definition.key),
		}))
})

function shouldRenderPreferencePolicy(policyKey: string): boolean {
	const policy = policiesStore.getPolicy(policyKey)
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.shouldRender) {
		return behavior.shouldRender(policy, preferenceBehaviorContext)
	}

	return canRenderPersonalPreferencePolicy(policyKey, policy)
}

function canSavePreferenceFor(policyKey: string): boolean {
	const policy = policiesStore.getPolicy(policyKey)
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.canSave) {
		return behavior.canSave(policy, preferenceBehaviorContext)
	}

	return policy?.canSaveAsUserDefault ?? false
}

function hasSavedPreferenceFor(policyKey: string): boolean {
	const policy = policiesStore.getPolicy(policyKey)
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.hasSavedPreference) {
		return behavior.hasSavedPreference(policy, preferenceBehaviorContext)
	}

	return policy?.sourceScope === 'user'
}

function syncSelectedPreference(policyKey: string): void {
	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	if (!definition) {
		return
	}

	const policy = policiesStore.getPolicy(policyKey)
	const behavior = getPreferenceBehaviorFor(policyKey)
	selectedPreferenceValues[policyKey] = behavior?.resolveSelectedValue?.(policy, preferenceBehaviorContext)
		?? definition.normalizeDraftValue(policy?.effectiveValue ?? null)
}

function syncAllSelectedPreferences(): void {
	for (const entry of preferenceEntries.value) {
		syncSelectedPreference(entry.definition.key)
	}
}

function editorPropsFor(policyKey: string): Record<string, unknown> {
	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	const baseEditorProps = definition?.editorProps ?? {}
	const resolvedEditorProps = definition?.resolveEditorProps?.(getResolvedPreferencePolicy(policyKey), baseEditorProps) ?? baseEditorProps

	return {
		...resolvedEditorProps,
		editorScope: 'user',
		editorMode: 'edit',
	}
}

function isAutoSaveSavingFor(policyKey: string): boolean {
	return autoSaveSavingByKey[policyKey] === true
}

function isAutoSaveSavedFor(policyKey: string): boolean {
	return autoSaveSavedByKey[policyKey] === true
}

function setAutoSaveSavedFeedback(policyKey: string): void {
	autoSaveSavedByKey[policyKey] = true
	const existingTimer = autoSaveFeedbackTimers.get(policyKey)
	if (existingTimer) {
		clearTimeout(existingTimer)
	}

	const timer = setTimeout(() => {
		autoSaveSavedByKey[policyKey] = false
		autoSaveFeedbackTimers.delete(policyKey)
	}, 2000)
	autoSaveFeedbackTimers.set(policyKey, timer)
}

function canUndoAutoSaveFor(policyKey: string): boolean {
	return hasSavedPreferenceFor(policyKey)
}

function undoLabelFor(policyKey: string): string {
	void policyKey
	return t('libresign', 'Reset to default')
}

function normalizePreferenceValue(policyKey: string, value: EffectivePolicyValue): EffectivePolicyValue {
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.normalizeValue) {
		return behavior.normalizeValue(value, preferenceBehaviorContext)
	}

	const definition = realDefinitions[policyKey as keyof typeof realDefinitions]
	if (!definition) {
		return value
	}

	return definition.normalizeDraftValue(value)
}

function getEffectivePreferenceValue(policyKey: string): EffectivePolicyValue {
	const policy = policiesStore.getPolicy(policyKey)
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.getEffectiveValue) {
		return behavior.getEffectiveValue(policy, preferenceBehaviorContext)
	}

	return policy?.effectiveValue ?? null
}

function arePreferenceValuesEqual(left: EffectivePolicyValue, right: EffectivePolicyValue): boolean {
	if (typeof left === 'object' && left !== null && typeof right === 'object' && right !== null) {
		return JSON.stringify(left) === JSON.stringify(right)
	}

	return left === right
}

function onPreferenceChange(policyKey: string, value: EffectivePolicyValue): void {
	const normalizedNextValue = normalizePreferenceValue(policyKey, value)
	const normalizedSelectedValue = normalizePreferenceValue(policyKey, selectedPreferenceValues[policyKey] ?? null)
	const normalizedEffectiveValue = normalizePreferenceValue(policyKey, getEffectivePreferenceValue(policyKey))

	if (!preferencesReady.value) {
		selectedPreferenceValues[policyKey] = normalizedNextValue
		return
	}

	if (arePreferenceValuesEqual(normalizedSelectedValue, normalizedNextValue)
		|| arePreferenceValuesEqual(normalizedEffectiveValue, normalizedNextValue)) {
		selectedPreferenceValues[policyKey] = normalizedNextValue
		return
	}

	selectedPreferenceValues[policyKey] = normalizedNextValue
	if (canSavePreferenceFor(policyKey)) {
		void savePreferenceByKey(policyKey, normalizedNextValue)
	}
}

async function savePreferenceByKey(policyKey: string, value: EffectivePolicyValue): Promise<void> {
	autoSaveSavingByKey[policyKey] = canSavePreferenceFor(policyKey)
	autoSaveSavedByKey[policyKey] = false
	const saved = await savePreferenceValue(policyKey, value, t('libresign', 'Could not save your preference. Try again.'))
	autoSaveSavingByKey[policyKey] = false
	if (saved && canSavePreferenceFor(policyKey)) {
		setAutoSaveSavedFeedback(policyKey)
	}
	syncSelectedPreference(policyKey)
}

async function clearPreferenceByKey(policyKey: string): Promise<void> {
	await clearPreferenceValue(policyKey, t('libresign', 'Could not clear your preference. Try again.'))
	await policiesStore.fetchEffectivePolicies()
	syncSelectedPreference(policyKey)
}

async function undoAutoSaveByKey(policyKey: string): Promise<void> {
	autoSaveSavedByKey[policyKey] = false
	if (hasSavedPreferenceFor(policyKey)) {
		await clearPreferenceByKey(policyKey)
	}
}

// Backward-compatible helpers used by existing tests.
async function savePreference(flow: SignatureFlowMode): Promise<void> {
	await savePreferenceByKey('signature_flow', flow)
}

async function clearPreference(): Promise<void> {
	await clearPreferenceByKey('signature_flow')
}

async function savePreferenceValue(policyKey: string, value: EffectivePolicyValue, errorText: string): Promise<boolean> {
	if (!canSavePreferenceFor(policyKey)) {
		return false
	}

	saving.value = true
	errorMessage.value = ''
	try {
		await persistPreferenceValue(policyKey, value)
		return true
	} catch (error) {
		console.error(`Failed to save ${policyKey} preference`, error)
		errorMessage.value = errorText
		return false
	} finally {
		saving.value = false
	}
}

async function persistPreferenceValue(policyKey: string, value: EffectivePolicyValue): Promise<void> {
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.savePreference) {
		await behavior.savePreference(value, preferenceBehaviorContext)
		return
	}

	await policiesStore.saveUserPreference(policyKey, value)
}

async function clearPreferenceValue(policyKey: string, errorText: string): Promise<void> {
	saving.value = true
	errorMessage.value = ''
	try {
		await clearPersistedPreferenceValue(policyKey)
	} catch (error) {
		console.error(`Failed to clear ${policyKey} preference`, error)
		errorMessage.value = errorText
	} finally {
		saving.value = false
	}
}


async function clearPersistedPreferenceValue(policyKey: string): Promise<void> {
	const behavior = getPreferenceBehaviorFor(policyKey)
	if (behavior?.clearPreference) {
		await behavior.clearPreference(preferenceBehaviorContext)
		return
	}

	await policiesStore.clearUserPreference(policyKey)
}

syncAllSelectedPreferences()

onMounted(async () => {
	try {
		await policiesStore.fetchEffectivePolicies()
		syncAllSelectedPreferences()
	} finally {
		preferencesReady.value = true
	}
})

onBeforeUnmount(() => {
	for (const timer of autoSaveFeedbackTimers.values()) {
		clearTimeout(timer)
	}
	autoSaveFeedbackTimers.clear()
})

defineExpose({
	canSavePreferenceFor,
	clearPreference,
	errorMessage,
	onPreferenceChange,
	savePreference,
	selectedPreferenceValues,
	preferenceEntries,
	preferencesReady,
	canUndoAutoSaveFor,
	undoLabelFor,
	isAutoSaveSavedFor,
	isAutoSaveSavingFor,
	syncSelectedPreference,
	syncAllSelectedPreferences,
	undoAutoSaveByKey,
})
</script>

<style scoped lang="scss">
.preferences-view {
	padding: 24px;

	&__options {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__undo-row {
		display: flex;
		justify-content: flex-end;
	}

	&__editor-shell {
		position: relative;
		border-radius: 10px;
		padding: 2px;
		transition: box-shadow 180ms ease;

		&--saved {
			box-shadow: 0 0 0 2px var(--color-border-success);
		}
	}

	&__autosave-status {
		position: absolute;
		top: 8px;
		right: 8px;
		z-index: 2;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 4px 8px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--color-main-background) 86%, transparent);
		border: 1px solid var(--color-border-dark);
		color: var(--color-text-maxcontrast);
		pointer-events: none;
		font-size: 0.78rem;
		line-height: 1.2;

		&--saved {
			border-color: var(--color-border-success);
			color: var(--color-success-text);
		}
	}

	&__option-copy p {
		margin: 4px 0 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
