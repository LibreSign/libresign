<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="collect-metadata-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="String(option.value)"
			class="collect-metadata-editor__option"
			type="radio"
			:model-value="normalizedValue === option.value"
			name="collect-metadata-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="collect-metadata-editor__copy">
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

defineOptions({
	name: 'CollectMetadataRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

// TRANSLATORS Radio option label in the policy editor that enables storing signer metadata such as IP address and browser information.
const collectSignerMetadataLabel = t('libresign', 'Collect signer metadata')
// TRANSLATORS Description for the radio option that enables storing signer metadata in the signed document audit/signing metadata.
const collectSignerMetadataDescription = t('libresign', 'Store signer IP address and browser information in signing metadata.')
// TRANSLATORS Radio option label in the policy editor that disables storing signer metadata.
const disableMetadataCollectionLabel = t('libresign', 'Disable metadata collection')
// TRANSLATORS Description for the radio option that disables storing signer metadata such as IP address and browser information.
const disableMetadataCollectionDescription = t('libresign', 'Do not store signer IP address or browser information in signing metadata.')

const options = [
	{
		value: true,
		label: collectSignerMetadataLabel,
		description: collectSignerMetadataDescription,
	},
	{
		value: false,
		label: disableMetadataCollectionLabel,
		description: disableMetadataCollectionDescription,
	},
]

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

function onChange(value: boolean, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', value)
}
</script>

<style scoped lang="scss">
.collect-metadata-editor {
	display: flex;
	flex-direction: column;
	gap: 0.6rem;

	&__copy p {
		margin: 0.2rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.collect-metadata-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.collect-metadata-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.collect-metadata-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
