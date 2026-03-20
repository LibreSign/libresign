<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-flow-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="modelValue.enabled"
			@update:modelValue="onEnabledChange">
			<span>{{ t('libresign', 'Enable a signing order override for this target') }}</span>
		</NcCheckboxRadioSwitch>

		<div v-if="modelValue.enabled" class="signature-flow-rule-editor__options">
			<NcCheckboxRadioSwitch
				v-for="flow in flows"
				:key="flow.value"
				type="radio"
				:model-value="modelValue.flow === flow.value"
				name="signature-flow-rule-editor"
				@update:modelValue="onFlowChange(flow.value)">
				<div class="signature-flow-rule-editor__copy">
					<strong>{{ flow.label }}</strong>
					<p>{{ flow.description }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { SignatureFlowMode, SignatureFlowRuleValue } from '../../types'

defineOptions({
	name: 'SignatureFlowRuleEditor',
})

const props = defineProps<{
	modelValue: SignatureFlowRuleValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: SignatureFlowRuleValue]
}>()

const flows: Array<{ value: SignatureFlowMode, label: string, description: string }> = [
	{
		value: 'parallel',
		label: t('libresign', 'Simultaneous (Parallel)'),
		description: t('libresign', 'All signers receive the document at the same time and can sign in any order.'),
	},
	{
		value: 'ordered_numeric',
		label: t('libresign', 'Sequential'),
		description: t('libresign', 'Signers are organized by signing order number. Only those with the lowest pending order number can sign.'),
	},
]

function updateValue(nextValue: Partial<SignatureFlowRuleValue>) {
	emit('update:modelValue', {
		...props.modelValue,
		...nextValue,
	})
}

function onEnabledChange(enabled: boolean) {
	updateValue({
		enabled,
	})
}

function onFlowChange(flow: SignatureFlowMode) {
	updateValue({
		flow,
	})
}
</script>

<style scoped lang="scss">
.signature-flow-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 1rem;

	&__options {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
