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
<script setup lang="ts">
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

type CollectMetadataResponse = {
	data?: {
		ocs?: {
			data?: {
				data?: unknown
			}
		}
	}
}

type OcpCallbacks = {
	success?: () => void
}

type OcpGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: string, callbacks?: OcpCallbacks) => void
	}
}

defineOptions({
	name: 'CollectMetadata',
})

const collectMetadataEnabled = ref(false)

async function getData() {
	const responseCollectMetadata = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/collect_metadata')) as CollectMetadataResponse
	const value = responseCollectMetadata?.data?.ocs?.data?.data
	collectMetadataEnabled.value = ['true', true, '1', 1].includes(value as never)
}

function saveCollectMetadata() {
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue('libresign', 'collect_metadata', collectMetadataEnabled.value ? '1' : '0', {
		success: () => {
			emit('collect-metadata:changed', undefined)
		},
	})
}

onMounted(() => {
	void getData()
})

defineExpose({
	t,
	collectMetadataEnabled,
	getData,
	saveCollectMetadata,
})
</script>
<style scoped>
.collect-metadata-content{
	display: flex;
	flex-direction: column;
}
</style>
