<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="confetti-editor">
		<NcCheckboxRadioSwitch
			class="confetti-editor__switch"
			type="switch"
			:model-value="normalizedValue === true"
			@update:modelValue="onChange">
			<div class="confetti-editor__copy">
				<strong>{{ title }}</strong>
				<p>{{ description }}</p>
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
	name: 'ConfettiRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const title = t('libresign', 'Confetti animation')
const description = t('libresign', 'Show a confetti animation after successful signing.')

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

function onChange(enabled: boolean) {
	emit('update:modelValue', enabled)
}
</script>

<style scoped lang="scss">
.confetti-editor {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.confetti-editor__switch.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.confetti-editor__switch .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.confetti-editor__switch.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
