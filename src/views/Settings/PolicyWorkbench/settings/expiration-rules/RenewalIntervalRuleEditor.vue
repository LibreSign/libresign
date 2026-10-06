<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="expiration-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="enabled"
			@update:modelValue="onToggleEnabled">
			{{ renewalIntervalToggleLabel }}
		</NcCheckboxRadioSwitch>

		<NcTextField
			v-if="enabled"
			:model-value="String(valueInSeconds)"
			type="number"
			:min="1"
			:step="1"
			:label="renewalIntervalInputLabel"
			@update:modelValue="onValueChange" />
		<p class="expiration-rule-editor__helper">
			{{ renewalIntervalDescription }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import { DEFAULT_RENEWAL_INTERVAL, normalizeNonNegativeInt } from './model'

defineOptions({
	name: 'RenewalIntervalRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

// TRANSLATORS Toggle label for enabling or disabling a renewal interval on signing requests.
const renewalIntervalToggleLabel = t('libresign', 'Signer access renewal')
// TRANSLATORS Label for the numeric field that sets renewal interval in seconds for a signing request access link.
const renewalIntervalInputLabel = t('libresign', 'Renew signer access after (seconds)')
// TRANSLATORS Helper explaining that signer access/session renewal is limited to the signing request maximum validity.
const renewalIntervalDescription = t('libresign', 'Signers can renew their access/session using the access link only while the signing request is within its configured maximum validity.')

const normalized = computed(() => normalizeNonNegativeInt(props.modelValue, DEFAULT_RENEWAL_INTERVAL))
const enabled = computed(() => normalized.value > 0)
const valueInSeconds = computed(() => (normalized.value > 0 ? normalized.value : 1))

function onToggleEnabled(nextValue: boolean): void {
	if (!nextValue) {
		emit('update:modelValue', 0)
		return
	}

	emit('update:modelValue', valueInSeconds.value)
}

function onValueChange(nextValue: string | number): void {
	const parsed = normalizeNonNegativeInt(String(nextValue), 1)
	emit('update:modelValue', Math.max(1, parsed))
}
</script>

<style scoped lang="scss">
.expiration-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	&__helper {
		margin: 0;
		font-size: 0.82rem;
		color: var(--color-text-maxcontrast);
	}
}
</style>
