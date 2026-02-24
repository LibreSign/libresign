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

<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'SignatureFlow',
	components: {
		NcLoadingIcon,
		NcNoteCard,
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcSavingIndicatorIcon,
	},
	data() {
		return {
			enabled: false,
			selectedFlow: null,
			loading: false,
			errorMessage: '',
			saved: false,
			showErrorIcon: false,
			flowChanging: false,
		}
	},
	computed: {
		availableFlows() {
			return [
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
		},
		selectedFlowValue: {
			get() {
				return this.selectedFlow?.value ?? this.availableFlows[0].value
			},
			set(value) {
				this.selectedFlow = this.availableFlows.find(flow => flow.value === value) ?? this.availableFlows[0]
			},
		},
	},
	async mounted() {
		this.loadConfig()
	},
	methods: {
		t,
		loadConfig() {
			try {
				const mode = loadState('libresign', 'signature_flow', 'none')

				if (mode === 'none') {
					this.enabled = false
					this.selectedFlow = this.availableFlows[0]
				} else {
					this.enabled = true
					this.selectedFlow = this.availableFlows.find(
						flow => flow.value === mode
					)

					if (!this.selectedFlow) {
						this.selectedFlow = this.availableFlows[0]
					}
				}
			} catch (error) {
				console.error('Error loading signature flow configuration:', error)
				this.errorMessage = t('libresign', 'Could not load configuration.')
				this.enabled = false
				this.selectedFlow = this.availableFlows[0]
			}
		},
		onToggleChange() {
			this.errorMessage = ''
			this.showErrorIcon = false
			this.flowChanging = false
			this.saveConfig()
		},
		onFlowChange() {
			this.errorMessage = ''
			this.showErrorIcon = false
			this.flowChanging = true
			this.saveConfig()
		},
		async saveConfig() {
			this.loading = true
			this.errorMessage = ''
			this.saved = false
			this.showErrorIcon = false

			try {
				const url = generateOcsUrl('apps/libresign/api/v1/admin/signature-flow/config')
				await axios.post(url, {
					enabled: this.enabled,
					mode: this.enabled ? (this.selectedFlow?.value ?? 'parallel') : null,
				})

				this.saved = true
				setTimeout(() => {
					this.saved = false
					this.flowChanging = false
				}, 3000)
			} catch (error) {
				console.error('Error saving signature flow configuration:', error)
				this.errorMessage = error.response?.data?.ocs?.data?.error
					?? t('libresign', 'Error saving configuration.')
				this.showErrorIcon = true
			} finally {
				this.loading = false
			}
		},
	},
}
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
