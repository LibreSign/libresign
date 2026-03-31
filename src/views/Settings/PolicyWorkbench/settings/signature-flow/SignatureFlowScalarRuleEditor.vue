<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signature-flow-scalar-editor">
		<div class="signature-flow-scalar-editor__options">
			<NcCheckboxRadioSwitch
				v-for="flow in flows"
				:key="flow.value"
				type="radio"
				:model-value="normalizedValue === flow.value"
				name="signature-flow-scalar-editor"
				@update:modelValue="onFlowChange(flow.value, $event)">
				<div class="signature-flow-scalar-editor__copy">
					<strong>{{ flow.label }}</strong>
					<p>{{ flow.description }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import type { EffectivePolicyValue, SignatureFlowMode } from '../../../../../types/index'

defineOptions({
	name: 'SignatureFlowScalarRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const flows: Array<{ value: SignatureFlowMode | 'none', label: string, description: string }> = [
	{
		value: 'parallel',
		label: t('libresign', 'Simultaneous (Parallel)'),
		description: t('libresign', 'All signers receive the document at the same time and can sign in any order.'),
	},
	{
		value: 'ordered_numeric',
		label: t('libresign', 'Sequential'),
		description: t('libresign', 'Signers follow a defined order. Only the next signer can proceed.'),
	},
	{
		value: 'none',
		label: t('libresign', 'Let users choose'),
		description: t('libresign', 'Users can choose between simultaneous or sequential signing.'),
	},
]

const normalizedValue = computed<SignatureFlowMode | 'none' | null>(() => {
	const value = props.modelValue
	if (value === 'parallel' || value === 'ordered_numeric' || value === 'none') {
		return value
	}

	return null
})

function onFlowChange(flow: SignatureFlowMode | 'none', selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', flow)
}
</script>

<style scoped lang="scss">
.signature-flow-scalar-editor {
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
