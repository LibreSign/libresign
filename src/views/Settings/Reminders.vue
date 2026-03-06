<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Reminders')"
		:description="t('libresign', 'Follow up with automatic reminders. Signers will receive reminders until they sign or decline.')">
		<div class="reminders-content">
			<NcCheckboxRadioSwitch type="switch"
				v-model="reminderState"
				:disabled="loading">
				{{ switchText }}
			</NcCheckboxRadioSwitch>
			<NcLoadingIcon v-if="loading"
				class="settings-section__loading-icon"
				:size="20" />
			<div v-if="reminderState">
			{{ t('libresign', 'Next job execution: {date}', {date: nextRunFormatted}) }}
			<NcTextField v-model="reminderDaysBefore"
				:label="t('libresign', 'First reminder after (days)')"
				:placeholder="t('libresign', 'First reminder after (days)')"
					type="number"
					:min="0"
					:step="1"
					:helper-text="t('libresign', 'The first message is not considered a notification')"
					:spellcheck="false"
					:success="displaySuccessReminderDaysBefore"
					@keydown.enter="save"
					@blur="save" />
				<NcTextField v-model="reminderDaysBetween"
				:label="t('libresign', 'Days between reminders')"
				:placeholder="t('libresign', 'Days between reminders')"
					type="number"
					:min="0"
					:step="1"
					:spellcheck="false"
					:success="displaySuccessReminderDaysBetween"
					@keydown.enter="save"
					@blur="save" />
				<NcTextField v-model="reminderMax"
				:label="t('libresign', 'Max reminders per signer')"
				:placeholder="t('libresign', 'Max reminders per signer')"
					type="number"
					:min="0"
					:step="1"
					:helper-text="t('libresign', 'Zero or empty is no reminder.')"
					:spellcheck="false"
					:success="displaySuccessReminderMax"
					@keydown.enter="save"
					@blur="save" />
				<NcDateTimePickerNative v-model="reminderSendTimer"
					:label="labelReminderSendTimer"
					:placeholder="labelReminderSendTimer"
					type="time"
					class="date-time-picker"
					@input="save" />
			</div>
		</div>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import Moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref, watch } from 'vue'

import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

defineOptions({
	name: 'Reminders',
})

type ReminderResponse = {
	ocs: {
		data: {
			days_before: number | string
			days_between: number | string
			max: number | string
			send_timer: string
			next_run: string | null
		}
	}
}

const value = ref('')
const reminderDaysBefore = ref<number | string>(0)
const previousReminderDaysBefore = ref(0)
const displaySuccessReminderDaysBefore = ref(false)
const reminderDaysBetween = ref<number | string>(0)
const previousReminderDaysBetween = ref(0)
const displaySuccessReminderDaysBetween = ref(false)
const reminderSendTimer = ref<Date | null>(null)
const previousReminderSendTimer = ref<Date | null>(null)
const displaySuccessReminderSendTimer = ref(false)
const reminderMax = ref<number | string>(5)
const previousReminderMax = ref(0)
const displaySuccessReminderMax = ref(false)
const reminderState = ref(false)
const nextRun = ref<string | null>(null)
const loading = ref(false)

const labelReminderSendTimer = computed(() => t('libresign', 'Send time (HH:mm)'))

const switchText = computed(() => {
	return t('libresign', 'Turn {reminderState} auto reminders', {
		reminderState: reminderState.value ? t('libresign', 'off') : t('libresign', 'on'),
	})
})

const nextRunFormatted = computed(() => {
	if (nextRun.value) {
		return dateFromSqlAnsi(nextRun.value)
	}

	return t('libresign', 'Not scheduled')
})

function dateFromSqlAnsi(date: string) {
	return Moment(Date.parse(date)).format('LL LTS')
}

