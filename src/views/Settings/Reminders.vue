<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="reminders-content">
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="reminderState"
				:disabled="loading">
				{{ switchText }}
			</NcCheckboxRadioSwitch>
			<NcLoadingIcon v-if="loading"
				class="settings-section__loading-icon"
				:size="20" />
			<div v-if="reminderState">
				<NcTextField :value.sync="reminderDaysBefore"
					:label="t('libresign', 'Days before first reminder')"
					:placeholder="t('libresign', 'Days before first reminder')"
					type="number"
					:min="0"
					:max="maxValue"
					:step="1"
					:helper-text="helperTextDaysBefore"
					:error="reminderDaysBefore > maxValue"
					:spellcheck="false"
					:success="displaySuccessReminderDaysBefore"
					@keydown.enter="save"
					@blur="save" />
				<NcTextField :value.sync="reminderRepeatEvery"
					:label="t('libresign', 'Repeat reminder')"
					:placeholder="t('libresign', 'Repeat reminder')"
					type="number"
					:min="0"
					:max="maxValue"
					:step="1"
					:helper-text="helperTextRepeatEvery"
					:error="reminderRepeatEvery > maxValue"
					:spellcheck="false"
					:success="displaySuccessReminderRepeatEvery"
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
<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import { NcDateTimePickerNative } from '@nextcloud/vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'Reminders',
	components: {
		NcDateTimePickerNative,
		NcSettingsSection,
		NcLoadingIcon,
		NcTextField,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			name: t('libresign', 'Reminders'),
			description: t('libresign', 'Follow up with automatic reminders. Signers will receive reminders until they sign or decline.'),
			// TRANSLATORS The time that will send a sign reminder to a signer
			labelReminderSendTimer: t('libresign', 'Send time (HH:mm)'),
			value: '',
			reminderDaysBefore: 0,
			reminderRepeatEvery: 0,
			reminderSendTimer: null,
			previousReminderDaysBefore: 0,
			previousReminderRepeatEvery: 0,
			previousReminderSendTimer: null,
			maxValue: 30,
			reminderState: false,
			displaySuccessReminderDaysBefore: false,
			displaySuccessReminderRepeatEvery: false,
			displaySuccessReminderSendTimer: false,
			loading: false,
		}
	},
	computed: {
		switchText() {
			// TRANSLATORS Toggle reminders for signers who have not signed yet
			return t('libresign', 'Turn {reminderState} auto reminders', {
				// TRANSLATORS The reminder state to auto reminders, usage example: Turn {reminderState} auto reminders
				reminderState: this.reminderState ? t('libresign', 'off') : t('libresign', 'on'),
			})
		},
		helperTextDaysBefore() {
			if (this.reminderDaysBefore > this.maxValue) {
				return t('libresign', 'The max value is {maxValue}', { maxValue: this.maxValue })
			}
			return ''
		},
		helperTextRepeatEvery() {
			if (this.reminderRepeatEvery > this.maxValue) {
				return t('libresign', 'The max value is {maxValue}', { maxValue: this.maxValue })
			}
			return ''
		},
	},
	watch: {
		reminderState(reminderState) {
			this.reminderState = reminderState
			if (!reminderState) {
				this.reminderDaysBefore = 0
				this.reminderRepeatEvery = 0
				this.reminderSendTimer = null
				this.save()
			}
		},
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			this.loading = true

			const reminderDaysBefore = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/reminder_days_before'))
			this.reminderDaysBefore = parseInt(reminderDaysBefore.data.ocs.data.data) || 0
			this.previousReminderDaysBefore = this.reminderDaysBefore

			const reminderRepeatEvery = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/reminder_repeat_every'))
			this.reminderRepeatEvery = parseInt(reminderRepeatEvery.data.ocs.data.data) || 0
			this.previousReminderRepeatEvery = this.reminderRepeatEvery

			const reminderSendTimer = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/reminder_send_timer'))
			if (reminderSendTimer?.data?.ocs?.data?.data.length > 0) {
				this.reminderSendTimer = new Date('2022-10-10 ' + reminderSendTimer.data.ocs.data.data)
			} else {
				this.reminderSendTimer = new Date('2022-10-10 10:00:00')
			}
			this.previousReminderSendTimer = this.reminderSendTimer

			this.reminderState = this.reminderDaysBefore > 0 || this.reminderRepeatEvery > 0

			this.loading = false
		},
		save: debounce(async function() {
			this.displaySuccessReminderDaysBefore = false
			this.displaySuccessReminderRepeatEvery = false
			this.displaySuccessReminderSendTimer = false

			if (this.reminderDaysBefore !== this.previousReminderDaysBefore && this.reminderDaysBefore >= 0 && this.reminderDaysBefore <= this.maxValue) {
				if (this.reminderDaysBefore > 0) {
					await OCP.AppConfig.setValue('libresign', 'reminder_days_before', parseInt(this.reminderDaysBefore, 10) || 0)
				} else {
					await OCP.AppConfig.deleteKey('libresign', 'reminder_days_before')
				}
				this.previousReminderDaysBefore = this.reminderDaysBefore
				this.displaySuccessReminderDaysBefore = true
				setTimeout(() => { this.displaySuccessReminderDaysBefore = false }, 2000)
			}

			if (this.reminderRepeatEvery !== this.previousReminderRepeatEvery && this.reminderRepeatEvery >= 0 && this.reminderRepeatEvery <= this.maxValue) {
				if (this.reminderRepeatEvery > 0) {
					await OCP.AppConfig.setValue('libresign', 'reminder_repeat_every', parseInt(this.reminderRepeatEvery, 10) || 0)
				} else {
					await OCP.AppConfig.deleteKey('libresign', 'reminder_repeat_every')
				}
				this.previousReminderRepeatEvery = this.reminderRepeatEvery
				this.displaySuccessReminderRepeatEvery = true
				setTimeout(() => { this.displaySuccessReminderRepeatEvery = false }, 2000)
			}

			if (this.reminderSendTimer !== this.previousReminderSendTimer) {
				if (this.reminderSendTimer instanceof Date) {
					await OCP.AppConfig.setValue('libresign', 'reminder_send_timer', this.reminderSendTimer.toISOString().slice(11, 16))
				} else {
					await OCP.AppConfig.deleteKey('libresign', 'reminder_send_timer')
				}
				this.previousReminderSendTimer = this.reminderSendTimer
				this.displaySuccessReminderSendTimer = true
				setTimeout(() => { this.displaySuccessReminderSendTimer = false }, 2000)
			}
		}, 1000),
	},
}
</script>
<style lang="scss" scoped>
.reminders-content{
	display: flex;
	flex-direction: column;

	:deep(input) {
		display: flex;
		max-width: 200px
	}
}
</style>
