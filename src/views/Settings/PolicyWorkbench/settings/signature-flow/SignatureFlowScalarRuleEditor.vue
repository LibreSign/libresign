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
				class="signature-flow-scalar-editor__option"
				type="radio"
				:disabled="flow.disabled"
				:model-value="normalizedValue === flow.value"
				name="signature-flow-scalar-editor"
				@update:modelValue="onFlowChange(flow.value, flow.disabled, $event)">
				<div class="signature-flow-scalar-editor__copy">
					<strong>{{ flow.label }}</strong>
					<p>{{ flow.description }}</p>
				</div>
			</NcCheckboxRadioSwitch>
		</div>
		<p v-if="isInstanceCreateMode" class="signature-flow-scalar-editor__hint">
			{{ t('libresign', 'To create a rule for everyone, choose Simultaneous or Sequential. "User choice" already matches the default and is not saved as a custom rule.') }}
		</p>
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
	editorScope?: 'system' | 'group' | 'user'
	editorMode?: 'create' | 'edit' | null
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const baseFlows: Array<{ value: SignatureFlowMode | 'none', label: string, description: string }> = [
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
		label: t('libresign', 'User choice'),
		description: t('libresign', 'Users can choose between simultaneous or sequential signing.'),
	},
]

const isInstanceCreateMode = computed(() => {
	return props.editorScope === 'system' && props.editorMode === 'create'
})

const flows = computed(() => {
	return baseFlows.map((flow) => {
		if (flow.value === 'none' && isInstanceCreateMode.value) {
			return {
				...flow,
				disabled: true,
				description: t('libresign', 'Already the default for this setting. Choose another option to create an explicit custom rule.'),
			}
		}

		return {
			...flow,
			disabled: false,
		}
	})
})

const normalizedValue = computed<SignatureFlowMode | 'none' | null>(() => {
	const value = props.modelValue
	if (value === 'parallel' || value === 'ordered_numeric' || value === 'none') {
		return value
	}

	return null
})

function onFlowChange(flow: SignatureFlowMode | 'none', disabled: boolean, selected?: unknown) {
	if (disabled || selected === false) {
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

	&__option {
		width: 100%;
	}

	&__copy p {
		margin: 0.35rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__hint {
		margin: 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
	}

	:deep(.signature-flow-scalar-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signature-flow-scalar-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.signature-flow-scalar-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