async function getData() {
	loading.value = true

	await axios.get<ReminderResponse>(generateOcsUrl('/apps/libresign/api/v1/admin/reminder'))
		.then(({ data }) => {
			const response = data.ocs.data
			reminderDaysBefore.value = parseInt(String(response.days_before)) || 0
			previousReminderDaysBefore.value = Number(reminderDaysBefore.value)

			reminderDaysBetween.value = parseInt(String(response.days_between)) || 0
			previousReminderDaysBetween.value = Number(reminderDaysBetween.value)

			reminderMax.value = parseInt(String(response.max)) || 0
			previousReminderMax.value = Number(reminderMax.value)

			setSendTimer(response.send_timer)

			reminderState.value = Number(reminderDaysBefore.value) > 0
				|| Number(reminderDaysBetween.value) > 0
				|| Number(reminderMax.value) > 0
			nextRun.value = response.next_run
		})
		.finally(() => {
			loading.value = false
		})
}

function setSendTimer(timer: string) {
	if (timer.length > 0) {
		reminderSendTimer.value = new Date(`2022-10-10 ${timer}`)
	} else {
		reminderSendTimer.value = new Date('2022-10-10 10:00:00')
	}

	previousReminderSendTimer.value = reminderSendTimer.value
}

const save = debounce(async () => {
	displaySuccessReminderDaysBefore.value = false
	displaySuccessReminderDaysBetween.value = false
	displaySuccessReminderSendTimer.value = false
	loading.value = true

	await axios.post<ReminderResponse>(generateOcsUrl('/apps/libresign/api/v1/admin/reminder'), {
		daysBefore: parseInt(String(reminderDaysBefore.value)),
		daysBetween: parseInt(String(reminderDaysBetween.value)),
		max: parseInt(String(reminderMax.value)),
		sendTimer: formatHourMinute(reminderSendTimer.value),
	})
		.then(({ data }) => {
			const response = data.ocs.data

			if (Number(response.days_before) !== previousReminderDaysBefore.value) {
				previousReminderDaysBefore.value = Number(response.days_before)
				displaySuccessReminderDaysBefore.value = true
				setTimeout(() => {
					displaySuccessReminderDaysBefore.value = false
				}, 2000)
			}

			if (Number(response.days_between) !== previousReminderDaysBetween.value) {
				previousReminderDaysBetween.value = Number(response.days_between)
				displaySuccessReminderDaysBetween.value = true
				setTimeout(() => {
					displaySuccessReminderDaysBetween.value = false
				}, 2000)
			}

			if (Number(response.max) !== previousReminderMax.value) {
				previousReminderMax.value = Number(response.max)
				displaySuccessReminderMax.value = true
				setTimeout(() => {
					displaySuccessReminderMax.value = false
				}, 2000)
			}

			if (response.send_timer !== formatHourMinute(previousReminderSendTimer.value)) {
				setSendTimer(response.send_timer)
				displaySuccessReminderSendTimer.value = true
				setTimeout(() => {
					displaySuccessReminderSendTimer.value = false
				}, 2000)
			}

			nextRun.value = response.next_run
		})
		.catch(() => {
			nextRun.value = null
		})
		.finally(() => {
			loading.value = false
		})
}, 1000)

function formatHourMinute(date: Date | null) {
	if (!date) {
		return ''
	}

	const hours = date.getHours()
	const minutes = date.getMinutes()
	const formattedHours = hours < 10 ? `0${hours}` : String(hours)
	const formattedMinutes = minutes < 10 ? `0${minutes}` : String(minutes)

	return `${formattedHours}:${formattedMinutes}`
}

watch(reminderState, (nextReminderState) => {
	if (!nextReminderState) {
		reminderDaysBefore.value = 0
		reminderDaysBetween.value = 0
		reminderMax.value = 0
		reminderSendTimer.value = null
		void save()
	}
})

onMounted(() => {
	void getData()
})

defineExpose({
	value,
	reminderDaysBefore,
	previousReminderDaysBefore,
	displaySuccessReminderDaysBefore,
	reminderDaysBetween,
	previousReminderDaysBetween,
	displaySuccessReminderDaysBetween,
	reminderSendTimer,
	previousReminderSendTimer,
	displaySuccessReminderSendTimer,
	reminderMax,
	previousReminderMax,
	displaySuccessReminderMax,
	reminderState,
	nextRun,
	loading,
	labelReminderSendTimer,
	switchText,
	nextRunFormatted,
	dateFromSqlAnsi,
	getData,
	setSendTimer,
	save,
	formatHourMinute,
})
</script>
<style lang="scss" scoped>
.reminders-content{
	display: flex;
	flex-direction: column;
}
</style>
