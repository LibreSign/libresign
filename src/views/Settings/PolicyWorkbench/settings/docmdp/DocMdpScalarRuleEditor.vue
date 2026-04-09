<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="docmdp-scalar-editor">
		<div class="docmdp-scalar-editor__options">
			<NcCheckboxRadioSwitch
				v-for="level in levels"
				:key="level.value"
				class="docmdp-scalar-editor__option"
				type="radio"
				:model-value="normalizedValue === level.value"
				name="docmdp-scalar-editor"
				@update:modelValue="onLevelChange(level.value, $event)">
				<div class="docmdp-scalar-editor__copy">
					<strong>{{ level.label }}</strong>
					<p>{{ level.description }}</p>
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
	name: 'DocMdpScalarRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const levels: Array<{ value: number, label: string, description: string }> = [
	{
		value: 0,
		label: t('libresign', 'Disabled'),
		description: t('libresign', 'Do not apply DocMDP certification by default.'),
	},
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
]

const normalizedValue = computed<number | null>(() => {
	const value = props.modelValue
	if (typeof value === 'number' && value >= 0 && value <= 3) {
		return value
	}

	if (typeof value === 'string' && /^[0-3]$/.test(value)) {
		return Number(value)
	}

	return null
})

function onLevelChange(level: number, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', level)
}
</script>

<style scoped lang="scss">
.docmdp-scalar-editor {
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

	:deep(.docmdp-scalar-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.docmdp-scalar-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.docmdp-scalar-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
