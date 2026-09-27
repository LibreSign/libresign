<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<template v-if="hasContent">
		<NcListItem class="extra"
			compact
			role="button"
			:aria-expanded="open ? 'true' : 'false'"
			@click="open = !open">
			<template #name>
				<!-- TRANSLATORS Collapsible section title for approximate location resolved from the signer IP. -->
				<strong>{{ t('libresign', 'IP-based approximate location') }}</strong>
			</template>
			<template #extra-actions>
				<NcButton variant="tertiary"
					:aria-label="toggleAriaLabel"
					@click.stop="open = !open">
					<template #icon>
						<NcIconSvgWrapper v-if="open"
							:path="mdiUnfoldLessHorizontal"
							:size="20" />
						<NcIconSvgWrapper v-else
							:path="mdiUnfoldMoreHorizontal"
							:size="20" />
					</template>
				</NcButton>
			</template>
		</NcListItem>
		<div v-if="open"
			class="ip-based-location-wrapper"
			role="region"
			:aria-label="ipBasedLocationDetailsAriaLabel">
			<div class="extra-chain ip-based-location-item">
				<p class="ip-based-location-status">{{ statusText }}</p>
				<dl class="ip-based-location-details">
					<div v-if="sourceIp" class="ip-based-location-field">
						<!-- TRANSLATORS Label for the signer source IP stored with IP geolocation evidence. -->
						<dt>{{ t('libresign', 'Source IP:') }}</dt>
						<dd>{{ sourceIp }}</dd>
					</div>
					<div v-if="country" class="ip-based-location-field">
						<!-- TRANSLATORS Label for the country from approximate IP geolocation. -->
						<dt>{{ t('libresign', 'Country:') }}</dt>
						<dd>{{ country }}</dd>
					</div>
					<div v-if="region" class="ip-based-location-field">
						<!-- TRANSLATORS Label for the region from approximate IP geolocation. -->
						<dt>{{ t('libresign', 'Region:') }}</dt>
						<dd>{{ region }}</dd>
					</div>
					<div v-if="city" class="ip-based-location-field">
						<!-- TRANSLATORS Label for the city from approximate IP geolocation. -->
						<dt>{{ t('libresign', 'City:') }}</dt>
						<dd>{{ city }}</dd>
					</div>
					<div v-if="coordinates" class="ip-based-location-field">
						<!-- TRANSLATORS Label for latitude and longitude from approximate IP geolocation. -->
						<dt>{{ t('libresign', 'Latitude / longitude:') }}</dt>
						<dd>{{ coordinates }}</dd>
					</div>
					<div v-if="accuracyRadius" class="ip-based-location-field">
						<!-- TRANSLATORS Label for the GeoIP accuracy radius of approximate coordinates. -->
						<dt>{{ t('libresign', 'Accuracy radius:') }}</dt>
						<dd>{{ accuracyRadius }}</dd>
					</div>
				</dl>
				<p v-if="reasonText" class="serial-hex">{{ reasonText }}</p>
				<!-- TRANSLATORS Disclaimer that IP-derived location is approximate and may be affected by VPNs or proxies. -->
				<p class="serial-hex">{{ t('libresign', 'Approximate location. VPNs, proxies and mobile networks can affect this result.') }}</p>
			</div>
		</div>
	</template>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { computed, ref } from 'vue'

import {
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
} from '@mdi/js'

import {
	formatIpAccuracyRadius,
	formatIpCoordinates,
	hasIpGeolocationEvidence,
	ipGeolocationReasonLabel,
	ipGeolocationStatusLabel,
	resolveIpGeolocationReason,
	resolveIpGeolocationStatus,
	type SignerIpGeolocationEvidence,
} from '../../helpers/signerIpGeolocation'

defineOptions({
	name: 'IpBasedApproximateLocation',
})

const props = defineProps<{
	geolocation?: SignerIpGeolocationEvidence | null
}>()

const open = ref(false)

// TRANSLATORS ARIA label for the expandable region with IP-based location details.
const ipBasedLocationDetailsAriaLabel = t('libresign', 'IP-based approximate location details')

const hasContent = computed(() => hasIpGeolocationEvidence(props.geolocation))
const status = computed(() => resolveIpGeolocationStatus(props.geolocation?.status))
const statusText = computed(() => status.value ? ipGeolocationStatusLabel(status.value) : '')
const reason = computed(() => resolveIpGeolocationReason(props.geolocation?.reason))
const reasonText = computed(() => reason.value ? ipGeolocationReasonLabel(reason.value) : '')
const sourceIp = computed(() => {
	const value = props.geolocation?.sourceIp
	return typeof value === 'string' && value !== '' ? value : null
})
const country = computed(() => {
	const value = props.geolocation?.country
	return typeof value === 'string' && value !== '' ? value : null
})
const region = computed(() => {
	const value = props.geolocation?.region
	return typeof value === 'string' && value !== '' ? value : null
})
const city = computed(() => {
	const value = props.geolocation?.city
	return typeof value === 'string' && value !== '' ? value : null
})
const coordinates = computed(() => formatIpCoordinates(props.geolocation))
const accuracyRadius = computed(() => {
	const value = props.geolocation?.accuracyRadius
	if (typeof value !== 'number' || !Number.isFinite(value)) {
		return null
	}
	return formatIpAccuracyRadius(value)
})

const toggleAriaLabel = computed(() =>
	open.value
		// TRANSLATORS ARIA label for button action that hides IP-based location details.
		? t('libresign', 'Collapse IP-based approximate location details')
		// TRANSLATORS ARIA label for button action that reveals IP-based location details.
		: t('libresign', 'Expand IP-based approximate location details'),
)

defineExpose({
	open,
	hasContent,
	statusText,
	reasonText,
	sourceIp,
	country,
	region,
	city,
	coordinates,
	accuracyRadius,
	toggleAriaLabel,
})
</script>

<style scoped lang="scss">
.ip-based-location-wrapper {
	padding-inline-start: 44px;
}

.ip-based-location-item {
	padding: 0;
}

.ip-based-location-status {
	margin: 4px 0;
}

.ip-based-location-details {
	display: flex;
	flex-direction: column;
	gap: 2px;
	width: 100%;
	margin: 0;
	padding: 4px 0;
	list-style: none;
}

.ip-based-location-field {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 4px;
	line-height: 1.5;
	word-break: break-word;

	dt {
		font-weight: bold;
		min-width: 120px;
		text-align: end;
		margin: 0;
		padding: 0;
	}

	dd {
		margin: 0;
		padding: 0;
		word-break: break-all;
	}
}

.serial-hex {
	display: block;
	margin-block: 4px 0;
	margin-inline: 0;
	opacity: 0.7;
}

.extra {
	padding-inline-start: 44px;
	background-color: var(--color-background-hover);

	:deep(.list-item-content__name) {
		white-space: normal;
		line-height: 1.4;
	}
}

.extra-chain {
	padding-inline-start: 48px;

	:deep(.list-item) {
		--list-item-height: auto;
	}

	:deep(.list-item-content__name) {
		white-space: normal !important;
		overflow: visible !important;
		text-overflow: clip !important;
	}
}
</style>
