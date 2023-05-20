<template>
	<NcSettingsSection :title="title" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useUser">
			{{ t('libresign', 'User') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useUser">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="allowedInviteUser">
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
			:checked.sync="useTelegram">
			{{ t('libresign', 'Telegram') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useTelegram">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredTelegram">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useSMS">
			{{ t('libresign', 'SMS') }}
		</NcCheckboxRadioSwitch>
		<div v-if="useSMS">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredSMS">
					{{ t('libresign', 'Make this method required') }}
				</NcActionCheckbox>
			</div>
		</div>
		<hr>

		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="useSignal">
			{{ t('libresign', 'Signal') }}
		</NcCheckboxRadioSwitch>

		<div v-if="useSignal">
			<div class="container-checkbox">
				<NcActionCheckbox :checked.sync="requiredSignal">
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

export default {
	name: 'IdentifierFactor',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcActionCheckbox,
		NcSelect,
	},
	data() {
		return {
			title: t('Identify factors'),
			selectedDefaultIdentification: '',
			options: [
			],
			allowedInviteUser: false,
			useUser: true,
			useTelegram: false,
			botTelegramName: '',
			botTelegramApi: '',
			signalApiToken: '',
			requiredTelegram: false,
			requiredSMS: false,
			requiredSignal: false,
			identificationSignal: false,
		}
	},
	methods: {
		changeAllowInviteUser(allowedInviteUser) {
			this.allowedInviteUser = allowedInviteUser
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
