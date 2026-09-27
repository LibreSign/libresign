<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signer-ip-geolocation-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			class="signer-ip-geolocation-editor__option"
			type="radio"
			:model-value="normalizedMode === option.value"
			name="signer-ip-geolocation-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="signer-ip-geolocation-editor__copy">
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
	normalizeSignerIpGeolocationValue,
	resolveSignerIpGeolocationMode,
	type SignerIpGeolocationMode,
} from './model'

defineOptions({
	name: 'SignerIpGeolocationRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options: Array<{ value: SignerIpGeolocationMode, label: string, description: string }> = [
	{
		value: 'disabled',
		// TRANSLATORS Radio option that turns off IP-based approximate location.
		label: t('libresign', 'Disabled'),
		// TRANSLATORS Description for disabled IP geolocation mode.
		description: t('libresign', 'Do not resolve or store an approximate location from the signer IP address.'),
	},
	{
		value: 'enabled',
		// TRANSLATORS Radio option that turns on IP-based approximate location.
		label: t('libresign', 'Enabled'),
		// TRANSLATORS Description explaining IP geolocation uses the signer IP and the local GeoIP database.
		description: t('libresign', 'Use the signer IP address and the locally configured GeoIP database to store an approximate location. This is not a per-signer requester option.'),
	},
]

const normalizedMode = computed<SignerIpGeolocationMode | null>(() => resolveSignerIpGeolocationMode(props.modelValue))

function onChange(mode: SignerIpGeolocationMode, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', normalizeSignerIpGeolocationValue({ mode }))
}
</script>

<style scoped lang="scss">
.signer-ip-geolocation-editor {
	display: flex;
	flex-direction: column;
	gap: 0.6rem;

	&__copy p {
		margin: 0.2rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	:deep(.signer-ip-geolocation-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signer-ip-geolocation-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.signer-ip-geolocation-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
