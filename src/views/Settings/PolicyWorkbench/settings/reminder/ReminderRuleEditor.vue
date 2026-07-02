<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="reminder-editor">
		<NcCheckboxRadioSwitch type="switch"
			:model-value="isEnabled"
			@update:modelValue="onToggleEnabled">
			<span>{{ t('libresign', 'Enable automatic reminders') }}</span>
		</NcCheckboxRadioSwitch>

		<div v-if="isEnabled" class="reminder-editor__fields">
			<NcTextField :model-value="String(value.days_before)"
				:label="t('libresign', 'First reminder after (days)')"
				type="number"
				:min="1"
				:step="1"
				@update:modelValue="onDaysBeforeChange" />

			<NcTextField :model-value="String(value.days_between)"
				:label="t('libresign', 'Days between reminders')"
				type="number"
				:min="1"
				:step="1"
				@update:modelValue="onDaysBetweenChange" />

			<NcTextField :model-value="String(value.max)"
				:label="t('libresign', 'Max reminders per signer')"
				type="number"
				:min="1"
				:step="1"
				@update:modelValue="onMaxChange" />

			<NcDateTimePickerNative :model-value="sendTimerDate"
				:label="t('libresign', 'Send time (HH:mm)')"
				type="time"
				@update:modelValue="onSendTimerChange" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { ReminderPolicyConfig } from './model'
import {
	normalizeReminderPolicyConfig,
	REMINDER_POLICY_ENABLED_PRESET,
	serializeReminderPolicyConfig,
} from './model'

defineOptions({
	name: 'ReminderRuleEditor',
})

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: string]
}>()

const value = computed(() => normalizeReminderPolicyConfig(props.modelValue))

const isEnabled = computed(() => {
	return value.value.days_before > 0
		&& value.value.days_between > 0
		&& value.value.max > 0
})

const sendTimerDate = computed(() => {
	if (!value.value.send_timer) {
		return null
	}

	return new Date(`2022-10-10 ${value.value.send_timer}`)
})

function emitValue(partial: Partial<ReminderPolicyConfig>): void {
	const nextValue: ReminderPolicyConfig = {
		...value.value,
		...partial,
	}

	emit('update:modelValue', serializeReminderPolicyConfig(nextValue))
}

function onToggleEnabled(enabled: boolean): void {
	if (!enabled) {
		emitValue({
			days_before: 0,
			days_between: 0,
			max: 0,
			send_timer: '',
		})
		return
	}

	emitValue(REMINDER_POLICY_ENABLED_PRESET)
}

function onDaysBeforeChange(nextValue: string | number): void {
	emitValue({
		days_before: Math.max(1, Number.parseInt(String(nextValue), 10) || REMINDER_POLICY_ENABLED_PRESET.days_before),
	})
}

function onDaysBetweenChange(nextValue: string | number): void {
	emitValue({
		days_between: Math.max(1, Number.parseInt(String(nextValue), 10) || REMINDER_POLICY_ENABLED_PRESET.days_between),
	})
}

function onMaxChange(nextValue: string | number): void {
	emitValue({
		max: Math.max(1, Number.parseInt(String(nextValue), 10) || REMINDER_POLICY_ENABLED_PRESET.max),
	})
}

function onSendTimerChange(nextValue: Date | null): void {
	if (!nextValue) {
		emitValue({ send_timer: REMINDER_POLICY_ENABLED_PRESET.send_timer })
		return
	}

	const hours = nextValue.getHours()
	const minutes = nextValue.getMinutes()
	const hh = hours < 10 ? `0${hours}` : String(hours)
	const mm = minutes < 10 ? `0${minutes}` : String(minutes)

	emitValue({ send_timer: `${hh}:${mm}` })
}
</script>

<style scoped lang="scss">
.reminder-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.reminder-editor__fields {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	margin-left: 0.25rem;
}
</style>
