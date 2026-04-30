<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="validation-access-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="String(option.value)"
			class="validation-access-editor__option"
			type="radio"
			:model-value="normalizedValue === option.value"
			name="validation-access-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="validation-access-editor__copy">
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
	name: 'ValidationAccessRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options = [
	{
		value: false,
		label: t('libresign', 'Public validation page'),
		description: t('libresign', 'Anyone with the validation URL can access the validation page.'),
	},
	{
		value: true,
		label: t('libresign', 'Authenticated-only validation page'),
		description: t('libresign', 'Users must be authenticated to access the validation page URL.'),
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
.validation-access-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.validation-access-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.validation-access-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.validation-access-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
