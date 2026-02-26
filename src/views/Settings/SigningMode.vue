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

<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'SigningMode',
	components: {
		NcLoadingIcon,
		NcNoteCard,
		NcSettingsSection,
		NcTextField,
		NcCheckboxRadioSwitch,
		NcSavingIndicatorIcon,
	},
	data() {
		return {
			asyncEnabled: false,
			externalWorkerEnabled: false,
			parallelWorkersCount: '4',
			lastSavedParallelWorkers: '4',
			loading: false,
			errorMessage: '',
			saved: false,
			showErrorIcon: false,
			debouncedSaveParallelWorkers: null,
		}
	},
	created() {
		this.debouncedSaveParallelWorkers = debounce(this.saveParallelWorkers, 800)
	},
	mounted() {
		this.loadConfig()
	},
	methods: {
		t,
		loadConfig() {
			try {
				const mode = loadState('libresign', 'signing_mode', 'sync')
				this.asyncEnabled = mode === 'async'

				const workerType = loadState('libresign', 'worker_type', 'local')
				this.externalWorkerEnabled = workerType === 'external'

				const parallelWorkers = loadState('libresign', 'parallel_workers', '4')
				this.parallelWorkersCount = String(parallelWorkers)
				this.lastSavedParallelWorkers = this.parallelWorkersCount
			} catch (error) {
				console.error('Error loading signing mode configuration:', error)
				this.errorMessage = t('libresign', 'Could not load configuration.')
				this.asyncEnabled = false
				this.externalWorkerEnabled = false
				this.parallelWorkersCount = '4'
			}
		},
		onToggleChange(value) {
			this.asyncEnabled = value
			this.errorMessage = ''
			this.showErrorIcon = false
			this.saveConfig()
		},
		onWorkerTypeChange(value) {
			this.externalWorkerEnabled = value
			this.errorMessage = ''
			this.showErrorIcon = false
			this.saveConfig()
		},
		saveConfig() {
			this.loading = true
			this.errorMessage = ''
			this.saved = false

			const url = generateOcsUrl('apps/libresign/api/v1/admin/signing-mode/config')
			axios.post(url, {
				mode: this.asyncEnabled ? 'async' : 'sync',
				workerType: this.externalWorkerEnabled ? 'external' : 'local',
			})
				.then(() => {
					this.saved = true
					setTimeout(() => {
						this.saved = false
					}, 3000)
				})
				.catch((error) => {
					console.error('Error saving signing mode configuration:', error)
					this.errorMessage = error.response?.data?.ocs?.data?.error
						?? t('libresign', 'Error saving configuration.')
				})
				.finally(() => {
					this.loading = false
				})
		},
		saveParallelWorkers() {
			const numValue = parseInt(this.parallelWorkersCount, 10)

			if (isNaN(numValue) || numValue < 1 || numValue > 32) {
				this.parallelWorkersCount = this.lastSavedParallelWorkers
				return
			}

			const normalizedValue = String(numValue)
			this.parallelWorkersCount = normalizedValue

			if (normalizedValue === this.lastSavedParallelWorkers) {
				return
			}

			OCP.AppConfig.setValue('libresign', 'parallel_workers', normalizedValue, {
				success: () => {
					this.lastSavedParallelWorkers = normalizedValue
					this.saved = true
					setTimeout(() => {
						this.saved = false
					}, 3000)
				},
				error: (error) => {
					this.errorMessage = t('libresign', 'Error saving parallel workers configuration.')
					this.showErrorIcon = true
				},
			})
		},
	},
}
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
