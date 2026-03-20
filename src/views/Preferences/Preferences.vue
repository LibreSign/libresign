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
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import { usePoliciesStore } from '../../store/policies'
import type { EffectivePolicyState, SignatureFlowMode } from '../../types/index'

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

const selectedValue = ref<SignatureFlowMode>('parallel')

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

onMounted(async () => {
	await policiesStore.fetchEffectivePolicies()
	syncSelectedValue()
})

defineExpose({
	availableFlows,
	canSavePreference,
	clearPreference,
	effectiveLabel,
	errorMessage,
	hasSavedPreference,
	onPreferenceChange,
	preferenceCleared,
	savePreference,
	selectedValue,
	signatureFlowPolicy,
	sourceLabel,
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