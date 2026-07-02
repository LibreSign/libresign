<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="worker-config-rule-editor">
		<fieldset class="worker-config-rule-editor__worker-type">
			<legend class="worker-config-rule-editor__section-label">
				{{ t('libresign', 'Worker service type') }}
			</legend>

			<NcCheckboxRadioSwitch
				type="radio"
				name="worker-config-type"
				:model-value="config.workerType === 'local'"
				@update:modelValue="onWorkerTypeChange('local', $event)">
				<div class="worker-config-rule-editor__copy">
					<strong>{{ t('libresign', 'Local worker') }}</strong>
					<p>{{ t('libresign', 'Nextcloud manages and executes background workers locally.') }}</p>
				</div>
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch
				type="radio"
				name="worker-config-type"
				:model-value="config.workerType === 'external'"
				@update:modelValue="onWorkerTypeChange('external', $event)">
				<div class="worker-config-rule-editor__copy">
					<strong>{{ t('libresign', 'External worker') }}</strong>
					<p>{{ t('libresign', 'An external service processes signing jobs.') }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</fieldset>

		<div class="worker-config-rule-editor__parallel">
			<p class="worker-config-rule-editor__parallel-description">
				{{ t('libresign', 'Defines how many signing jobs may run concurrently.') }}
			</p>
			<p v-if="config.workerType === 'external'" class="worker-config-rule-editor__parallel-hint">
				{{ t('libresign', 'Parallel workers is managed by the external worker service.') }}
			</p>
			<NcTextField
				id="worker-config-parallel-input"
				:label="t('libresign', 'Parallel workers')"
				type="number"
				min="1"
				max="32"
				:disabled="config.workerType === 'external'"
				:model-value="localParallelValue"
				@update:modelValue="onParallelChange"
				@blur="emitNormalizedParallel" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { WorkerTypeValue } from './model'
import {
	getDefaultWorkerConfig,
	normalizeWorkerConfig,
	resolveParallelWorkers,
	serializeWorkerConfig,
} from './model'

defineOptions({
	name: 'WorkerConfigRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const config = computed(() => normalizeWorkerConfig(props.modelValue))

const localParallelValue = ref(String(config.value.parallelWorkers))

watch(() => config.value.parallelWorkers, (val) => {
	localParallelValue.value = String(val)
})

function onWorkerTypeChange(workerType: WorkerTypeValue, selected?: unknown) {
	if (selected === false) {
		return
	}

	const updated = {
		...config.value,
		workerType,
		// Reset parallel workers to default when switching to external (irrelevant field)
		parallelWorkers: workerType === 'external' ? getDefaultWorkerConfig().parallelWorkers : config.value.parallelWorkers,
	}
	emit('update:modelValue', serializeWorkerConfig(updated))
}

function onParallelChange(value: string | number) {
	if (config.value.workerType === 'external') {
		return
	}

	localParallelValue.value = String(value)
	const parsed = Number.parseInt(String(value), 10)
	if (!Number.isNaN(parsed) && parsed >= 1 && parsed <= 32) {
		emit('update:modelValue', serializeWorkerConfig({ ...config.value, parallelWorkers: parsed }))
	}
}

function emitNormalizedParallel() {
	if (config.value.workerType === 'external') {
		return
	}

	const normalized = resolveParallelWorkers(localParallelValue.value)
	localParallelValue.value = String(normalized)
	emit('update:modelValue', serializeWorkerConfig({ ...config.value, parallelWorkers: normalized }))
}
</script>

<style scoped lang="scss">
.worker-config-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 1.25rem;

	&__worker-type {
		border: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__section-label {
		font-weight: 600;
		margin-bottom: 0.25rem;
	}

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__parallel {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	&__parallel-description {
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
	}

	&__parallel-hint {
		margin: 0;
		font-size: 0.82rem;
		color: var(--color-text-maxcontrast);
	}

	:deep(.nc-text-field) {
		max-width: 140px;
	}
}
</style>
