<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="preferences-view">
		<NcSettingsSection
			:name="t('libresign', 'Preferences')"
			:description="t('libresign', 'Save your default signing order for new signature requests when higher-level policies allow it.')">
			<NcNoteCard v-if="preferenceCleared" type="info">
				{{ t('libresign', 'A previously saved signing order preference was cleared because it is no longer compatible with a higher-level policy.') }}
			</NcNoteCard>

			<NcNoteCard v-if="errorMessage" type="error">
				{{ errorMessage }}
			</NcNoteCard>

			<div class="preferences-view__summary">
				<div>
					<strong>{{ t('libresign', 'Effective signing order') }}</strong>
					<p>{{ effectiveLabel }}</p>
				</div>
				<div>
					<strong>{{ t('libresign', 'Source') }}</strong>
					<p>{{ sourceLabel }}</p>
				</div>
			</div>

			<NcNoteCard v-if="!canSavePreference" type="info">
				{{ t('libresign', 'Your current context does not allow saving a personal default for signing order. The effective value above is still applied when you create requests.') }}
			</NcNoteCard>

			<div v-else class="preferences-view__options">
				<NcCheckboxRadioSwitch
					v-for="flow in availableFlows"
					:key="flow.value"
					type="radio"
					:model-value="selectedValue"
					:value="flow.value"
					:disabled="saving"
					name="signature_flow_preference"
					@update:modelValue="onPreferenceChange(flow.value)">
					<div class="preferences-view__option-copy">
						<strong>{{ flow.label }}</strong>
						<p>{{ flow.description }}</p>
					</div>
				</NcCheckboxRadioSwitch>

				<div class="preferences-view__actions">
					<NcButton
						:variant="hasSavedPreference ? 'secondary' : 'primary'"
						:disabled="saving"
						@click="savePreference(selectedValue)">
						{{ hasSavedPreference ? t('libresign', 'Update saved preference') : t('libresign', 'Save as my default') }}
					</NcButton>
					<NcButton
						v-if="hasSavedPreference"
						variant="secondary"
						:disabled="saving"
						@click="clearPreference">
						{{ t('libresign', 'Clear saved preference') }}
					</NcButton>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection
			:name="t('libresign', 'Signature footer preferences')"
			:description="t('libresign', 'Save your personal footer defaults when higher-level policies allow customization.')">
			<NcNoteCard v-if="!canSaveFooterPreference" type="info">
				{{ t('libresign', 'Your current context does not allow saving personal footer preferences.') }}
			</NcNoteCard>

			<div v-else class="preferences-view__options">
				<SignatureFooterRuleEditor
					:model-value="selectedFooterValue"
					@update:modelValue="onFooterPreferenceChange" />

				<div class="preferences-view__actions">
					<NcButton
						:variant="hasSavedFooterPreference ? 'secondary' : 'primary'"
						:disabled="saving"
						@click="saveFooterPreference(selectedFooterValue)">
						{{ hasSavedFooterPreference ? t('libresign', 'Update footer preference') : t('libresign', 'Save footer preference') }}
					</NcButton>
					<NcButton
						v-if="hasSavedFooterPreference"
						variant="secondary"
						:disabled="saving"
						@click="clearFooterPreference">
						{{ t('libresign', 'Clear footer preference') }}
					</NcButton>
				</div>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import SignatureFooterRuleEditor from '../Settings/PolicyWorkbench/settings/signature-footer/SignatureFooterRuleEditor.vue'
import { usePoliciesStore } from '../../store/policies'
import type { EffectivePolicyState, EffectivePolicyValue, SignatureFlowMode } from '../../types/index'
import {
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
} from '../Settings/PolicyWorkbench/settings/signature-footer/model'

defineOptions({
	name: 'Preferences',
})

type FlowOption = {
	value: SignatureFlowMode
	label: string
	description: string
}

const policiesStore = usePoliciesStore()
const saving = ref(false)
const errorMessage = ref('')

const availableFlows: FlowOption[] = [
	{
		value: 'parallel',
		label: t('libresign', 'Simultaneous (Parallel)'),
		description: t('libresign', 'All signers receive the document at the same time and can sign in any order.'),
	},
	{
		value: 'ordered_numeric',
		label: t('libresign', 'Sequential'),
		description: t('libresign', 'Signers are organized by signing order number. Only those with the lowest pending order number can sign.'),
	},
]

