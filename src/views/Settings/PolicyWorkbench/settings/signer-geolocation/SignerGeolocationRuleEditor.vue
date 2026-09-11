<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signer-geolocation-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			class="signer-geolocation-editor__option"
			type="radio"
			:model-value="normalizedMode === option.value"
			name="signer-geolocation-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="signer-geolocation-editor__copy">
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
import {
	normalizeSignerGeolocationValue,
	resolveSignerGeolocationMode,
	type SignerGeolocationMode,
} from './model'

defineOptions({
	name: 'SignerGeolocationRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options: Array<{ value: SignerGeolocationMode, label: string, description: string }> = [
	{
		value: 'disabled',
		// TRANSLATORS Radio option that turns off device geolocation for signing.
		label: t('libresign', 'Disabled'),
		// TRANSLATORS Description for disabled geolocation mode.
		description: t('libresign', 'Do not request or store device-reported location when signing.'),
	},
	{
		value: 'optional',
		// TRANSLATORS Radio option that lets requesters require device location for selected signers.
		label: t('libresign', 'Optional'),
		// TRANSLATORS Description clarifying optional means requester elevation, not automatic soft collection.
		description: t('libresign', 'Requesters may require device location for selected signers. Location is only collected when required for that signer.'),
	},
	{
		value: 'required',
		// TRANSLATORS Radio option that makes device location mandatory for every signer.
		label: t('libresign', 'Required'),
		// TRANSLATORS Description for required geolocation mode.
		description: t('libresign', 'Every signer must provide device-reported location to complete the signature.'),
	},
]

const normalizedMode = computed<SignerGeolocationMode | null>(() => resolveSignerGeolocationMode(props.modelValue))

function onChange(mode: SignerGeolocationMode, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', normalizeSignerGeolocationValue({ mode }))
}
</script>

<style scoped lang="scss">
.signer-geolocation-editor {
	display: flex;
	flex-direction: column;
	gap: 0.6rem;

	&__copy p {
		margin: 0.2rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.signer-geolocation-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signer-geolocation-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.signer-geolocation-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
