<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Collect signers\' metadata')"
		:description="t('libresign', 'Enabling this feature, every time a document is signed, LibreSign will store the IP address and user agent of the signer.')">
		<NcCheckboxRadioSwitch type="switch"
			v-model="collectMetadataEnabled"
			@update:model-value="saveCollectMetadata()">
			{{ t('libresign', 'Collect signers\'metadata when signing a document') }}
		</NcCheckboxRadioSwitch>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'CollectMetadata',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			collectMetadataEnabled: false,
		}
	},
	created() {
		this.getData()
	},
	methods: {
		t,
		async getData() {
			const responseCollectMetadata = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/collect_metadata'))
			const value = responseCollectMetadata?.data?.ocs?.data?.data
			this.collectMetadataEnabled = ['true', true, '1', 1].includes(value)
		},
		saveCollectMetadata() {
			OCP.AppConfig.setValue('libresign', 'collect_metadata', this.collectMetadataEnabled ? '1' : '0', {
				success: () => {
					emit('collect-metadata:changed')
				},
			})
		},
	},
}
</script>
<style scoped>
.collect-metadata-content{
	display: flex;
	flex-direction: column;
}
</style>