const signatureFlowPolicy = computed<EffectivePolicyState | null>(() => policiesStore.getPolicy('signature_flow'))
const canSavePreference = computed(() => signatureFlowPolicy.value?.canSaveAsUserDefault ?? false)
const hasSavedPreference = computed(() => signatureFlowPolicy.value?.sourceScope === 'user')
const preferenceCleared = computed(() => signatureFlowPolicy.value?.preferenceWasCleared ?? false)

const footerPolicy = computed<EffectivePolicyState | null>(() => policiesStore.getPolicy('add_footer'))
const canSaveFooterPreference = computed(() => footerPolicy.value?.canSaveAsUserDefault ?? false)
const hasSavedFooterPreference = computed(() => footerPolicy.value?.sourceScope === 'user')

const selectedValue = ref<SignatureFlowMode>('parallel')
const selectedFooterValue = ref<EffectivePolicyValue>(serializeSignatureFooterPolicyConfig(normalizeSignatureFooterPolicyConfig(null)))

const effectiveLabel = computed(() => {
	if (signatureFlowPolicy.value?.effectiveValue === 'ordered_numeric') {
		return t('libresign', 'Sequential')
	}
	return t('libresign', 'Simultaneous (Parallel)')
})

const sourceLabel = computed(() => {
	switch (signatureFlowPolicy.value?.sourceScope) {
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
})

function syncSelectedValue(): void {
	selectedValue.value = signatureFlowPolicy.value?.effectiveValue === 'ordered_numeric'
		? 'ordered_numeric'
		: 'parallel'
}

function syncSelectedFooterValue(): void {
	selectedFooterValue.value = serializeSignatureFooterPolicyConfig(
		normalizeSignatureFooterPolicyConfig(footerPolicy.value?.effectiveValue ?? null),
	)
}

async function savePreference(flow: SignatureFlowMode): Promise<void> {
	if (!canSavePreference.value) {
		return
	}

	saving.value = true
	errorMessage.value = ''
	try {
		await policiesStore.saveUserPreference('signature_flow', flow)
		syncSelectedValue()
	} catch (error) {
		console.error('Failed to save signing order preference', error)
		errorMessage.value = t('libresign', 'Could not save your signing order preference. Try again.')
	} finally {
		saving.value = false
	}
}

async function clearPreference(): Promise<void> {
	saving.value = true
	errorMessage.value = ''
	try {
		await policiesStore.clearUserPreference('signature_flow')
		syncSelectedValue()
	} catch (error) {
		console.error('Failed to clear signing order preference', error)
		errorMessage.value = t('libresign', 'Could not clear your signing order preference. Try again.')
	} finally {
		saving.value = false
	}
}

function onPreferenceChange(flow: SignatureFlowMode): void {
	selectedValue.value = flow
	if (hasSavedPreference.value) {
		void savePreference(flow)
	}
}

function onFooterPreferenceChange(value: EffectivePolicyValue): void {
	selectedFooterValue.value = value
	if (hasSavedFooterPreference.value) {
		void saveFooterPreference(value)
	}
}

async function saveFooterPreference(value: EffectivePolicyValue): Promise<void> {
	if (!canSaveFooterPreference.value) {
		return
	}

	saving.value = true
	errorMessage.value = ''
	try {
		await policiesStore.saveUserPreference('add_footer', value)
		syncSelectedFooterValue()
	} catch (error) {
		console.error('Failed to save footer preference', error)
		errorMessage.value = t('libresign', 'Could not save your footer preference. Try again.')
	} finally {
		saving.value = false
	}
}

async function clearFooterPreference(): Promise<void> {
	saving.value = true
	errorMessage.value = ''
	try {
		await policiesStore.clearUserPreference('add_footer')
		syncSelectedFooterValue()
	} catch (error) {
		console.error('Failed to clear footer preference', error)
		errorMessage.value = t('libresign', 'Could not clear your footer preference. Try again.')
	} finally {
		saving.value = false
	}
}

onMounted(async () => {
	await policiesStore.fetchEffectivePolicies()
	syncSelectedValue()
	syncSelectedFooterValue()
})

defineExpose({
	availableFlows,
	canSavePreference,
	canSaveFooterPreference,
	clearPreference,
	clearFooterPreference,
	effectiveLabel,
	errorMessage,
	footerPolicy,
	hasSavedPreference,
	hasSavedFooterPreference,
	onPreferenceChange,
	onFooterPreferenceChange,
	preferenceCleared,
	savePreference,
	saveFooterPreference,
	selectedValue,
	selectedFooterValue,
	signatureFlowPolicy,
	sourceLabel,
	syncSelectedFooterValue,
	syncSelectedValue,
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
