<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signing-mode-rule-editor">
		<fieldset class="signing-mode-rule-editor__mode">
			<legend class="signing-mode-rule-editor__section-label">
				<!-- TRANSLATORS Section title describing how signature jobs are executed. -->
				{{ t('libresign', 'How signatures are processed') }}
			</legend>
			<NcCheckboxRadioSwitch
				type="radio"
				name="signing-mode-rule-editor"
				:model-value="settings.signingMode === 'sync'"
				@update:modelValue="onModeChange('sync', $event)">
				<div class="signing-mode-rule-editor__copy">
					<!-- TRANSLATORS Option label for synchronous signing execution. -->
					<strong>{{ t('libresign', 'Process immediately') }}</strong>
					<!-- TRANSLATORS Option description: signing occurs during request lifecycle. -->
					<p>{{ t('libresign', 'Documents are signed during the request lifecycle.') }}</p>
				</div>
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch
				type="radio"
				name="signing-mode-rule-editor"
				:model-value="settings.signingMode === 'async'"
				@update:modelValue="onModeChange('async', $event)">
				<div class="signing-mode-rule-editor__copy">
					<!-- TRANSLATORS Option label for asynchronous/background signing execution. -->
					<strong>{{ t('libresign', 'Process in background') }}</strong>
					<!-- TRANSLATORS Option description: signing jobs are queued and processed asynchronously. -->
					<p>{{ t('libresign', 'Documents are queued and signed asynchronously.') }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</fieldset>

		<section
			v-if="editorScope === 'system' && settings.signingMode === 'async'"
			class="signing-mode-rule-editor__infrastructure">
			<fieldset class="signing-mode-rule-editor__worker-type">
				<legend class="signing-mode-rule-editor__worker-label">
					<!-- TRANSLATORS Section label for selecting worker service implementation. -->
					{{ t('libresign', 'Worker service') }}
				</legend>

				<NcCheckboxRadioSwitch
					type="radio"
					name="worker-config-type"
					:model-value="settings.workerType === 'local'"
					@update:modelValue="onWorkerTypeChange('local', $event)">
					<div class="signing-mode-rule-editor__copy">
						<!-- TRANSLATORS Option label for built-in/local worker service. -->
						<strong>{{ t('libresign', 'Local worker') }}</strong>
						<!-- TRANSLATORS Option description for local worker processing on Nextcloud instance. -->
						<p>{{ t('libresign', 'Nextcloud processes signing jobs locally.') }}</p>
					</div>
				</NcCheckboxRadioSwitch>

				<div v-if="settings.workerType === 'local'" class="signing-mode-rule-editor__local-config">
					<div class="signing-mode-rule-editor__parallel">
						<label for="signing-mode-parallel-input" class="signing-mode-rule-editor__parallel-label">
							<!-- TRANSLATORS Label for maximum number of parallel signing jobs. -->
							{{ t('libresign', 'Concurrent jobs') }}
						</label>
						<div class="signing-mode-rule-editor__parallel-input-row">
							<input
								id="signing-mode-parallel-input"
								type="number"
								min="1"
								max="32"
								class="signing-mode-rule-editor__parallel-input"
								:value="localParallelValue"
								aria-describedby="signing-mode-parallel-helper"
								@input="onParallelChange($event.target.value)"
								@blur="emitNormalizedParallel" />
						</div>
						<p id="signing-mode-parallel-helper" class="signing-mode-rule-editor__parallel-helper">
							<!-- TRANSLATORS Helper text explaining the concurrent jobs limit. -->
							{{ t('libresign', 'Maximum concurrent signing jobs.') }}
						</p>
					</div>
				</div>

				<NcCheckboxRadioSwitch
					type="radio"
					name="worker-config-type"
					:model-value="settings.workerType === 'external'"
					@update:modelValue="onWorkerTypeChange('external', $event)">
					<div class="signing-mode-rule-editor__copy">
						<!-- TRANSLATORS Option label for external worker service integration. -->
						<strong>{{ t('libresign', 'External worker') }}</strong>
						<!-- TRANSLATORS Option description for processing signing jobs outside Nextcloud. -->
						<p>{{ t('libresign', 'Signing jobs are processed by an external service.') }}</p>
					</div>
				</NcCheckboxRadioSwitch>
			</fieldset>

		</section>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { SigningModeValue, WorkerTypeValue } from './model'
import { getDefaultWorkerConfig, normalizeSigningExecutionSettings, resolveParallelWorkers } from './model'

defineOptions({
	name: 'SigningModeRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
	editorScope?: 'system' | 'group' | 'user'
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const settings = computed(() => normalizeSigningExecutionSettings(props.modelValue))
const localParallelValue = ref(String(settings.value.parallelWorkers))

watch(() => settings.value.parallelWorkers, (value) => {
	localParallelValue.value = String(value)
})

function onModeChange(mode: SigningModeValue, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', {
		...settings.value,
		signingMode: mode,
	})
}

function onWorkerTypeChange(workerType: WorkerTypeValue, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', {
		...settings.value,
		workerType,
		parallelWorkers: workerType === 'external'
			? getDefaultWorkerConfig().parallelWorkers
			: settings.value.parallelWorkers,
	})
}

