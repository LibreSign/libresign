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
				{{ t('libresign', 'Next job execution: {date}', {date: nextRunFormatted}) }}
				<NcTextField :value.sync="reminderDaysBefore"
					:label="t('libresign', 'First reminder after (days)')"
					:placeholder="t('libresign', 'First reminder after (days)')"
					type="number"
					:min="0"
					:step="1"
					:helper-text="helperTextDaysBefore"
					:spellcheck="false"
					:success="displaySuccessReminderDaysBefore"
					@keydown.enter="save"
					@blur="save" />
				<NcTextField :value.sync="reminderDaysBetween"
					:label="t('libresign', 'Days between reminders')"
					:placeholder="t('libresign', 'Days between reminders')"
					type="number"
					:min="0"
					:step="1"
					:spellcheck="false"
					:success="displaySuccessReminderDaysBetween"
					@keydown.enter="save"
					@blur="save" />
				<NcTextField :value.sync="reminderMax"
					:label="t('libresign', 'Max reminders per signer')"
					:placeholder="t('libresign', 'Max reminders per signer')"
					type="number"
					:min="0"
					:step="1"
					:helper-text="helperTextMax"
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
<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
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
			// TRANSLATORS The time that a sign reminder will be sent to a signer
			labelReminderSendTimer: t('libresign', 'Send time (HH:mm)'),
			helperTextDaysBefore: t('libresign', 'The first message is not considered a notification'),
			helperTextMax: t('libresign', 'Zero or empty is no reminder.'),
			value: '',
			reminderDaysBefore: 0,
			previousReminderDaysBefore: 0,
			displaySuccessReminderDaysBefore: false,
			reminderDaysBetween: 0,
			previousReminderDaysBetween: 0,
			displaySuccessReminderDaysBetween: false,
			reminderSendTimer: null,
			previousReminderSendTimer: null,
			displaySuccessReminderSendTimer: false,
			reminderMax: 5,
			previousReminderMax: 0,
			displaySuccessReminderMax: false,
			reminderState: false,
			nextRun: null,
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
		nextRunFormatted() {
			if (this.nextRun) {
				return this.dateFromSqlAnsi(this.nextRun)
			}
			// TRANSLATORS No next reminder job to signers is scheduled
			return t('libresign', 'Not scheduled')
		},
	},
	watch: {
		reminderState(reminderState) {
			this.reminderState = reminderState
			if (!reminderState) {
				this.reminderDaysBefore = 0
				this.reminderDaysBetween = 0
				this.reminderMax = 0
				this.reminderSendTimer = null
				this.save()
			}
		},
	},
	created() {
		this.getData()
	},
	methods: {
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
		async getData() {
			this.loading = true

			await axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/reminder'))
				.then(({ data }) => {
					const response = data.ocs.data
					this.reminderDaysBefore = parseInt(response.days_before) || 0
					this.previousReminderDaysBefore = this.reminderDaysBefore

					this.reminderDaysBetween = parseInt(response.days_between) || 0
					this.previousReminderDaysBetween = this.reminderDaysBetween

					this.reminderMax = parseInt(response.max) || 0
					this.previousReminderMax = this.reminderMax

					this.setSendTimer(response.send_timer)

					this.reminderState = this.reminderDaysBefore > 0
						|| this.reminderDaysBetween > 0
						|| this.max > 0
					this.nextRun = response.next_run
				})
				.finally(() => {
					this.loading = false
				})
		},
		setSendTimer(timer) {
			if (timer.length > 0) {
				this.reminderSendTimer = new Date('2022-10-10 ' + timer)
			} else {
				this.reminderSendTimer = new Date('2022-10-10 10:00:00')
			}
			this.previousReminderSendTimer = this.reminderSendTimer
		},
		save: debounce(async function() {
			this.displaySuccessReminderDaysBefore = false
			this.displaySuccessReminderDaysBetween = false
			this.displaySuccessReminderSendTimer = false
			this.loading = true

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/reminder'), {
				daysBefore: parseInt(this.reminderDaysBefore),
				daysBetween: parseInt(this.reminderDaysBetween),
				max: parseInt(this.reminderMax),
				sendTimer: this.formatHourMinute(this.reminderSendTimer),
			})
				.then(({ data }) => {
					const response = data.ocs.data
					if (response.days_before !== this.previousReminderDaysBefore) {
						this.previousReminderDaysBefore = response.days_before
						this.displaySuccessReminderDaysBefore = true
						setTimeout(() => { this.displaySuccessReminderDaysBefore = false }, 2000)
					}
					if (response.days_between !== this.previousReminderDaysBetween) {
						this.previousReminderDaysBetween = response.days_between
						this.displaySuccessReminderDaysBetween = true
						setTimeout(() => { this.displaySuccessReminderDaysBetween = false }, 2000)
					}
					if (response.days_between !== this.previousReminderMax) {
						this.previousReminderMax = response.days_between
						this.displaySuccessReminderMax = true
						setTimeout(() => { this.displaySuccessReminderMax = false }, 2000)
					}
					if (response.send_timer !== this.formatHourMinute(this.previousReminderSendTimer)) {
						this.setSendTimer(response.send_timer)
						this.displaySuccessReminderSendTimer = true
						setTimeout(() => { this.displaySuccessReminderSendTimer = false }, 2000)
					}
					this.nextRun = response.next_run
				})
				.catch(() => {
					this.nextRun = null
				})
				.finally(() => {
					this.loading = false
				})
		}, 1000),
		formatHourMinute(date) {
			if (!date) {
				return ''
			}
			const hours = date.getHours()
			const minutes = date.getMinutes()

			const formattedHours = hours < 10 ? '0' + hours : hours
			const formattedMinutes = minutes < 10 ? '0' + minutes : minutes

			return `${formattedHours}:${formattedMinutes}`
		},
	},
}
</script>
<style lang="scss" scoped>
.reminders-content{
	display: flex;
	flex-direction: column;
}
</style>
