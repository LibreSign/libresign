<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('libresign', 'Signing order')">
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>

		<div class="signature-flow-toggle">
			<NcCheckboxRadioSwitch type="switch"
				v-model="enabled"
				:disabled="loading"
				@update:modelValue="onToggleChange">
				<span>{{ t('libresign', 'Set default signing order') }}</span>
			</NcCheckboxRadioSwitch>
			<span v-if="loading && !flowChanging" class="toggle-status">
				<NcLoadingIcon :size="20" />
			</span>
			<span v-else-if="saved && !flowChanging" class="toggle-status">
				<NcSavingIndicatorIcon :size="20" />
			</span>
			<span v-else-if="showErrorIcon && !flowChanging" class="toggle-status">
				<NcSavingIndicatorIcon :size="20" error />
			</span>
		</div>

		<div v-if="enabled" class="signature-flow-options">
			<NcCheckboxRadioSwitch v-for="flow in availableFlows"
				:key="flow.value"
				type="radio"
				v-model="selectedFlowValue"
				:value="flow.value"
				:disabled="loading"
				name="signature_flow"
				@update:modelValue="onFlowChange">
				<div class="signature-flow-option">
					<div class="signature-flow-option-content">
						<strong>{{ flow.label }}</strong>
						<p class="signature-flow-option-description">
							{{ flow.description }}
						</p>
					</div>
					<div v-if="selectedFlow?.value === flow.value" class="signature-flow-option-status">
						<NcLoadingIcon v-if="loading && flowChanging" :size="20" />
						<NcSavingIndicatorIcon v-else-if="saved && flowChanging" :size="20" />
						<NcSavingIndicatorIcon v-else-if="showErrorIcon && flowChanging" :size="20" error />
					</div>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import type { operations } from '../../types/openapi/openapi-administration'

defineOptions({
	name: 'SignatureFlow',
})

type FlowOption = {
	value: SignatureFlowMode
	label: string
	description: string
}

type SignatureFlowMode = 'none' | 'parallel' | 'ordered_numeric'

type SignatureFlowRequestBody = operations['admin-set-signature-flow-config']['requestBody']['content']['application/json']
type SignatureFlowErrorResponse =
	| operations['admin-set-signature-flow-config']['responses'][400]['content']['application/json']
	| operations['admin-set-signature-flow-config']['responses'][500]['content']['application/json']

type SignatureFlowRequestError = {
	response?: {
		data?: SignatureFlowErrorResponse
	}
}

const enabled = ref(false)
const selectedFlow = ref<FlowOption | null>(null)
const loading = ref(false)
const errorMessage = ref('')
const saved = ref(false)
const showErrorIcon = ref(false)
const flowChanging = ref(false)

const availableFlows = computed<FlowOption[]>(() => [
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
])

const selectedFlowValue = computed({
	get() {
		return selectedFlow.value?.value ?? availableFlows.value[0].value
	},
	set(value: string) {
		selectedFlow.value = availableFlows.value.find(flow => flow.value === value) ?? availableFlows.value[0]
	},
})

function loadConfig() {
	try {
		const mode = loadState<SignatureFlowMode>('libresign', 'signature_flow', 'none')

		if (mode === 'none') {
			enabled.value = false
			selectedFlow.value = availableFlows.value[0]
		} else {
			enabled.value = true
			selectedFlow.value = availableFlows.value.find(flow => flow.value === mode) ?? availableFlows.value[0]
		}
	} catch (error) {
		console.error('Error loading signature flow configuration:', error)
		errorMessage.value = t('libresign', 'Could not load configuration.')
		enabled.value = false
		selectedFlow.value = availableFlows.value[0]
	}
}

function onToggleChange() {
	errorMessage.value = ''
	showErrorIcon.value = false
	flowChanging.value = false
	saveConfig()
}

function onFlowChange() {
	errorMessage.value = ''
	showErrorIcon.value = false
	flowChanging.value = true
	saveConfig()
}

function getErrorMessage(error: unknown): string | null {
	const requestError = error as SignatureFlowRequestError
	return requestError.response?.data?.ocs?.data?.error ?? null
}

async function saveConfig() {
	loading.value = true
	errorMessage.value = ''
	saved.value = false
	showErrorIcon.value = false

	try {
		const url = generateOcsUrl('apps/libresign/api/v1/admin/signature-flow/config')
		const payload: SignatureFlowRequestBody = {
			enabled: enabled.value,
			mode: enabled.value ? (selectedFlow.value?.value ?? 'parallel') : null,
		}
		await axios.post(url, payload)

		saved.value = true
		setTimeout(() => {
			saved.value = false
			flowChanging.value = false
		}, 3000)
	} catch (error: unknown) {
		console.error('Error saving signature flow configuration:', error)
		errorMessage.value = getErrorMessage(error) ?? t('libresign', 'Error saving configuration.')
		showErrorIcon.value = true
	} finally {
		loading.value = false
	}
}

onMounted(() => {
	loadConfig()
})

defineExpose({
	enabled,
	selectedFlow,
	loading,
	errorMessage,
	saved,
	showErrorIcon,
	flowChanging,
	availableFlows,
	selectedFlowValue,
	loadConfig,
	onToggleChange,
	onFlowChange,
	saveConfig,
})
</script>

<style lang="scss" scoped>
.signature-flow-toggle {
	margin-bottom: 1.5rem;
	display: flex;
	align-items: center;
	gap: 0.5rem;

	:deep(.checkbox-radio-switch) {
		flex-shrink: 0;
	}

	.toggle-status {
		display: flex;
		align-items: center;
		flex-shrink: 0;
	}
}

.signature-flow-options {
	margin-top: 0.5rem;
	display: flex;
	flex-direction: column;
	gap: 0.5rem;

	.signature-flow-option {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 1rem;
		width: 100%;

		&-content {
			flex: 1;
		}

		&-description {
			margin: 0.25rem 0 0 0;
			color: var(--color-text-maxcontrast);
			font-size: 90%;
		}

		&-status {
			flex-shrink: 0;
			display: flex;
			align-items: center;
		}
	}
}
</style>
