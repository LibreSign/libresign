
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="row">
			<NcTextArea v-model="endpoint"
						:label="t('libresign', 'DPO Endpoint')"
						:placeholder="t('libresign', 'https://secure.3gdirectpay.com/API/v6/')"
						@input="saveAppConfigValue('dpo_endpoint', endpoint)" />
		</div>

		<div class="row">
			<NcTextArea v-model="companyToken"
						:label="t('libresign', 'Company Token')"
						type="text"
						:placeholder="t('libresign', 'Your DPO Company Token')"
						@input="saveAppConfigValue('dpo_company_token', companyToken)" />
		</div>

		<div class="row">
			<NcTextArea v-model="serviceId"
						:label="t('libresign', 'Service ID')"
						:placeholder="t('libresign', 'Your Service ID')"
						@input="saveAppConfigValue('dpo_service_id', serviceId)" />
		</div>

		<div class="row">
			<NcTextArea v-model="paymentUrl"
						:label="t('libresign', 'Payment URL')"
						:placeholder="t('libresign', 'https://secure.3gdirectpay.com/payv2.php')"
						@input="saveAppConfigValue('dpo_payment_url', paymentUrl)" />
		</div>
	</NcSettingsSection>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

export default {
	name: 'DpoPaymentConfig',
	components: {
		NcSettingsSection,
		NcTextArea,
	},
	data() {
		return {
			name: t('libresign', 'DPO Configuration'),
			description: t('libresign', 'Configure Direct Pay Online (DPO) payment settings.'),

			endpoint: loadState('libresign', 'dpo_endpoint', ''),
			companyToken: loadState('libresign', 'dpo_company_token', ''),
			serviceId: loadState('libresign', 'dpo_service_id', ''),
			paymentUrl: loadState('libresign', 'dpo_payment_url', ''),
		}
	},
	methods: {
		t,
		saveAppConfigValue(key, value) {
			OCP.AppConfig.setValue('libresign', key, value)
		},
	},
}
</script>
