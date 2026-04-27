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
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import { usePoliciesStore } from '../../store/policies'

defineOptions({
	name: 'CollectMetadata',
})

const collectMetadataEnabled = ref(false)
const policiesStore = usePoliciesStore()

function normalizeBoolean(value: unknown): boolean {
	return ['true', true, '1', 1].includes(value as never)
}

async function getData() {
	await policiesStore.fetchEffectivePolicies()
	const value = policiesStore.getEffectiveValue('collect_metadata')
	collectMetadataEnabled.value = normalizeBoolean(value)
}

async function saveCollectMetadata() {
	const saved = await policiesStore.saveSystemPolicy('collect_metadata', collectMetadataEnabled.value, false)
	if (saved) {
		emit('collect-metadata:changed', undefined)
	}
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
