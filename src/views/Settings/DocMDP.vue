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

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import type { operations } from '../../types/openapi/openapi-administration'

defineOptions({
	name: 'DocMDP',
})

interface LevelOption {
	value: number
	label: string
	description: string
}

interface DocMDPConfig {
	enabled: boolean
	defaultLevel: number
	availableLevels: LevelOption[]
}

type DocMdpRequestBody = operations['admin-set-doc-mdp-config']['requestBody']['content']['application/json']
type DocMdpErrorResponse =
	| operations['admin-set-doc-mdp-config']['responses'][400]['content']['application/json']
	| operations['admin-set-doc-mdp-config']['responses'][500]['content']['application/json']

type DocMdpRequestError = {
	response?: {
		data?: DocMdpErrorResponse
	}
}

const PREFERRED_DEFAULT_LEVEL = 2

const enabled = ref(false)
const selectedLevel = ref<LevelOption | null>(null)
const availableLevels = ref<LevelOption[]>([])
const loading = ref(false)
const errorMessage = ref('')
const saved = ref(false)
const showErrorIcon = ref(false)

const name = computed(() => {
	return t('libresign', 'PDF certification (DocMDP)')
})

const selectedLevelValue = computed({
	get() {
		return String(selectedLevel.value?.value ?? 0)
	},
	set(value: string) {
		const numericValue = Number(value)
		selectedLevel.value = availableLevels.value.find(level => level.value === numericValue) ?? availableLevels.value[0] ?? null
	},
})

function getPreferredDefaultLevel() {
	return availableLevels.value.find(level => level.value === PREFERRED_DEFAULT_LEVEL)
		?? availableLevels.value[0]
		?? null
}

function loadConfig() {
	try {
		const config = loadState('libresign', 'docmdp_config', {
			enabled: false,
			defaultLevel: PREFERRED_DEFAULT_LEVEL,
			availableLevels: [],
		}) as DocMDPConfig

		enabled.value = config.enabled
		availableLevels.value = config.availableLevels
		selectedLevel.value = availableLevels.value.find(level => level.value === config.defaultLevel) ?? null

		if (!selectedLevel.value) {
			selectedLevel.value = getPreferredDefaultLevel()
		}
	} catch (error) {
		console.error('Error loading DocMDP configuration:', error)
		errorMessage.value = t('libresign', 'Could not load configuration.')
	}
}

function onEnabledChange() {
	saved.value = false
	errorMessage.value = ''
	showErrorIcon.value = false

	if (!selectedLevel.value || !enabled.value) {
		selectedLevel.value = getPreferredDefaultLevel()
	}

	void saveConfig()
}

function onLevelChange() {
	errorMessage.value = ''
	showErrorIcon.value = false
	void saveConfig()
}

function getErrorMessage(error: unknown): string | null {
	const requestError = error as DocMdpRequestError
	return requestError.response?.data?.ocs?.data?.error ?? null
}

async function saveConfig() {
	loading.value = true
	errorMessage.value = ''
	saved.value = false
	showErrorIcon.value = false

	try {
		const url = generateOcsUrl('apps/libresign/api/v1/admin/docmdp/config')
		const payload: DocMdpRequestBody = {
			enabled: enabled.value,
			defaultLevel: enabled.value ? (selectedLevel.value?.value ?? 0) : 0,
		}
		await axios.post(url, payload)

		saved.value = true
		setTimeout(() => {
			saved.value = false
		}, 3000)
	} catch (error: unknown) {
		console.error('Error saving DocMDP configuration:', error)
		errorMessage.value = getErrorMessage(error) ?? t('libresign', 'Could not save configuration.')
		showErrorIcon.value = true
		setTimeout(() => {
			showErrorIcon.value = false
		}, 3000)
	} finally {
		loading.value = false
	}
}

onMounted(() => {
	loadConfig()
})

defineExpose({
	enabled,
	selectedLevel,
	onEnabledChange,
})
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
