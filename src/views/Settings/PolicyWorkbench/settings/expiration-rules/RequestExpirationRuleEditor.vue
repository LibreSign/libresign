<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="expiration-rule-editor">
		<div class="expiration-rule-editor__section">
			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="expirationEnabled"
				@update:modelValue="onToggleExpiration">
				{{ expirationToggleLabel }}
			</NcCheckboxRadioSwitch>

			<div v-if="expirationEnabled" class="expiration-rule-editor__fields">
				<NcTextField
					:model-value="expirationAmount"
					type="number"
					:min="1"
					:step="1"
					:label="expirationAmountLabel"
					@update:modelValue="onExpirationAmountChange" />
				<NcSelect
					:model-value="selectedExpirationUnitOption"
					:options="unitOptions"
					:input-label="timeUnitSelectLabel"
					:clearable="false"
					@update:modelValue="onExpirationUnitChange" />
			</div>
		</div>

		<div class="expiration-rule-editor__section">
			<NcCheckboxRadioSwitch
				type="switch"
				:model-value="renewalEnabled"
				@update:modelValue="onToggleRenewal">
				{{ renewalToggleLabel }}
			</NcCheckboxRadioSwitch>

			<div v-if="renewalEnabled" class="expiration-rule-editor__fields">
				<NcTextField
					:model-value="renewalAmount"
					type="number"
					:min="1"
					:step="1"
					:label="renewalAmountLabel"
					:error="renewalRequiresExpiration"
					@update:modelValue="onRenewalAmountChange" />
				<NcSelect
					:model-value="selectedRenewalUnitOption"
					:options="unitOptions"
					:input-label="timeUnitSelectLabel"
					:clearable="false"
					@update:modelValue="onRenewalUnitChange" />
			</div>
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
import { computed, ref, watch } from 'vue'

import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	durationToSeconds,
	normalizeRequestExpirationDraftValue,
	secondsToDuration,
	type RequestExpirationDraftValue,
	type TimeUnit,
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

interface TimeUnitOption {
	id: TimeUnit
	label: string
}

// TRANSLATORS Toggle label for enabling signing request expiration.
const expirationToggleLabel = t('libresign', 'Enable request expiration')
// TRANSLATORS Label for numeric field setting request expiration duration.
const expirationAmountLabel = t('libresign', 'Expires after')
// TRANSLATORS Toggle label for requiring access renewal after expiration.
const renewalToggleLabel = t('libresign', 'Require access renewal')
// TRANSLATORS Label for numeric field setting access renewal interval.
const renewalAmountLabel = t('libresign', 'Renew access after')
// TRANSLATORS Accessible label for selecting time unit in expiration rules.
const timeUnitSelectLabel = t('libresign', 'Time unit')
// TRANSLATORS Secondary helper text explaining that accounts can renew access to a signing request after it expires.
const renewalIntervalDescription = t('libresign', 'Accounts may renew the signing request after expiration using the access link.')
// TRANSLATORS Validation error shown when a renewal interval is entered without configuring a maximum validity.
const renewalRequiresExpirationMessage = t('libresign', 'Maximum validity is required when renewal interval is set.')

const unitOptions = computed<TimeUnitOption[]>(() => [
	{ id: 'seconds', label: t('libresign', 'seconds') },
	{ id: 'minutes', label: t('libresign', 'minutes') },
	{ id: 'hours', label: t('libresign', 'hours') },
	{ id: 'days', label: t('libresign', 'days') },
])

const normalized = computed(() => normalizeRequestExpirationDraftValue(props.modelValue))

// Internal local reactive state for inputs
const expirationEnabled = ref(normalized.value.maximumValidity > 0)
const expirationAmount = ref<string>('')
const expirationUnit = ref<TimeUnit>('days')

const renewalEnabled = ref(normalized.value.renewalInterval > 0)
const renewalAmount = ref<string>('')
const renewalUnit = ref<TimeUnit>('hours')

