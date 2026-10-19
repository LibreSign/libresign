<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="expiration-rule-editor">
		<NcTextField
			:model-value="String(expiryInDays)"
			type="number"
			:min="1"
			:step="1"
			:label="expiryInDaysLabel"
			@update:modelValue="onValueChange" />
		<p class="expiration-rule-editor__helper">
			{{ expiryInDaysDescription }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import { DEFAULT_EXPIRY_IN_DAYS, normalizePositiveInt } from './model'

defineOptions({
	name: 'ExpiryInDaysRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

// TRANSLATORS Label for the number field that defines how many days generated certificates remain valid.
const expiryInDaysLabel = t('libresign', 'Certificate validity (days)')

// TRANSLATORS Helper explaining that certificate validity changes apply only to certificates created afterwards.
const expiryInDaysDescription = t('libresign', 'Changing this value affects only signer certificates created afterwards. Certificates that already exist remain unchanged.')

const expiryInDays = computed(() => normalizePositiveInt(props.modelValue, DEFAULT_EXPIRY_IN_DAYS))

function onValueChange(nextValue: string | number): void {
	emit('update:modelValue', normalizePositiveInt(String(nextValue), DEFAULT_EXPIRY_IN_DAYS))
}
</script>

<style scoped lang="scss">
.expiration-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}
</style>
