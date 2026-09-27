<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="hasEvidence" class="signer-geolocation-evidence">
		<NcListItem class="extra" compact>
			<template #name>
				<!-- TRANSLATORS Section title grouping stored device and IP geolocation evidence for one signer. -->
				<strong>{{ signerGeolocationLabel }}</strong>
			</template>
		</NcListItem>
		<DeviceReportedLocation :geolocation="device" />
		<IpBasedApproximateLocation :geolocation="ip" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { computed } from 'vue'

import DeviceReportedLocation from './DeviceReportedLocation.vue'
import IpBasedApproximateLocation from './IpBasedApproximateLocation.vue'
import { formatDeviceReportedCoordinates, type DeviceReportedLocation as DeviceReportedLocationData } from '../../helpers/signerGeolocation'
import { hasIpGeolocationEvidence, type SignerIpGeolocationEvidence } from '../../helpers/signerIpGeolocation'

defineOptions({
	name: 'SignerGeolocationEvidence',
})

const props = defineProps<{
	device?: DeviceReportedLocationData | null
	ip?: SignerIpGeolocationEvidence | null
}>()

// TRANSLATORS Section title grouping stored device and IP geolocation evidence for one signer.
const signerGeolocationLabel = t('libresign', 'Signer geolocation')

const hasDevice = computed(() => formatDeviceReportedCoordinates(props.device) !== null)
const hasIp = computed(() => hasIpGeolocationEvidence(props.ip))
const hasEvidence = computed(() => hasDevice.value || hasIp.value)

defineExpose({
	hasEvidence,
	hasDevice,
	hasIp,
	signerGeolocationLabel,
})
</script>
