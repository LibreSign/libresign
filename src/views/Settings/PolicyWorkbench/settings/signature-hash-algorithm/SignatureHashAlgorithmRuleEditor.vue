<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-hash-rule-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			type="radio"
			:model-value="selected === option.value"
			name="signature-hash-rule-editor"
			class="signature-hash-rule-editor__option"
			@update:modelValue="onChange(option.value, $event)">
			<div class="signature-hash-rule-editor__copy">
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
import { HASH_ALGORITHMS, normalizeHashAlgorithm, type HashAlgorithm } from './model'

defineOptions({
	name: 'SignatureHashAlgorithmRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const selected = computed(() => normalizeHashAlgorithm(props.modelValue))

const options = HASH_ALGORITHMS.map((algorithm) => ({
	value: algorithm,
	label: algorithm,
	description: t('libresign', 'Use {algorithm} as the signature digest algorithm.', {
		algorithm,
	}),
}))

function onChange(nextValue: HashAlgorithm, selectedOption?: unknown): void {
	if (selectedOption === false) {
		return
	}

	emit('update:modelValue', nextValue)
}
</script>

<style scoped lang="scss">
.signature-hash-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.signature-hash-rule-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signature-hash-rule-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}
}
</style>
