<template>
	<NcSettingsSection :name="name">
		<p>
			{{ t('libresign', 'Rules for controlling the expiration of a request to sign a file.') }}
		</p>
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="enableMaximumValidity"
			@update:checked="saveMaximumValidity">
			{{ t('libresign', 'Maximum validity') }}
		</NcCheckboxRadioSwitch>
		<fieldset v-show="enableMaximumValidity" id="settings-sharing-remote-api-expiration" class="sharing__sub-section">
			{{ t('libresign', 'Maximum validity in seconds of a request to sign.') }}
			<NcTextField type="number"
				class="sharing__input"
				:label="t('libresign', 'Maximum validity')"
				:placeholder="t('libresign', 'Maximum validity')"
				:value.sync="maximumValidity"
				@update:value="saveMaximumValidity" />
		</fieldset>
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="enableRenewalInterval"
			@update:checked="saveRenewalInterval">
			{{ t('libresign', 'Renewal interval') }}
		</NcCheckboxRadioSwitch>
		<fieldset v-show="enableRenewalInterval" id="settings-sharing-remote-api-expiration" class="sharing__sub-section">
			{{ t('libresign', 'Renewal interval in seconds of a subscription request. When accessing the link, you will be asked to renew the link.') }}
			<NcTextField type="number"
				class="sharing__input"
				:label="t('libresign', 'Renewal interval')"
				:placeholder="t('libresign', 'Renewal interval')"
				:value.sync="renewalInterval"
				@update:value="saveRenewalInterval" />
		</fieldset>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'ExpirationRules',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcTextField,
	},
	data() {
		return {
			name: t('libresign', 'Expiration rules'),
			paternValidadeUrl: 'https://validador.librecode.coop/',
			enableMaximumValidity: false,
			maximumValidity: '0',
			enableRenewalInterval: false,
			renewalInterval: '0',
			url: null,
		}
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			this.getMaximumValidity()
			this.getRenewalInterval()
		},
		async getMaximumValidity() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/maximum_validity'),
			)
			this.maximumValidity = Number(response.data.ocs.data.data).toString()
			this.enableMaximumValidity = Number(this.maximumValidity) > 0
		},
		async saveMaximumValidity() {
			if (!this.enableMaximumValidity) {
				this.maximumValidity = '0'
			}
			OCP.AppConfig.setValue('libresign', 'maximum_validity', Number(this.maximumValidity))
		},
		async getRenewalInterval() {
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/renewal_interval'),
			)
			this.renewalInterval = Number(response.data.ocs.data.data).toString()
			this.enableRenewalInterval = Number(this.renewalInterval) > 0
		},
		async saveRenewalInterval() {
			if (!this.enableRenewalInterval) {
				this.renewalInterval = '0'
			}
			OCP.AppConfig.setValue('libresign', 'renewal_interval', Number(this.renewalInterval))
		},
	},
}
</script>
<style scoped>

input{
	width: 100%;
}

</style>
