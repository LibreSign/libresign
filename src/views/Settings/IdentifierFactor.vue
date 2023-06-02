<template>
	<NcSettingsSection :title="title" :description="description">
		<NcCheckboxRadioSwitch
			type="switch"
			@update:checked="saveAccount"
			:checked.sync="useUser">
			{{ t('libresign', 'User') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useUser">

			<div class="container-checkbox">
				<NcActionCheckbox 
					@change="saveAccount()"
					:checked.sync="requiredUser">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
			<div class="container-checkbox">
				<NcActionCheckbox 
				@change="saveAccount()"
				:checked.sync="allowedInviteUser">
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

		<NcCheckboxRadioSwitch
			type="switch"
			@update:checked="saveEmail"
			:checked.sync="useEmail">
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
			@update:checked="saveTelegram"
			:checked.sync="useTelegram">
			{{ t('libresign', 'Telegram') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useTelegram">
			<div class="container-checkbox">
				<NcActionCheckbox 
				@change="saveTelegram()"
				:checked.sync="requiredTelegram">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			@update:checked="saveSMS"
			:checked.sync="useSMS">
			{{ t('libresign', 'SMS') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useSMS">
			<div class="container-checkbox">
				<NcActionCheckbox @change="saveSMS()" :checked.sync="requiredSMS">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch
		@update:checked="saveSignal"
		 type="switch"
			:checked.sync="useSignal">
			{{ t('libresign', 'Signal') }}
		</NcCheckboxRadioSwitch>

		<div v-if="useSignal">
			<div class="container-checkbox">
				<NcActionCheckbox  @change="saveSignal()" :checked.sync="requiredSignal">
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
		const account = identifyMethod.find((item) =>  item.name === 'account');
		const email = identifyMethod.find((item) =>  item.name === 'email');
		const sms = identifyMethod.find((item) =>  item.name === 'sms');
		const telegram = identifyMethod.find((item) =>  item.name === 'telegram');
		const signal = identifyMethod.find((item) =>  item.name === 'signal');

		return {
			title: t('Identify factors'),
			description: t('Identify factors'),
			selectedDefaultIdentification: account?.signature_method,
			options: account?.allowed_signature_methods,
			useUser: account?.enabled,
			requiredUser: account?.mandatory,			
			allowedInviteUser: account?.can_create_account,

			useTelegram: telegram?.enabled,
			requiredTelegram: telegram?.mandatory,
			
			useSMS: sms?.enabled,
			requiredSMS: sms?.mandatory,

			useEmail: email?.enabled, 
			requiredEmail: email?.mandatory,

			useSignal: signal?.enabled,
			requiredSignal: signal?.mandatory,

			optionsSave: [],
		}
	},
	methods: {
		saveEmail() {
			// TODO: verify useEmail is false checked
			this.optionsSave = [...this.optionsSave.filter(item => item.name !== "email"), {
				"name":"email",
				"enabled": !this.useEmail,
				"mandatory": this.requiredEmail,
				"can_be_used": !this.useEmail,
			}];
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},
		saveAccount() {
			// TODO: verify useAccount is false checked
			this.optionsSave = [...this.optionsSave.filter(item => item.name !== "account"), {
				"name":"account",
				"enabled": !this.useAccount,
				"mandatory": this.requiredUser,
				"can_be_used": !this.useAccount,
				"can_create_account": this.allowedInviteUser,
				"signature_method": this.selectedDefaultIdentification,
			}];
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},
		saveSMS() {
			// TODO: verify useSMS is false checked
			this.optionsSave = [...this.optionsSave.filter(item => item.name !== "sms"), {
				"name":"sms",
				"enabled": !this.useSMS,
				"mandatory": this.requiredSMS,
				"can_be_used": !this.useSMS,
			}];
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},
		
		saveTelegram() {
			// TODO: verify useTelegram is false checked
			this.optionsSave = [...this.optionsSave.filter(item => item.name !== "telegram"), {
				"name":"telegram",
				"enabled": !this.useTelegram,
				"mandatory": this.requiredTelegram,
				"can_be_used": !this.useTelegram,
			}];
			OCP.AppConfig.setValue('libresign', 'identify_methods', JSON.stringify(this.optionsSave))
		},


		saveSignal() {
			// TODO: verify useSignal is false checked
			this.optionsSave = [...this.optionsSave.filter(item => item.name !== "signal"), {
				"name":"signal",
				"enabled": !this.useSignal,
				"mandatory": this.requiredSignal,
				"can_be_used": !this.useSignal,
			}];
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
