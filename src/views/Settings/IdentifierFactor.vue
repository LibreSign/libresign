<template>
	<NcSettingsSection :title="title" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useUser"
			@update:checked="saveAccount">
			{{ t('libresign', 'User') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useUser">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredUser"
					@change="saveAccount()">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="allowedInviteUser"
					@change="saveAccount()">
					{{ t('libresign', 'Allow account creation for new users') }}
				</NcActionCheckbox>

				<p>{{ t('libresign', 'Allows sending registration email when the user does not have an account.') }}</p>
			</div>

			<div class="container-select">
				<label for="selectIdentificationDefault">{{ t('libresign', 'Default signature method') }}</label>
				<NcSelect v-model="selectedDefaultIdentification"
					:options="options"
					input-id="selectIdentificationDefault" />
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useEmail"
			@update:checked="saveEmail">
			{{ t('libresign', 'Email') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useEmail">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredEmail"
					@change="saveEmail()">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useTelegram"
			@update:checked="saveTelegram">
			{{ t('libresign', 'Telegram') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useTelegram">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredTelegram"
					@change="saveTelegram()">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useSMS"
			@update:checked="saveSMS">
			{{ t('libresign', 'SMS') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useSMS">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredSMS" @change="saveSMS()">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useSignal"
			@update:checked="saveSignal">
			{{ t('libresign', 'Signal') }}
		</NcCheckboxRadioSwitch>

		<div v-if="useSignal">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredSignal" @change="saveSignal()">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import { loadState } from '@nextcloud/initial-state'
import { getBoolean } from '../../helpers/helperTypes.js'

export default {
	name: 'IdentifierFactor',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcActionCheckbox,
		NcSelect,
	},
	data() {
		const identifyMethod = loadState('libresign', 'identify_methods')
		const account = identifyMethod.find((item) => item.name === 'account')
		const email = identifyMethod.find((item) => item.name === 'email')
		const sms = identifyMethod.find((item) => item.name === 'sms')
		const telegram = identifyMethod.find((item) => item.name === 'telegram')
		const signal = identifyMethod.find((item) => item.name === 'signal')

		return {
			title: t('Identify factors'),
			description: t('Identify factors'),
			selectedDefaultIdentification: account?.signature_method,
			options: account?.allowed_signature_methods,
			useUser: getBoolean(account?.enabled),
			requiredUser: getBoolean(account?.mandatory),
			allowedInviteUser: getBoolean(account?.can_create_account),

			useTelegram: getBoolean(telegram?.enabled),
			requiredTelegram: getBoolean(telegram?.mandatory),

			useSMS: getBoolean(sms?.enabled),
			requiredSMS: getBoolean(sms?.mandatory),

			useEmail: getBoolean(email?.enabled),
			requiredEmail: getBoolean(email?.mandatory),

			useSignal: getBoolean(signal?.enabled),
			requiredSignal: getBoolean(signal?.mandatory),

			optionsSave: [],
		}
	},
	methods: {
		isDefaultValue() {
			if (this.useUser === false) {
				if (!this.useSMS
						&& !this.useSignal
						&& !this.useTelegram
						&& !this.useEmail) {
					this.useUser = true
					return true
				}
			 }
			return false
		},
		saveEmail() {
			if (this.isDefaultValue()) {
				return
			}
			this.optionsSave = [...this.optionsSave.filter(item => item.name !== 'email'), {
				name: 'email',
				enabled: this.useEmail,
				mandatory: this.requiredEmail,
				can_be_used: this.useEmail,
			}]
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},
		saveAccount() {
			if (this.isDefaultValue()) {
				return
			}

			this.optionsSave = [...this.optionsSave.filter(item => item.name !== 'account'), {
				name: 'account',
				enabled: this.useUser,
				mandatory: this.requiredUser,
				can_be_used: this.useUser,
				can_create_account: this.allowedInviteUser,
				signature_method: this.selectedDefaultIdentification,
			}]
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},
		saveSMS() {
			if (this.isDefaultValue()) {
				return
			}

			this.optionsSave = [...this.optionsSave.filter(item => item.name !== 'sms'), {
				name: 'sms',
				enabled: this.useSMS,
				mandatory: this.requiredSMS,
				can_be_used: this.useSMS,
			}]
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},

		saveTelegram() {
			if (this.isDefaultValue()) {
				return
			}

			this.optionsSave = [...this.optionsSave.filter(item => item.name !== 'telegram'), {
				name: 'telegram',
				enabled: this.useTelegram,
				mandatory: this.requiredTelegram,
				can_be_used: this.useTelegram,
			}]
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},

		saveSignal() {
			if (this.isDefaultValue()) {
				return
			}

			this.optionsSave = [...this.optionsSave.filter(item => item.name !== 'signal'), {
				name: 'signal',
				enabled: this.useSignal,
				mandatory: this.requiredSignal,
				can_be_used: this.useSignal,
			}]
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},
	},
}
</script>
<style scoped>
	.container-select {
		display: flex;
		flex-direction: column;
	}

	.container-checkbox {
		list-style: none;

		p {
			padding: 15px;
		}
	}

.identification-documents-content{
	display: flex;
	flex-direction: column;
}
</style>
