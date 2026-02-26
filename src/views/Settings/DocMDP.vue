<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="name"
		:description="t('libresign', 'DocMDP defines what types of changes are allowed in a PDF after it is signed, ensuring viewers can detect unauthorized modifications.')"
	>
		<div>
			<NcCheckboxRadioSwitch type="switch"
				v-model="enabled"
				:disabled="loading"
				@update:modelValue="onEnabledChange">
				<!-- TRANSLATORS: Label for enabling DocMDP certification -->
				{{ t('libresign', 'Enable DocMDP') }}
			</NcCheckboxRadioSwitch>
		</div>
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>
		<div v-if="enabled">
			<label>
				<!-- TRANSLATORS: Label asking to select default certification level -->
				{{ t('libresign', 'Default certification level for new signatures:') }}
			</label>
			<div class="docmdp-select-wrapper">
				<NcCheckboxRadioSwitch v-for="level in availableLevels"
					:key="level.value"
					type="radio"
					v-model="selectedLevelValue"
					:value="String(level.value)"
					:disabled="loading"
					name="docmdp_level"
					@update:modelValue="onLevelChange">
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
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'DocMDP',
	constants: {
		PREFERRED_DEFAULT_LEVEL: 2,
	},
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
			selectedLevel: null,
			availableLevels: [],
			loading: false,
			errorMessage: '',
			saved: false,
			showErrorIcon: false,
		}
	},
	computed: {
		name() {
			// TRANSLATORS DocMDP (Document Modification Detection and Prevention) is a PDF specification extension that allows setting certification levels for digitally signed documents. It controls what types of changes are allowed after signing and ensures viewers can detect unauthorized modifications.
			return t('libresign', 'PDF certification (DocMDP)')
		},
		selectedLevelValue: {
			get() {
				return String(this.selectedLevel?.value ?? 0)
			},
			set(value) {
				const numericValue = Number(value)
				this.selectedLevel = this.availableLevels.find(level => level.value === numericValue) ?? this.availableLevels[0] ?? null
			},
		},
	},
	async mounted() {
		this.loadConfig()
	},
	methods: {
		t,
		getPreferredDefaultLevel() {
			return this.availableLevels.find(level => level.value === this.$options.constants.PREFERRED_DEFAULT_LEVEL)
				?? this.availableLevels[0]
				?? null
		},
		loadConfig() {
			try {
				const config = loadState('libresign', 'docmdp_config', {
					enabled: false,
					defaultLevel: this.$options.constants.PREFERRED_DEFAULT_LEVEL,
					availableLevels: [],
				})
				this.enabled = config.enabled
				this.availableLevels = config.availableLevels
				const defaultLevel = config.defaultLevel

				this.selectedLevel = this.availableLevels.find(
					level => level.value === defaultLevel
				)

				if (!this.selectedLevel) {
					this.selectedLevel = this.getPreferredDefaultLevel()
				}
			} catch (error) {
				console.error('Error loading DocMDP configuration:', error)
				this.errorMessage = t('libresign', 'Could not load configuration.')
			}
		},
		onEnabledChange() {
			this.saved = false
			this.errorMessage = ''
			this.showErrorIcon = false

			if (this.enabled) {
				if (!this.selectedLevel) {
					this.selectedLevel = this.getPreferredDefaultLevel()
				}
			} else {
				this.selectedLevel = this.getPreferredDefaultLevel()
			}

			this.saveConfig()
		},
		onLevelChange() {
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
					|| t('libresign', 'Could not save configuration.')
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
