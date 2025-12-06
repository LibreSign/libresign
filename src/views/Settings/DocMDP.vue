<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name">
		<p class="docmdp-info">
			{{ t('libresign', 'DocMDP adds certification signatures to protect PDF documents from unauthorized modifications.') }}
		</p>
		<p>
			<NcCheckboxRadioSwitch type="switch"
				:checked="enabled"
				:disabled="loading"
				@update:checked="onEnabledChange">
				{{ t('libresign', 'Enable DocMDP') }}
			</NcCheckboxRadioSwitch>
		</p>
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>
		<div v-if="enabled">
			<label>
				{{ t('libresign', 'Default certification level for new signatures:') }}
			</label>
			<div class="docmdp-select-wrapper">
				<NcCheckboxRadioSwitch v-for="level in availableLevels"
					:key="level.value"
					type="radio"
					:checked="String(selectedLevel?.value)"
					:value="String(level.value)"
					:disabled="loading"
					name="docmdp_level"
					@update:checked="onLevelChange">
					<div class="docmdp-option">
						<div class="docmdp-option-content">
							<strong>{{ level.label }}</strong>
							<p class="docmdp-option-description">
								{{ level.description }}
							</p>
						</div>
						<div v-if="selectedLevel?.value === level.value" class="docmdp-option-status">
							<NcLoadingIcon v-if="loading" :size="20" />
							<NcSavingIndicatorIcon v-else-if="saved" :size="20" />
							<NcSavingIndicatorIcon v-else-if="showErrorIcon" :size="20" error />
						</div>
					</div>
				</NcCheckboxRadioSwitch>
			</div>
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
	name: 'DocMDP',
	components: {
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcNoteCard,
		NcSavingIndicatorIcon,
		NcSettingsSection,
	},
	data() {
		return {
			name: t('libresign', 'DocMDP Configuration'),
			enabled: false,
			selectedLevel: null,
			availableLevels: [],
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
				const config = loadState('libresign', 'docmdp_config')
				this.enabled = config.enabled
				this.availableLevels = config.availableLevels

				this.selectedLevel = this.availableLevels.find(
					level => level.value === config.defaultLevel
				)

				if (this.enabled && !this.selectedLevel && this.availableLevels.length > 0) {
					this.selectedLevel = this.availableLevels[0]
				}
			} catch (error) {
				console.error('Error loading DocMDP configuration:', error)
				this.errorMessage = t('libresign', 'Failed to load DocMDP configuration')
			}
		},
		onEnabledChange(value) {
			this.enabled = value
			this.saved = false
			this.errorMessage = ''
			this.showErrorIcon = false

			if (value) {
				if (!this.selectedLevel && this.availableLevels.length > 0) {
					this.selectedLevel = this.availableLevels[0]
				}
			} else {
				this.selectedLevel = this.availableLevels[0] || null
			}

			this.saveConfig()
		},
		onLevelChange(value) {
			this.selectedLevel = this.availableLevels.find(level => level.value === parseInt(value))
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
				const url = generateOcsUrl('apps/libresign/api/v1/admin/docmdp/config')
				await axios.post(url, {
					enabled: this.enabled,
					defaultLevel: this.enabled ? (this.selectedLevel?.value ?? 0) : 0,
				})

				this.saved = true
				setTimeout(() => {
					this.saved = false
				}, 3000)
			} catch (error) {
				console.error('Error saving DocMDP configuration:', error)
				this.errorMessage = error.response?.data?.ocs?.data?.error
					|| t('libresign', 'Failed to save DocMDP configuration')
				this.showErrorIcon = true
				setTimeout(() => {
					this.showErrorIcon = false
				}, 3000)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.docmdp {
	&-info {
		color: var(--color-text-maxcontrast);
		margin-top: 0.5rem;
	}

	&-option {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 1rem;
		width: 100%;

		&-content {
			flex: 1;
		}

		&-status {
			flex-shrink: 0;
			display: flex;
			align-items: center;
		}

		&-description {
			margin: 0.25rem 0 0 0;
			color: var(--color-text-maxcontrast);
			font-size: 90%;
		}
	}

	&-select-wrapper {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}
}
</style>