function syncFromProps(val: RequestExpirationDraftValue) {
	expirationEnabled.value = val.maximumValidity > 0
	if (val.maximumValidity > 0) {
		const dur = secondsToDuration(val.maximumValidity)
		expirationAmount.value = String(dur.amount)
		expirationUnit.value = dur.unit
	} else {
		expirationAmount.value = '7'
		expirationUnit.value = 'days'
	}

	renewalEnabled.value = val.renewalInterval > 0
	if (val.renewalInterval > 0) {
		const dur = secondsToDuration(val.renewalInterval)
		renewalAmount.value = String(dur.amount)
		renewalUnit.value = dur.unit
	} else {
		renewalAmount.value = '24'
		renewalUnit.value = 'hours'
	}
}

// Initial sync without emitting update:modelValue!
syncFromProps(normalized.value)

watch(
	() => props.modelValue,
	(newVal) => {
		const norm = normalizeRequestExpirationDraftValue(newVal)
		const currentValidity = currentExpirationSeconds()
		const currentRenewal = currentRenewalSeconds()
		if (norm.maximumValidity !== currentValidity || norm.renewalInterval !== currentRenewal) {
			syncFromProps(norm)
		}
	},
)

const selectedExpirationUnitOption = computed<TimeUnitOption>(() => {
	return unitOptions.value.find((opt) => opt.id === expirationUnit.value) ?? unitOptions.value[3]
})

const selectedRenewalUnitOption = computed<TimeUnitOption>(() => {
	return unitOptions.value.find((opt) => opt.id === renewalUnit.value) ?? unitOptions.value[2]
})

function currentExpirationSeconds(): number {
	if (!expirationEnabled.value) {
		return 0
	}

	const sec = durationToSeconds(expirationAmount.value, expirationUnit.value)
	return sec ?? -1
}

function currentRenewalSeconds(): number {
	if (!renewalEnabled.value) {
		return 0
	}

	const sec = durationToSeconds(renewalAmount.value, renewalUnit.value)
	return sec ?? -1
}

const renewalRequiresExpiration = computed(() => {
	return renewalEnabled.value && !expirationEnabled.value
})

function emitValue() {
	const maximumValidity = currentExpirationSeconds()
	const renewalInterval = currentRenewalSeconds()
	emit('update:modelValue', {
		maximumValidity,
		renewalInterval,
	})
}

function onToggleExpiration(enabled: boolean): void {
	expirationEnabled.value = enabled
	if (enabled && (!expirationAmount.value || expirationAmount.value === '0')) {
		expirationAmount.value = '7'
		expirationUnit.value = 'days'
	}
	emitValue()
}

function onExpirationAmountChange(val: string | number): void {
	expirationAmount.value = String(val ?? '')
	emitValue()
}

function onExpirationUnitChange(opt: TimeUnitOption | TimeUnit | string | null): void {
	const unitId = (typeof opt === 'object' && opt ? opt.id : opt) as TimeUnit
	if (unitOptions.value.some((o) => o.id === unitId)) {
		expirationUnit.value = unitId
		emitValue()
	}
}

function onToggleRenewal(enabled: boolean): void {
	renewalEnabled.value = enabled
	if (enabled && (!renewalAmount.value || renewalAmount.value === '0')) {
		renewalAmount.value = '24'
		renewalUnit.value = 'hours'
	}
	emitValue()
}

function onRenewalAmountChange(val: string | number): void {
	renewalAmount.value = String(val ?? '')
	emitValue()
}

function onRenewalUnitChange(opt: TimeUnitOption | TimeUnit | string | null): void {
	const unitId = (typeof opt === 'object' && opt ? opt.id : opt) as TimeUnit
	if (unitOptions.value.some((o) => o.id === unitId)) {
		renewalUnit.value = unitId
		emitValue()
	}
}
</script>

<style scoped lang="scss">
.expiration-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;

	&__section {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	&__fields {
		display: flex;
		gap: 0.5rem;
		align-items: flex-end;
		margin-inline-start: 0.25rem;

		:deep(.nc-text-field) {
			flex: 1 1 auto;
		}

		:deep(.nc-select) {
			flex: 0 0 140px;
		}
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
