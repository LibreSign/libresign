<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="crl-validation-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="String(option.value)"
			class="crl-validation-editor__option"
			type="radio"
			:model-value="normalizedValue === option.value"
			name="crl-validation-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="crl-validation-editor__copy">
				<strong>{{ option.label }}</strong>
				<p>{{ option.description }}</p>
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'

defineOptions({
	name: 'CrlValidationRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options = [
	{
		value: true,
		label: t('libresign', 'Enabled'),
		description: t('libresign', 'Validate external CRL distribution points when available.'),
	},
	{
		value: false,
		label: t('libresign', 'Disabled'),
		description: t('libresign', 'Skip external CRL distribution points and only rely on local CRL checks.'),
	},
]

const normalizedValue = computed<boolean | null>(() => {
	if (typeof props.modelValue === 'boolean') {
		return props.modelValue
	}

	if (props.modelValue === '1' || props.modelValue === 'true') {
		return true
	}

	if (props.modelValue === '0' || props.modelValue === 'false') {
		return false
	}

	return null
})

function onChange(value: boolean, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', value)
}
</script>

<style scoped lang="scss">
.crl-validation-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.crl-validation-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.crl-validation-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.crl-validation-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
