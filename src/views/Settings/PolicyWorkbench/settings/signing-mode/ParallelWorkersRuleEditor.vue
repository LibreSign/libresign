<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="parallel-workers-rule-editor">
		<p>{{ t('libresign', 'Set how many background workers process signing jobs in parallel (1-32).') }}</p>
		<NcTextField
			id="parallel-workers-policy-input"
			:label="t('libresign', 'Parallel workers')"
			type="number"
			min="1"
			max="32"
			:model-value="localValue"
			@update:modelValue="onInputChange"
			@blur="emitNormalizedValue" />
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'

import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { EffectivePolicyValue } from '../../../../../types/index'
import { resolveParallelWorkers } from './model'

defineOptions({
	name: 'ParallelWorkersRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const localValue = ref(String(resolveParallelWorkers(props.modelValue)))

watch(() => props.modelValue, (value) => {
	localValue.value = String(resolveParallelWorkers(value))
})

function onInputChange(value: string | number) {
	localValue.value = String(value)
	const parsed = Number.parseInt(String(value), 10)
	if (!Number.isNaN(parsed) && parsed >= 1 && parsed <= 32) {
		emit('update:modelValue', parsed)
	}
}

function emitNormalizedValue() {
	const normalized = resolveParallelWorkers(localValue.value)
	localValue.value = String(normalized)
	emit('update:modelValue', normalized)
}
</script>

<style scoped lang="scss">
.parallel-workers-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;

	p {
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
	}

	:deep(.nc-text-field) {
		max-width: 140px;
	}
}
</style>
