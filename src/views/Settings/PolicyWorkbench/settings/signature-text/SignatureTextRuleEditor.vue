<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signature-text-editor">
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
				<option value="default">{{ t('libresign', 'Default') }}</option>
				<option value="graphic">{{ t('libresign', 'Graphic') }}</option>
				<option value="text">{{ t('libresign', 'Text') }}</option>
			</select>
		</div>
	</div>
</template>

<script setup lang="ts">
import { watch, reactive } from 'vue'
import { t } from '@nextcloud/l10n'
import type { EffectivePolicyValue } from '../../../../../types/index'
import { normalizeSignatureTextPolicyConfig, serializeSignatureTextPolicyConfig } from './model'

interface Props {
	value: EffectivePolicyValue
}

interface Emits {
	(e: 'update:value', value: Record<string, unknown>): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const id = Math.random().toString(36).substring(7)
const normalized = normalizeSignatureTextPolicyConfig(props.value)

const config = reactive({
	template: normalized.template,
	templateFontSize: normalized.templateFontSize,
	signatureFontSize: normalized.signatureFontSize,
	signatureWidth: normalized.signatureWidth,
	signatureHeight: normalized.signatureHeight,
	renderMode: normalized.renderMode,
})

// Watch all fields and emit serialized value
const emitUpdate = () => {
	emit('update:value', serializeSignatureTextPolicyConfig(config))
}

watch(() => config.template, emitUpdate)
watch(() => config.templateFontSize, emitUpdate)
watch(() => config.signatureFontSize, emitUpdate)
watch(() => config.signatureWidth, emitUpdate)
watch(() => config.signatureHeight, emitUpdate)
watch(() => config.renderMode, emitUpdate)
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
</style>
