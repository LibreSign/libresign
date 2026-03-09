<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('libresign', 'Signing mode')">
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>

		<div class="signing-mode-toggle">
			<NcCheckboxRadioSwitch type="switch"
				:model-value="asyncEnabled"
				:disabled="loading"
				@update:modelValue="onToggleChange">
				<span>{{ t('libresign', 'Sign documents asynchronously in the background') }}</span>
			</NcCheckboxRadioSwitch>
			<span v-if="loading" class="toggle-status">
				<NcLoadingIcon :size="20" />
			</span>
			<span v-else-if="saved" class="toggle-status">
				<NcSavingIndicatorIcon :size="20" />
			</span>
			<span v-else-if="showErrorIcon" class="toggle-status">
				<NcSavingIndicatorIcon :size="20" error />
			</span>
		</div>

		<div v-if="asyncEnabled" class="worker-type-toggle">
			<NcCheckboxRadioSwitch type="switch"
				:model-value="externalWorkerEnabled"
				:disabled="loading"
				@update:modelValue="onWorkerTypeChange">
				<span>{{ t('libresign', 'Use external worker service') }}</span>
			</NcCheckboxRadioSwitch>
			<p class="worker-type-description">
				{{ externalWorkerEnabled
					? t('libresign', 'You must manage and keep the external worker running manually.')
					: t('libresign', 'Nextcloud manages the background worker automatically.')
				}}
			</p>
		</div>

		<div v-if="asyncEnabled && !externalWorkerEnabled" class="parallel-workers-section">
			<p class="parallel-workers-description">
				{{ t('libresign', 'Configure how many background workers should process signing jobs in parallel. More workers increase throughput but consume more resources. Valid range: 1-32.') }}
			</p>
			<div class="parallel-workers-input-wrapper">
				<NcTextField
					id="parallel-workers-input"
					:label="t('libresign', 'Number of parallel workers')"
					type="number"
					min="1"
					max="32"
					v-model="parallelWorkersCount"
					:disabled="loading"
					:placeholder="t('libresign', 'Default: {workers} workers', { workers: 4 })"
					@input="debouncedSaveParallelWorkers"
					@keydown.enter="debouncedSaveParallelWorkers"
					@blur="debouncedSaveParallelWorkers">
				</NcTextField>
				<span v-if="saved" class="workers-status">
					<NcSavingIndicatorIcon :size="20" />
				</span>
				<span v-else-if="showErrorIcon" class="workers-status">
					<NcSavingIndicatorIcon :size="20" error />
				</span>
			</div>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

type SaveConfigError = {
	response?: {
		data?: {
			ocs?: {
				data?: {
					error?: string
				}
			}
		}
	}
}

type AppConfigCallbacks = {
	success?: () => void
	error?: (error: unknown) => void
}

type OcpGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: string, callbacks?: AppConfigCallbacks) => void
	}
}

defineOptions({
	name: 'SigningMode',
})

type SigningModeState = 'sync' | 'async'
type WorkerTypeState = 'local' | 'external'

const asyncEnabled = ref(false)
const externalWorkerEnabled = ref(false)
const parallelWorkersCount = ref('4')
const lastSavedParallelWorkers = ref('4')
const loading = ref(false)
const errorMessage = ref('')
const saved = ref(false)
const showErrorIcon = ref(false)

function showSavedIndicator() {
	saved.value = true
	setTimeout(() => {
		saved.value = false
	}, 3000)
}

function loadConfig() {
	try {
		const mode = loadState<SigningModeState>('libresign', 'signing_mode', 'sync')
		asyncEnabled.value = mode === 'async'

		const workerType = loadState<WorkerTypeState>('libresign', 'worker_type', 'local')
		externalWorkerEnabled.value = workerType === 'external'

		const parallelWorkers = loadState('libresign', 'parallel_workers', '4')
		parallelWorkersCount.value = String(parallelWorkers)
		lastSavedParallelWorkers.value = parallelWorkersCount.value
	} catch (error) {
		console.error('Error loading signing mode configuration:', error)
		errorMessage.value = t('libresign', 'Could not load configuration.')
		asyncEnabled.value = false
		externalWorkerEnabled.value = false
		parallelWorkersCount.value = '4'
		lastSavedParallelWorkers.value = '4'
	}
}

async function saveConfig() {
	loading.value = true
	errorMessage.value = ''
	saved.value = false

	try {
		const url = generateOcsUrl('apps/libresign/api/v1/admin/signing-mode/config')
		await axios.post(url, {
			mode: asyncEnabled.value ? 'async' : 'sync',
			workerType: externalWorkerEnabled.value ? 'external' : 'local',
		})
		showSavedIndicator()
	} catch (error) {
		console.error('Error saving signing mode configuration:', error)
		const requestError = error as SaveConfigError
		errorMessage.value = requestError.response?.data?.ocs?.data?.error
			?? t('libresign', 'Error saving configuration.')
	} finally {
		loading.value = false
	}
}

function onToggleChange(value: boolean) {
	asyncEnabled.value = value
	errorMessage.value = ''
	showErrorIcon.value = false
	void saveConfig()
}

function onWorkerTypeChange(value: boolean) {
	externalWorkerEnabled.value = value
	errorMessage.value = ''
	showErrorIcon.value = false
	void saveConfig()
}

function saveParallelWorkers() {
	const numValue = parseInt(parallelWorkersCount.value, 10)

	if (isNaN(numValue) || numValue < 1 || numValue > 32) {
		parallelWorkersCount.value = lastSavedParallelWorkers.value
		return
	}

	const normalizedValue = String(numValue)
	parallelWorkersCount.value = normalizedValue

	if (normalizedValue === lastSavedParallelWorkers.value) {
		return
	}

	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue('libresign', 'parallel_workers', normalizedValue, {
		success: () => {
			lastSavedParallelWorkers.value = normalizedValue
			showSavedIndicator()
		},
		error: () => {
			errorMessage.value = t('libresign', 'Error saving parallel workers configuration.')
			showErrorIcon.value = true
		},
	})
}

const debouncedSaveParallelWorkers = debounce(() => {
	saveParallelWorkers()
}, 800)

onMounted(() => {
	loadConfig()
})

defineExpose({
	t,
	asyncEnabled,
	externalWorkerEnabled,
	parallelWorkersCount,
	lastSavedParallelWorkers,
	loading,
	errorMessage,
	saved,
	showErrorIcon,
	debouncedSaveParallelWorkers,
	loadConfig,
	onToggleChange,
	onWorkerTypeChange,
	saveConfig,
	saveParallelWorkers,
})
</script>

<style lang="scss" scoped>
.signing-mode-toggle {
	display: flex;
	align-items: center;
	gap: 0.5rem;

	.toggle-status {
		display: flex;
		align-items: center;
	}
}

.worker-type-toggle {
	margin-top: 1rem;
	margin-left: 2rem;

	.worker-type-description {
		margin-top: 0.25rem;
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
	}
}

.parallel-workers-section {
	margin-top: 1rem;
	margin-left: 2rem;

	.parallel-workers-description {
		margin-bottom: 0.75rem;
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
	}

	.parallel-workers-input-wrapper {
		display: flex;
		align-items: center;
		gap: 0.5rem;

		.workers-status {
			display: flex;
			align-items: center;
		}
	}

	:deep(.nc-text-field) {
		max-width: 100px;
	}
}
</style>
