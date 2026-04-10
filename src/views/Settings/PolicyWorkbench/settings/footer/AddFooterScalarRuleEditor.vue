<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="add-footer-scalar-editor">
		<div class="add-footer-scalar-editor__options">
			<NcCheckboxRadioSwitch
				v-for="option in options"
				:key="option.value ? 'enabled' : 'disabled'"
				class="add-footer-scalar-editor__option"
				type="radio"
				:model-value="normalizedValue === option.value"
				name="add-footer-scalar-editor"
				@update:modelValue="onValueChange(option.value, $event)">
				<div class="add-footer-scalar-editor__copy">
					<strong>{{ option.label }}</strong>
					<p>{{ option.description }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'

defineOptions({
	name: 'AddFooterScalarRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options: Array<{ value: boolean, label: string, description: string }> = [
	{
		value: true,
		label: t('libresign', 'Enabled'),
		description: t('libresign', 'Signed files include the footer with signature validation information.'),
	},
	{
		value: false,
		label: t('libresign', 'Disabled'),
		description: t('libresign', 'Signed files are generated without the footer block.'),
	},
]

const normalizedValue = computed<boolean | null>(() => {
	const value = props.modelValue
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		return value === 1
	}

	if (typeof value === 'string') {
		if (['1', 'true', 'yes', 'on'].includes(value.toLowerCase())) {
			return true
		}

		if (['0', 'false', 'no', 'off'].includes(value.toLowerCase())) {
			return false
		}
	}

	return null
})

function onValueChange(value: boolean, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', value)
}
</script>

<style scoped lang="scss">
.add-footer-scalar-editor {
	display: flex;
	flex-direction: column;
	gap: 1rem;

	&__options {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__option {
		width: 100%;
	}

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.add-footer-scalar-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.add-footer-scalar-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.add-footer-scalar-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>