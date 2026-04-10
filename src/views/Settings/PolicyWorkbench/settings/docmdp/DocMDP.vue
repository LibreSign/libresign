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
				:disabled="loading || !canEdit"
				@update:modelValue="onEnabledChange">
				<!-- TRANSLATORS: Label for enabling DocMDP certification -->
				{{ t('libresign', 'Enable DocMDP') }}
			</NcCheckboxRadioSwitch>
		</div>
		<NcNoteCard v-if="!canEdit" type="info">
			{{ t('libresign', 'This setting is managed by higher-level policy (%s).', sourceScopeLabel) }}
		</NcNoteCard>
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
					:disabled="loading || !canEdit"
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
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSavingIndicatorIcon from '@nextcloud/vue/components/NcSavingIndicatorIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import { usePoliciesStore } from '../../../../../store/policies'
import type { EffectivePolicyState, SystemPolicyWriteErrorResponse } from '../../../../../types'

defineOptions({
	name: 'DocMDP',
})

type DocMdpLevelOption = {
	value: number
	label: string
	description: string
}

type DocMdpRequestError = {
	response?: {
		data?: {
			ocs?: {
				data?: SystemPolicyWriteErrorResponse
			}
		}
	}
}

const PREFERRED_DEFAULT_LEVEL = 2
const DOCMDP_DISABLED_LEVEL = 0

const policiesStore = usePoliciesStore()
const enabled = ref(false)
const selectedLevel = ref<DocMdpLevelOption | null>(null)
const loading = ref(false)
const errorMessage = ref('')
const saved = ref(false)
const showErrorIcon = ref(false)

const docMdpPolicy = computed<EffectivePolicyState | null>(() => policiesStore.getPolicy('docmdp'))
const canEdit = computed(() => docMdpPolicy.value?.editableByCurrentActor ?? true)

const availableLevels = computed<DocMdpLevelOption[]>(() => [
	{
		value: 1,
		label: t('libresign', 'No changes allowed'),
		description: t('libresign', 'After signing, no changes are allowed in the document.'),
	},
	{
		value: 2,
		label: t('libresign', 'Form filling'),
		description: t('libresign', 'After signing, only form filling is allowed.'),
	},
	{
		value: 3,
		label: t('libresign', 'Form filling and annotations'),
		description: t('libresign', 'After signing, form filling and annotations are allowed.'),
	},
])

const sourceScopeLabel = computed(() => {
	switch (docMdpPolicy.value?.sourceScope) {
	case 'group':
		return t('libresign', 'group')
	case 'user':
		return t('libresign', 'user preference')
	case 'request':
		return t('libresign', 'request override')
	case 'global':
		return t('libresign', 'global')
	default:
		return t('libresign', 'system')
	}
})

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
	return availableLevels.value.find((level) => level.value === PREFERRED_DEFAULT_LEVEL)
		?? availableLevels.value[0]
		?? null
}

function applyPolicy(policy: EffectivePolicyState | null) {
	const effectiveValue = Number(policy?.effectiveValue ?? DOCMDP_DISABLED_LEVEL)
	if (effectiveValue > DOCMDP_DISABLED_LEVEL) {
		enabled.value = true
		selectedLevel.value = availableLevels.value.find((level) => level.value === effectiveValue) ?? getPreferredDefaultLevel()
		return
	}

	enabled.value = false
	selectedLevel.value = getPreferredDefaultLevel()
}

function loadConfig() {
	try {
		applyPolicy(docMdpPolicy.value)
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
		const savedPolicy = await policiesStore.saveSystemPolicy(
			'docmdp',
			enabled.value ? (selectedLevel.value?.value ?? PREFERRED_DEFAULT_LEVEL) : DOCMDP_DISABLED_LEVEL,
		)
		applyPolicy(savedPolicy)

		saved.value = true
		setTimeout(() => {
			saved.value = false
		}, 3000)
	} catch (error: unknown) {
		console.error('Error saving DocMDP configuration:', error)
		errorMessage.value = getErrorMessage(error) ?? t('libresign', 'Could not save configuration.')
		showErrorIcon.value = true
		applyPolicy(docMdpPolicy.value)
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
	canEdit,
	docMdpPolicy,
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
