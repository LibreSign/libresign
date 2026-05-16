<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="worker-type-rule-editor">
		<NcCheckboxRadioSwitch
			type="radio"
			name="worker-type-rule-editor"
			:model-value="normalizedValue === 'local'"
			@update:modelValue="onWorkerTypeChange('local', $event)">
			<div class="worker-type-rule-editor__copy">
				<strong>{{ t('libresign', 'Local worker') }}</strong>
				<p>{{ t('libresign', 'Nextcloud manages and executes background workers locally.') }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			type="radio"
			name="worker-type-rule-editor"
			:model-value="normalizedValue === 'external'"
			@update:modelValue="onWorkerTypeChange('external', $event)">
			<div class="worker-type-rule-editor__copy">
				<strong>{{ t('libresign', 'External worker') }}</strong>
				<p>{{ t('libresign', 'An external service processes signing jobs.') }}</p>
			</div>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { WorkerTypeValue } from './model'
import { resolveWorkerType } from './model'

defineOptions({
	name: 'WorkerTypeRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const normalizedValue = computed<WorkerTypeValue>(() => resolveWorkerType(props.modelValue))

function onWorkerTypeChange(workerType: WorkerTypeValue, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', workerType)
}
</script>

<style scoped lang="scss">
.worker-type-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