function onParallelChange(value: string | number) {
	if (settings.value.workerType === 'external') {
		return
	}

	localParallelValue.value = String(value)
	const parsed = Number.parseInt(String(value), 10)
	if (!Number.isNaN(parsed) && parsed >= 1 && parsed <= 32) {
		emit('update:modelValue', {
			...settings.value,
			parallelWorkers: parsed,
		})
	}
}

function emitNormalizedParallel() {
	if (settings.value.workerType === 'external') {
		return
	}

	const normalized = resolveParallelWorkers(localParallelValue.value)
	localParallelValue.value = String(normalized)
	emit('update:modelValue', {
		...settings.value,
		parallelWorkers: normalized,
	})
}
</script>

<style scoped lang="scss">
.signing-mode-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;

	&__mode,
	&__worker-type {
		border: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 0.4rem;
	}

	&__section-label {
		font-weight: 600;
		margin: 0 0 0.15rem;
		font-size: 0.95rem;
	}

	&__worker-label {
		font-weight: 500;
		margin: 0 0 0.25rem;
		font-size: 0.9rem;
		color: var(--color-text-lighter);
	}

	&__infrastructure {
		padding: 0.6rem 0.7rem;
		margin-left: 0.8rem;
		background-color: var(--color-background-hover);
		border-radius: 6px;
		display: flex;
		flex-direction: column;
		gap: 0.6rem;
	}

	&__parallel {
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
	}

	&__local-config {
		margin: -0.1rem 0 0.1rem 1.75rem;
		padding-left: 0.75rem;
		border-left: 2px solid var(--color-border-maxcontrast);
	}

	&__parallel-label {
		font-weight: 500;
		color: var(--color-main-text);
		font-size: 0.9rem;
		display: block;
		margin-bottom: 0.1rem;
	}

	&__parallel-input-row {
		display: flex;
		align-items: center;
	}

	&__parallel-input {
		width: 80px;
		padding: 0.5rem 0.6rem;
		border: 1px solid var(--color-border);
		border-radius: 4px;
		font-size: 0.9rem;
		font-family: inherit;
		color: var(--color-main-text);
		background-color: var(--color-main-background);
		transition: border-color 0.2s, box-shadow 0.2s;

		&:hover {
			border-color: var(--color-border-dark);
		}

		&:focus {
			border-color: var(--color-primary-element);
			box-shadow: 0 0 0 2px var(--color-primary-element-light);
			outline: none;
		}

		/* Hide spinner buttons for cleaner look */
		&::-webkit-outer-spin-button,
		&::-webkit-inner-spin-button {
			-webkit-appearance: none;
			margin: 0;
		}
		&[type='number'] {
			appearance: textfield;
			-moz-appearance: textfield;
		}
	}

	&__parallel-helper {
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.8rem;
		line-height: 1.4;
	}

	&__copy p {
		margin: 0.15rem 0 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.85rem;
		line-height: 1.3;
	}
}
</style>
