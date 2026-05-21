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
				:label="t('libresign', 'Maximum validity (seconds)')"
				@update:modelValue="onMaximumValidityChange" />
			<p class="expiration-rule-editor__helper">
				{{ t('libresign', 'Leave empty to disable expiration.') }}
			</p>
		</div>

		<div class="expiration-rule-editor__field">
			<NcTextField
				:model-value="renewalIntervalField"
				type="number"
				:min="1"
				:step="1"
				:label="t('libresign', 'Renewal interval (seconds)')"
				:error="renewalRequiresExpiration"
				@update:modelValue="onRenewalIntervalChange" />
			<p class="expiration-rule-editor__helper">
				{{ t('libresign', 'Leave empty to disable renewal.') }}
			</p>
			<p class="expiration-rule-editor__helper expiration-rule-editor__helper--secondary">
					{{ t('libresign', 'Users may renew the signing request after expiration using the access link.') }}
			</p>
			<p v-if="renewalRequiresExpiration" class="expiration-rule-editor__validation" role="alert">
				{{ t('libresign', 'Maximum validity is required when renewal interval is set.') }}
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
