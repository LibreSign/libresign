<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Legal information')"
		:description="t('libresign', 'This information will appear on the validation page')">
		<div class="legal-information-content">
			<textarea v-model="legalInformation"
		:placeholder="t('libresign', 'Legal Information')"
				@input="saveLegalInformation" />
		</div>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'LegalInformation',
	components: {
		NcSettingsSection,
	},
	data() {
		return {
			legalInformation: '',
		}
	},
	created() {
		this.getData()
	},
	methods: {
		t,
		async getData() {
			const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/legal_information'))
			this.legalInformation = response.data.ocs.data.data
		},
		saveLegalInformation() {
			OCP.AppConfig.setValue('libresign', 'legal_information', this.legalInformation)
		},
	},
}
</script>
<style scoped>
.legal-information-content{
	display: flex;
	flex-direction: column;
}
textarea {
	width: 50%;
	height: 150px;
}
</style>
