<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="expiration-rule-editor">
		<NcCheckboxRadioSwitch
			type="switch"
			:model-value="localEnabled"
			@update:modelValue="onToggleEnabled">
			{{ renewalIntervalToggleLabel }}
		</NcCheckboxRadioSwitch>

		<div v-if="localEnabled" class="expiration-rule-editor__fields">
			<NcTextField
				:model-value="localAmount"
				type="number"
				:min="1"
				:step="1"
				:label="renewalIntervalInputLabel"
				@update:modelValue="onAmountChange" />
			<NcSelect
				:model-value="selectedUnitOption"
				:options="unitOptions"
				:input-label="timeUnitSelectLabel"
				:clearable="false"
				@update:modelValue="onUnitChange" />
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
	DEFAULT_RENEWAL_INTERVAL,
	durationToSeconds,
	normalizeNonNegativeInt,
	secondsToDuration,
	type TimeUnit,
} from './model'

defineOptions({
	name: 'RenewalIntervalRuleEditor',
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

// TRANSLATORS Toggle label for enabling or disabling a renewal interval on signing requests.
const renewalIntervalToggleLabel = t('libresign', 'Renewal interval')
// TRANSLATORS Label for the numeric field that sets renewal interval for a signing request access link.
const renewalIntervalInputLabel = t('libresign', 'Renewal interval of a subscription request. When accessing the link, you will be asked to renew the link.')
// TRANSLATORS Accessible label for selecting time unit in expiration rules.
const timeUnitSelectLabel = t('libresign', 'Time unit')

const unitOptions = computed<TimeUnitOption[]>(() => [
	{ id: 'seconds', label: t('libresign', 'seconds') },
	{ id: 'minutes', label: t('libresign', 'minutes') },
	{ id: 'hours', label: t('libresign', 'hours') },
	{ id: 'days', label: t('libresign', 'days') },
])

const normalized = computed(() => normalizeNonNegativeInt(props.modelValue, DEFAULT_RENEWAL_INTERVAL))

const localEnabled = ref(normalized.value > 0)
const localAmount = ref<string>('')
const localUnit = ref<TimeUnit>('hours')

function syncFromProps(val: number) {
	localEnabled.value = val > 0
	if (val > 0) {
		const dur = secondsToDuration(val)
		localAmount.value = String(dur.amount)
		localUnit.value = dur.unit
	} else {
		localAmount.value = '24'
		localUnit.value = 'hours'
	}
}

// Initial sync without emitting update:modelValue!
syncFromProps(normalized.value)

watch(
	() => props.modelValue,
	(newVal) => {
		const norm = normalizeNonNegativeInt(newVal, DEFAULT_RENEWAL_INTERVAL)
		const currentSec = currentSeconds()
		if (norm !== currentSec) {
			syncFromProps(norm)
		}
	},
)

const selectedUnitOption = computed<TimeUnitOption>(() => {
	return unitOptions.value.find((opt) => opt.id === localUnit.value) ?? unitOptions.value[2]
})

function currentSeconds(): number {
	if (!localEnabled.value) {
		return 0
	}

	const sec = durationToSeconds(localAmount.value, localUnit.value)
	return sec ?? -1
}

function emitValue() {
	emit('update:modelValue', currentSeconds())
}

function onToggleEnabled(enabled: boolean): void {
	localEnabled.value = enabled
	if (enabled && (!localAmount.value || localAmount.value === '0')) {
		localAmount.value = '24'
		localUnit.value = 'hours'
	}
	emitValue()
}

function onAmountChange(val: string | number): void {
	localAmount.value = String(val ?? '')
	emitValue()
}

function onUnitChange(opt: TimeUnitOption | TimeUnit | string | null): void {
	const unitId = (typeof opt === 'object' && opt ? opt.id : opt) as TimeUnit
	if (unitOptions.value.some((o) => o.id === unitId)) {
		localUnit.value = unitId
		emitValue()
	}
}
</script>

<style scoped lang="scss">
.expiration-rule-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

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
}
</style>
