<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="notification-delivery-rule-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			type="radio"
			:model-value="selected === option.value"
			name="notification-delivery-rule-editor"
			@update:model-value="onChange(option.value, $event)">
			<strong>{{ option.label }}</strong>
			<p>{{ option.description }}</p>
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue } from '../../../../../types/index'

const props = defineProps<{ modelValue: EffectivePolicyValue }>()
const emit = defineEmits<{ 'update:modelValue': [value: EffectivePolicyValue] }>()
const selected = computed(() => props.modelValue === 'async' ? 'async' : 'sync')

const options = [
	{
		value: 'sync',
		// TRANSLATORS Synchronous notification processing option.
		label: t('libresign', 'Synchronous'),
		// TRANSLATORS Description of synchronous notification processing.
		description: t('libresign', 'Process notifications during the current operation. This is the default mode.'),
	},
	{
		value: 'async',
		// TRANSLATORS Asynchronous notification processing option.
		label: t('libresign', 'Asynchronous'),
		// TRANSLATORS Description of asynchronous notification processing.
		description: t('libresign', 'Queue notifications for background processing. Delivery timing depends on the available job runners.'),
	},
] as const

function onChange(value: 'sync' | 'async', checked?: unknown): void {
	if (checked !== false) {
		emit('update:modelValue', value)
	}
}
</script>

<style scoped lang="scss">
.notification-delivery-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
