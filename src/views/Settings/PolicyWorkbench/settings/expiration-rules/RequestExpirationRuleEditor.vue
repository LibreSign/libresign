<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="expiration-rule-editor">
		<div class="expiration-rule-editor__field">
			<NcTextField
				:model-value="maximumValidityField"
				type="number"
				:min="1"
				:step="1"
				:label="maximumValidityLabel"
				@update:modelValue="onMaximumValidityChange" />
			<p class="expiration-rule-editor__helper">
				{{ disableExpirationHelperText }}
			</p>
		</div>

		<div class="expiration-rule-editor__field">
			<NcTextField
				:model-value="renewalIntervalField"
				type="number"
				:min="1"
				:step="1"
				:label="renewalIntervalLabel"
				:error="renewalRequiresExpiration"
				@update:modelValue="onRenewalIntervalChange" />
			<p class="expiration-rule-editor__helper">
				{{ disableRenewalHelperText }}
			</p>
			<p class="expiration-rule-editor__helper expiration-rule-editor__helper--secondary">
				{{ renewalIntervalDescription }}
			</p>
			<p v-if="renewalRequiresExpiration" class="expiration-rule-editor__validation" role="alert">
				{{ renewalRequiresExpirationMessage }}
			</p>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	normalizeRequestExpirationDraftValue,
	type RequestExpirationDraftValue,
} from './model'

defineOptions({
	name: 'RequestExpirationRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

// TRANSLATORS Label for the numeric field setting signature request expiration time in seconds.
const maximumValidityLabel = t('libresign', 'Maximum validity (seconds)')
// TRANSLATORS Helper text indicating that leaving the expiration field empty disables request expiration.
const disableExpirationHelperText = t('libresign', 'Leave empty to disable expiration.')
// TRANSLATORS Label for the numeric field setting the renewal interval in seconds for an expired request link.
const renewalIntervalLabel = t('libresign', 'Renewal interval (seconds)')
// TRANSLATORS Helper text indicating that leaving the renewal field empty disables renewal.
const disableRenewalHelperText = t('libresign', 'Leave empty to disable renewal.')
// TRANSLATORS Secondary helper text explaining that accounts can renew access to a signing request after it expires.
const renewalIntervalDescription = t('libresign', 'Accounts may renew the signing request after expiration using the access link.')
// TRANSLATORS Validation error shown when a renewal interval is entered without configuring a maximum validity.
const renewalRequiresExpirationMessage = t('libresign', 'Maximum validity is required when renewal interval is set.')

const normalized = computed(() => normalizeRequestExpirationDraftValue(props.modelValue))
const maximumValidityField = computed(() => normalized.value.maximumValidity > 0 ? String(normalized.value.maximumValidity) : '')
const renewalIntervalField = computed(() => normalized.value.renewalInterval > 0 ? String(normalized.value.renewalInterval) : '')
const renewalRequiresExpiration = computed(() => {
	return normalized.value.renewalInterval > 0 && normalized.value.maximumValidity <= 0
})

function updateValue(next: Partial<RequestExpirationDraftValue>) {
	emit('update:modelValue', {
		...normalized.value,
		...next,
	})
}

function normalizeFieldValue(nextValue: string | number): number {
	const text = String(nextValue ?? '').trim()
	if (text.length === 0) {
		return 0
	}

	const parsed = Number.parseInt(text, 10)
	if (!Number.isFinite(parsed)) {
		return 0
	}

	return Math.max(0, parsed)
}

function onMaximumValidityChange(nextValue: string | number): void {
	updateValue({
		maximumValidity: normalizeFieldValue(nextValue),
	})
}

function onRenewalIntervalChange(nextValue: string | number): void {
	updateValue({
		renewalInterval: normalizeFieldValue(nextValue),
	})
}
</script>

<style scoped lang="scss">
.expiration-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;

	&__field {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}

	&__helper {
		margin: 0;
		font-size: 0.82rem;
		color: var(--color-text-maxcontrast);
	}

	&__helper--secondary {
		font-size: 0.8rem;
	}

	&__validation {
		margin: 0;
		font-size: 0.82rem;
		color: var(--color-error);
	}
}
</style>
