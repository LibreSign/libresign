<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="envelope-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="String(option.value)"
			class="envelope-editor__option"
			type="radio"
			:model-value="normalizedValue === option.value"
			name="envelope-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="envelope-editor__copy">
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
	name: 'EnvelopeRuleEditor',
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
		description: t('libresign', 'Allow users to group multiple files into envelopes for signing.'),
	},
	{
		value: false,
		label: t('libresign', 'Disabled'),
		description: t('libresign', 'Do not allow envelope creation.'),
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
.envelope-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.envelope-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.envelope-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.envelope-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
