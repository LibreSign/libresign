<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signature-text-editor">
		<div class="signature-text-editor__row">
			<label class="signature-text-editor__label">{{ t('libresign', 'Background image') }}</label>
			<div class="signature-text-editor__background-options">
				<NcCheckboxRadioSwitch v-for="option in backgroundOptions"
					:key="option.value"
					type="radio"
					:model-value="config.backgroundType === option.value"
					name="signature-stamp-background"
					@update:modelValue="onBackgroundTypeChange(option.value, $event)">
					<div class="signature-text-editor__background-copy">
						<strong>{{ option.label }}</strong>
						<p>{{ option.description }}</p>
					</div>
				</NcCheckboxRadioSwitch>
			</div>

			<div class="signature-text-editor__background-actions">
				<NcButton variant="secondary"
					:aria-label="t('libresign', 'Upload new background image')"
					@click="activateLocalFilePicker">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUpload" :size="20" />
					</template>
					{{ t('libresign', 'Upload') }}
				</NcButton>

				<NcButton v-if="config.backgroundType !== 'default'"
					variant="tertiary"
					:aria-label="t('libresign', 'Reset to default')"
					@click="() => setBackgroundType('default')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiUndoVariant" :size="20" />
					</template>
				</NcButton>

				<NcButton v-if="config.backgroundType !== 'deleted'"
					variant="tertiary"
					:aria-label="t('libresign', 'Remove background')"
					@click="() => setBackgroundType('deleted')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDelete" :size="20" />
					</template>
				</NcButton>

				<NcLoadingIcon v-if="showLoading" :size="20" />

				<input ref="input"
					type="file"
					accept="image/png"
					@change="onChangeBackground">
			</div>

			<NcNoteCard v-if="errorMessage" type="error" :show-alert="true">
				<p>{{ errorMessage }}</p>
			</NcNoteCard>
		</div>

		<div class="signature-text-editor__row">
			<label :for="`template-${id}`" class="signature-text-editor__label">
				{{ t('libresign', 'Signature text template') }}
			</label>
			<textarea :id="`template-${id}`"
				v-model="config.template"
				class="signature-text-editor__textarea"
				:placeholder="t('libresign', 'Enter signature text template')" />
		</div>

		<div class="signature-text-editor__row">
			<label :for="`template-font-size-${id}`" class="signature-text-editor__label">
				{{ t('libresign', 'Template font size') }}
			</label>
			<input :id="`template-font-size-${id}`"
				v-model.number="config.templateFontSize"
				type="number"
				class="signature-text-editor__input"
				:min="0.1"
				:max="30"
				:step="0.1">
		</div>

		<div class="signature-text-editor__row">
			<label :for="`signature-font-size-${id}`" class="signature-text-editor__label">
				{{ t('libresign', 'Signature font size') }}
			</label>
			<input :id="`signature-font-size-${id}`"
				v-model.number="config.signatureFontSize"
				type="number"
				class="signature-text-editor__input"
				:min="0.1"
				:max="30"
				:step="0.1">
		</div>

		<div class="signature-text-editor__row">
			<label :for="`signature-width-${id}`" class="signature-text-editor__label">
				{{ t('libresign', 'Signature width') }}
			</label>
			<input :id="`signature-width-${id}`"
				v-model.number="config.signatureWidth"
				type="number"
				class="signature-text-editor__input"
				:min="1"
				:max="800"
				:step="1">
		</div>

		<div class="signature-text-editor__row">
			<label :for="`signature-height-${id}`" class="signature-text-editor__label">
				{{ t('libresign', 'Signature height') }}
			</label>
			<input :id="`signature-height-${id}`"
				v-model.number="config.signatureHeight"
				type="number"
				class="signature-text-editor__input"
				:min="1"
				:max="800"
				:step="1">
		</div>

		<div class="signature-text-editor__row">
			<label :for="`render-mode-${id}`" class="signature-text-editor__label">
				{{ t('libresign', 'Render mode') }}
			</label>
			<select :id="`render-mode-${id}`"
				v-model="config.renderMode"
				class="signature-text-editor__select">
				<option value="default">
					{{ t('libresign', 'Default') }}
				</option>
				<option value="graphic">
					{{ t('libresign', 'Graphic') }}
				</option>
				<option value="text">
					{{ t('libresign', 'Text') }}
				</option>
			</select>
		</div>
	</div>
