<template>
	<NcSettingsSection :name="name" :description="description">
		<div v-if="emailIdentifyMethodEnabled">
			<p>
				<NcCheckboxRadioSwitch type="switch"
					:checked.sync="smsOtpEnabled"
					@update:checked="toggleSetting('sms_otp_enabled', smsOtpEnabled)">
					{{ t('libresign', 'Enable SMS OTP') }}
				</NcCheckboxRadioSwitch>
			</p>

			<div class="row">
				<NcTextArea v-model="tiaraApiKey"
					:label="t('libresign', 'Tiara API Key Username')"
					:placeholder="t('libresign', 'Your Tiara API Key')"
					@input="saveAppConfigValue('tiara_api_key', tiaraApiKey)" />
			</div>

			<div class="row">
				<NcTextArea v-model="tiaraSenderId"
					:label="t('libresign', 'Tiara Sender Id')"
					:placeholder="t('libresign', 'Your Tiara Sender ID')"
					type="password"
					@input="saveAppConfigValue('tiara_sender_id', tiaraSenderId)" />
			</div>
		</div>
		<div v-else>
			<p>{{ t('libresign', 'SMS OTP settings are only available when the Email identification method is enabled.') }}</p>
		</div>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

export default {
	name: 'SmsToken',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcTextArea,
	},
	data() {
		const identifyMethods = loadState('libresign', 'identify_methods', []);
		const emailIdentifyMethod = identifyMethods.find(method => method.name === 'email');

		return {
			name: t('libresign', 'SMS Configuration'),
			description: t('libresign', 'Configure Tiara SMS API credentials for SMS authentication.'),
			smsOtpEnabled: false,
			tiaraApiKey: loadState('libresign', 'tiara_api_key', ''),
			tiaraSenderId: loadState('libresign', 'tiara_sender_id', ''),
		};
	},
	created() {
		this.getData()
	},
	methods: {
		t,
		async getData() {
			this.smsOtpEnabled()
		},
		async smsOtpEnabled() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/sms_otp_enabled'),
			)
			const value = response?.data?.ocs?.data.data
			this.smsOtpEnabled = ['true', true, '1', 1].includes(value)
		},
		saveAppConfigValue(key, value) {
			OCP.AppConfig.setValue('libresign', key, value)
		},
		// Method from Validation.vue -> SmsToken.vue
		async toggleSetting(setting, value) {
			OCP.AppConfig.setValue('libresign', setting, value ? true : false)
		},
	},
};
</script>

<style scoped>
/* Add any specific styling for this component here */
</style>
