<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-stamp-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="modelValue.enabled"
			@update:modelValue="onEnabledChange">
			<span>{{ t('libresign', 'Enable visible signature stamp') }}</span>
		</NcCheckboxRadioSwitch>

		<div v-if="modelValue.enabled" class="signature-stamp-rule-editor__grid">
			<div class="signature-stamp-rule-editor__presets">
				<p class="signature-stamp-rule-editor__presets-title">{{ t('libresign', 'Quick presets') }}</p>
				<div class="signature-stamp-rule-editor__preset-actions">
					<NcButton
						v-for="preset in presets"
						:key="preset.id"
						variant="secondary"
						:aria-label="t('libresign', 'Apply preset')"
						@click="applyPreset(preset.id)">
						{{ preset.label }}
					</NcButton>
				</div>
			</div>

			<fieldset class="signature-stamp-rule-editor__fieldset">
				<legend>{{ t('libresign', 'Render mode') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="renderModeOption in renderModeOptions"
					:key="renderModeOption.value"
					type="radio"
					name="stamp-render-mode"
					:model-value="modelValue.renderMode === renderModeOption.value"
					@update:modelValue="onRenderModeChange(renderModeOption.value)">
					<div class="signature-stamp-rule-editor__copy">
						<strong>{{ renderModeOption.label }}</strong>
						<p>{{ renderModeOption.description }}</p>
					</div>
				</NcCheckboxRadioSwitch>
			</fieldset>

			<fieldset class="signature-stamp-rule-editor__fieldset">
				<legend>{{ t('libresign', 'Background mode') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="backgroundModeOption in backgroundModeOptions"
					:key="backgroundModeOption.value"
					type="radio"
					name="stamp-background-mode"
					:model-value="modelValue.backgroundMode === backgroundModeOption.value"
					@update:modelValue="onBackgroundModeChange(backgroundModeOption.value)">
					{{ backgroundModeOption.label }}
				</NcCheckboxRadioSwitch>
			</fieldset>

			<div class="signature-stamp-rule-editor__field">
				<label class="signature-stamp-rule-editor__label" for="signature-stamp-template">
					{{ t('libresign', 'Template text') }}
				</label>
				<textarea
					id="signature-stamp-template"
					class="signature-stamp-rule-editor__textarea"
					:value="modelValue.template"
					@input="onTemplateChange" />
				<div class="signature-stamp-rule-editor__variables">
					<span>{{ t('libresign', 'Insert variable:') }}</span>
					<NcButton
						v-for="stampVariable in stampVariables"
						:key="stampVariable"
						variant="tertiary"
						:aria-label="t('libresign', 'Insert variable into template')"
						@click="insertVariable(stampVariable)">
						{{ stampVariable }}
					</NcButton>
				</div>
				<p class="signature-stamp-rule-editor__meta">
					{{ t('libresign', 'Template length: {count} characters', {
						count: String(templateLength),
					}) }}
				</p>
			</div>

			<div class="signature-stamp-rule-editor__dimensions">
				<NcTextField
					:model-value="String(modelValue.templateFontSize)"
					:label="t('libresign', 'Template font size')"
					type="number"
					:min="8"
					:max="48"
					:step="1"
					@update:modelValue="onNumberChange('templateFontSize', $event, 8, 48)" />
				<NcTextField
					:model-value="String(modelValue.signatureFontSize)"
					:label="t('libresign', 'Signature font size')"
					type="number"
					:min="8"
					:max="48"
					:step="1"
					@update:modelValue="onNumberChange('signatureFontSize', $event, 8, 48)" />
				<NcTextField
					:model-value="String(modelValue.signatureWidth)"
					:label="t('libresign', 'Default width')"
					type="number"
					:min="120"
					:max="800"
					:step="10"
					@update:modelValue="onNumberChange('signatureWidth', $event, 120, 800)" />
				<NcTextField
					:model-value="String(modelValue.signatureHeight)"
					:label="t('libresign', 'Default height')"
					type="number"
					:min="40"
					:max="320"
					:step="10"
					@update:modelValue="onNumberChange('signatureHeight', $event, 40, 320)" />
			</div>

			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="modelValue.showSigningDate"
				@update:modelValue="onSigningDateChange">
				<span>{{ t('libresign', 'Show signing date in the stamp') }}</span>
			</NcCheckboxRadioSwitch>

			<NcNoteCard v-if="isTemplateTooLong" type="warning">
				{{ t('libresign', 'This template is long and can overflow small signature boxes. Consider fewer variables or a larger width.') }}
			</NcNoteCard>

			<NcNoteCard v-if="isExtremeRatio" type="warning">
				{{ t('libresign', 'Current width and height ratio is extreme. This may reduce readability on mobile and PDF preview.') }}
			</NcNoteCard>

			<div class="signature-stamp-rule-editor__preview">
				<p class="signature-stamp-rule-editor__preview-title">{{ t('libresign', 'Preview summary') }}</p>
				<p>{{ previewSummary }}</p>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { SignatureStampBackgroundMode, SignatureStampRenderMode, SignatureStampRuleValue } from '../../types'

defineOptions({
	name: 'SignatureStampRuleEditor',
})

const props = defineProps<{
	modelValue: SignatureStampRuleValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: SignatureStampRuleValue]
}>()

const renderModeOptions: Array<{ value: SignatureStampRenderMode, label: string, description: string }> = [
	{
		value: 'GRAPHIC_AND_DESCRIPTION',
		label: t('libresign', 'Signature and description'),
		description: t('libresign', 'Shows signature image and textual context side by side.'),
	},
	{
		value: 'DESCRIPTION_ONLY',
		label: t('libresign', 'Description only'),
		description: t('libresign', 'Useful when a visual mark is not required, only the contextual text.'),
	},
	{
		value: 'SIGNAME_AND_DESCRIPTION',
		label: t('libresign', 'Signer name and description'),
		description: t('libresign', 'Renders signer name in place of the graphic signature image.'),
	},
	{
		value: 'GRAPHIC_ONLY',
		label: t('libresign', 'Signature only'),
		description: t('libresign', 'Uses only the signature image area without description text.'),
	},
]

const backgroundModeOptions: Array<{ value: SignatureStampBackgroundMode, label: string }> = [
	{ value: 'default', label: t('libresign', 'Default background') },
	{ value: 'custom', label: t('libresign', 'Custom background') },
	{ value: 'none', label: t('libresign', 'No background') },
]

const stampVariables = ['{{ signer_name }}', '{{ request_uuid }}', '{{ signed_at }}', '{{ organization }}']

const presets: Array<{ id: 'compact' | 'balanced' | 'detailed', label: string }> = [
	{ id: 'compact', label: t('libresign', 'Compact') },
	{ id: 'balanced', label: t('libresign', 'Balanced') },
	{ id: 'detailed', label: t('libresign', 'Detailed') },
]

const templateLength = computed(() => props.modelValue.template.trim().length)
const isTemplateTooLong = computed(() => templateLength.value > 180)
const isExtremeRatio = computed(() => {
	const ratio = props.modelValue.signatureWidth / Math.max(1, props.modelValue.signatureHeight)
	return ratio > 5 || ratio < 1.6
})

const previewSummary = computed(() => {
	const mode = renderModeOptions.find((item) => item.value === props.modelValue.renderMode)?.label ?? props.modelValue.renderMode
	const background = backgroundModeOptions.find((item) => item.value === props.modelValue.backgroundMode)?.label ?? props.modelValue.backgroundMode
	return `${mode} - ${background} - ${props.modelValue.signatureWidth}x${props.modelValue.signatureHeight}px`
})

function updateValue(nextValue: Partial<SignatureStampRuleValue>) {
	emit('update:modelValue', {
		...props.modelValue,
		...nextValue,
	})
}

function onEnabledChange(enabled: boolean) {
	updateValue({ enabled })
}

function onRenderModeChange(renderMode: SignatureStampRenderMode) {
	updateValue({ renderMode })
}

function onBackgroundModeChange(backgroundMode: SignatureStampBackgroundMode) {
	updateValue({ backgroundMode })
}

function onTemplateChange(event: Event) {
	const target = event.target as HTMLTextAreaElement
	updateValue({ template: target.value })
}

function insertVariable(stampVariable: string) {
	const separator = props.modelValue.template.length > 0 ? ' ' : ''
	updateValue({
		template: `${props.modelValue.template}${separator}${stampVariable}`,
	})
}

function applyPreset(presetId: 'compact' | 'balanced' | 'detailed') {
	if (presetId === 'compact') {
		updateValue({
			renderMode: 'GRAPHIC_ONLY',
			templateFontSize: 9,
			signatureFontSize: 16,
			signatureWidth: 150,
			signatureHeight: 60,
			backgroundMode: 'none',
			template: '{{ signer_name }}',
		})
		return
	}

	if (presetId === 'detailed') {
		updateValue({
			renderMode: 'GRAPHIC_AND_DESCRIPTION',
			templateFontSize: 11,
			signatureFontSize: 22,
			signatureWidth: 260,
			signatureHeight: 95,
			backgroundMode: 'custom',
			template: '{{ signer_name }} - {{ organization }} - {{ signed_at }}',
		})
		return
	}

	updateValue({
		renderMode: 'GRAPHIC_AND_DESCRIPTION',
		templateFontSize: 10,
		signatureFontSize: 19,
		signatureWidth: 180,
		signatureHeight: 70,
		backgroundMode: 'default',
		template: '{{ signer_name }} - {{ signed_at }}',
	})
}

function onSigningDateChange(showSigningDate: boolean) {
	updateValue({ showSigningDate })
}

function onNumberChange(
	field: 'templateFontSize' | 'signatureFontSize' | 'signatureWidth' | 'signatureHeight',
	rawValue: string | number,
	min: number,
	max: number,
) {
	const parsed = Number(rawValue)
	if (!Number.isFinite(parsed)) {
		return
	}

	const clamped = Math.min(max, Math.max(min, parsed))
	updateValue({
		[field]: clamped,
	} as Partial<SignatureStampRuleValue>)
}
</script>

<style scoped lang="scss">
.signature-stamp-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 1rem;

	&__grid {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}

	&__presets {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		border-radius: 12px;
		padding: 0.75rem;
		background: color-mix(in srgb, var(--color-primary-element) 10%, var(--color-main-background));
	}

	&__presets-title {
		margin: 0;
		font-size: 0.86rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--color-text-maxcontrast);
	}

	&__preset-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
	}

	&__fieldset {
		border: 1px solid var(--color-border-maxcontrast);
		border-radius: 12px;
		padding: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.5rem;

		legend {
			padding: 0 0.35rem;
			font-weight: 600;
		}
	}

	&__copy p {
		margin: 0.2rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__field {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	&__label {
		font-weight: 600;
	}

	&__textarea {
		width: 100%;
		min-height: 110px;
		border: 1px solid var(--color-border-maxcontrast);
		border-radius: 10px;
		padding: 0.6rem;
		font: inherit;
		background: var(--color-main-background);
	}

	&__variables {
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;
		align-items: center;
	}

	&__meta {
		margin: 0;
		font-size: 0.82rem;
		color: var(--color-text-maxcontrast);
	}

	&__dimensions {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.75rem;
	}

	&__preview {
		border-radius: 12px;
		border: 1px dashed color-mix(in srgb, var(--color-primary-element) 45%, var(--color-border-maxcontrast));
		padding: 0.75rem;
		background: color-mix(in srgb, var(--color-main-background) 92%, white);
	}

	&__preview-title {
		margin: 0 0 0.35rem;
		font-weight: 600;
	}
}

@media (max-width: 640px) {
	.signature-stamp-rule-editor {
		&__preset-actions {
			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}

		&__dimensions {
			grid-template-columns: 1fr;
		}
	}
}
</style>