</template>

<script setup lang="ts">
import { mdiDelete, mdiUndoVariant, mdiUpload } from '@mdi/js'
import { watch, reactive, ref } from 'vue'

import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { normalizeSignatureTextPolicyConfig, serializeSignatureTextPolicyConfig } from './model'

const props = defineProps({
	modelValue: {
		type: [String, Number, Boolean, Object, Array],
		default: '',
	},
})

const emit = defineEmits(['update:modelValue'])

const backgroundOptions = [
	{
		value: 'default',
		label: t('libresign', 'Default background'),
		description: t('libresign', 'Use the default LibreSign background image.'),
	},
	{
		value: 'custom',
		label: t('libresign', 'Custom background'),
		description: t('libresign', 'Use a custom image uploaded by an administrator.'),
	},
	{
		value: 'deleted',
		label: t('libresign', 'No background'),
		description: t('libresign', 'Do not apply any background image to signatures.'),
	},
]

const id = Math.random().toString(36).substring(7)
const normalized = normalizeSignatureTextPolicyConfig(props.modelValue)
const input = ref(null)
const showLoading = ref(false)
const errorMessage = ref('')

const config = reactive({
	template: normalized.template,
	templateFontSize: normalized.templateFontSize,
	signatureFontSize: normalized.signatureFontSize,
	signatureWidth: normalized.signatureWidth,
	signatureHeight: normalized.signatureHeight,
	backgroundType: normalized.backgroundType,
	renderMode: normalized.renderMode,
})

// Watch all fields and emit serialized value
const emitUpdate = () => {
	emit('update:modelValue', serializeSignatureTextPolicyConfig(config))
}

watch(() => config.template, emitUpdate)
watch(() => config.templateFontSize, emitUpdate)
watch(() => config.signatureFontSize, emitUpdate)
watch(() => config.signatureWidth, emitUpdate)
watch(() => config.signatureHeight, emitUpdate)
watch(() => config.backgroundType, emitUpdate)
watch(() => config.renderMode, emitUpdate)

/**
 *
 * @param value
 */
function setBackgroundType(value) {
	errorMessage.value = ''
	config.backgroundType = value
}

/**
 *
 */
function activateLocalFilePicker() {
	errorMessage.value = ''
	if (!input.value) {
		return
	}

	input.value.value = ''
	input.value.click()
}

/**
 *
 * @param event
 */
async function onChangeBackground(event) {
	const file = event?.target?.files?.[0]
	if (!file) {
		return
	}

	const formData = new FormData()
	formData.append('image', file)

	showLoading.value = true
	try {
		await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
		setBackgroundType('custom')
	} catch (error) {
		const response = /** @type {any} */(error)?.response
		errorMessage.value = response?.data?.ocs?.data?.message || 'Upload failed'
	} finally {
		showLoading.value = false
	}
}

/**
 *
 * @param value
 * @param selected
 */
function onBackgroundTypeChange(value, selected) {
	if (selected === false) {
		return
	}

	if (value === 'custom') {
		activateLocalFilePicker()
		return
	}

	setBackgroundType(value)
}
</script>

<style scoped>
.signature-text-editor {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.signature-text-editor__row {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.signature-text-editor__label {
	font-weight: 500;
	color: var(--color-text);
}

.signature-text-editor__background-options {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.signature-text-editor__background-copy p {
	margin: 0.35rem 0 0;
	color: var(--color-text-maxcontrast);
}

.signature-text-editor__background-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.signature-text-editor__textarea {
	min-height: 120px;
	padding: 0.5rem;
	border: 1px solid var(--color-border);
	border-radius: 4px;
	font-family: monospace;
}

.signature-text-editor__input,
.signature-text-editor__select {
	padding: 0.5rem;
	border: 1px solid var(--color-border);
	border-radius: 4px;
}

input[type='file'] {
	display: none;
}
</style>
