<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name">
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>
		<div class="signature-flow-options">
			<NcCheckboxRadioSwitch v-for="flow in availableFlows"
					:key="flow.value"
					type="radio"
					:checked="selectedFlow?.value"
					:value="flow.value"
					:disabled="loading"
					name="signature_flow"
					@update:checked="onFlowChange">
					<div class="signature-flow-option">
						<div class="signature-flow-option-content">
							<strong>{{ flow.label }}</strong>
							<p class="signature-flow-option-description">
								{{ flow.description }}
							</p>
						</div>
						<div v-if="selectedFlow?.value === flow.value" class="signature-flow-option-status">
							<NcLoadingIcon v-if="loading" :size="20" />
							<NcSavingIndicatorIcon v-else-if="saved" :size="20" />
							<NcSavingIndicatorIcon v-else-if="showErrorIcon" :size="20" error />
						</div>
					</div>
				</NcCheckboxRadioSwitch>
			</div>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'SignatureFlow',
	components: {
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcNoteCard,
		NcSavingIndicatorIcon,
		NcSettingsSection,
	},
	data() {
		return {
			name: t('libresign', 'Signing order'),
			selectedFlow: null,
			availableFlows: [
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
			],
			loading: false,
			errorMessage: '',
			saved: false,
			showErrorIcon: false,
		}
	},
	async mounted() {
		this.loadConfig()
	},
	methods: {
		loadConfig() {
			try {
				const mode = loadState('libresign', 'signature_flow', 'parallel')
				
				this.selectedFlow = this.availableFlows.find(
					flow => flow.value === mode
				)

				if (!this.selectedFlow) {
					this.selectedFlow = this.availableFlows[0]
				}
			} catch (error) {
				console.error('Error loading signature flow configuration:', error)
				this.errorMessage = t('libresign', 'Could not load configuration.')
				this.selectedFlow = this.availableFlows[0]
			}
		},
		onFlowChange(value) {
			this.selectedFlow = this.availableFlows.find(flow => flow.value === value)
			this.errorMessage = ''
			this.showErrorIcon = false
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
					mode: this.selectedFlow?.value ?? 'parallel',
				})

				this.saved = true
				setTimeout(() => {
					this.saved = false
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
.signature-flow-options {
	margin-top: 0.5rem;

	.signature-flow-option {
		display: flex;
		justify-content: space-between;
		align-items: center;
		width: 100%;
		padding: 0.5rem 0;

		&-content {
			flex: 1;
		}

		&-description {
			margin: 0.25rem 0 0 0;
			color: var(--color-text-maxcontrast);
			font-size: 0.9em;
		}

		&-status {
			margin-left: 1rem;
			display: flex;
			align-items: center;
		}
	}
}
</style>
