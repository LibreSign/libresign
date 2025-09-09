<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="enabled"
			@update:checked="saveTsaUrl">
			{{ t('libresign', 'Use timestamp server') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'TSA',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},

	data: () => ({
		name: t('libresign', 'Timestamp Authority (TSA)'),
		description: t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.'),
		enabled: false,
		tsa_url: '',
		loading: false,
	}),

	mounted() {
		this.getData()
	},
	methods: {
		async getData() {
			this.loading = true
			const TSA_URL = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/tsa_url'))
			this.tsa_url = TSA_URL?.data?.ocs?.data?.data ?? ''
			this.enabled = this.tsa_url.length > 0
			this.loading = false
		},

		async saveTsaUrl() {
			await confirmPassword()

			if (this.enabled) {
				const TSA_URL = this.enabled ? 'https://freetsa.org/tsr' : ''
				OCP.AppConfig.setValue('libresign', 'tsa_url', TSA_URL)
			} else {
				OCP.AppConfig.deleteKey('libresign', 'tsa_url')
			}
		},
	},

}
</script>
