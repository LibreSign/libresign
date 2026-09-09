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
				<!-- TRANSLATORS Collapsible section title for coordinates reported by the signer's device at signing time. -->
				<strong>{{ deviceReportedLocationLabel }}</strong>
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
			class="device-reported-location-wrapper"
			role="region"
			:aria-label="deviceReportedLocationDetailsAriaLabel">
			<div class="extra-chain device-reported-location-item">
				<dl class="device-reported-location-details">
					<div class="device-reported-location-field">
						<!-- TRANSLATORS Label for the latitude coordinate reported by the signer's device. -->
						<dt>{{ t('libresign', 'Latitude:') }}</dt>
						<dd>{{ latitude }}</dd>
					</div>
					<div class="device-reported-location-field">
						<!-- TRANSLATORS Label for the longitude coordinate reported by the signer's device. -->
						<dt>{{ t('libresign', 'Longitude:') }}</dt>
						<dd>{{ longitude }}</dd>
					</div>
				</dl>
				<!-- TRANSLATORS Disclaimer that stored coordinates are not verified proof of physical presence. -->
				<p class="serial-hex">{{ physicalPresenceDisclaimer }}</p>
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

import type { DeviceReportedLocation as DeviceReportedLocationData } from '../../helpers/signerGeolocation'

defineOptions({
	name: 'DeviceReportedLocation',
})

const props = defineProps<{
	geolocation?: DeviceReportedLocationData | null
}>()

const open = ref(false)

// TRANSLATORS Collapsible section title for coordinates reported by the signer's device at signing time.
const deviceReportedLocationLabel = t('libresign', 'Device-reported location')
// TRANSLATORS ARIA label for the expandable region with device-reported location details.
const deviceReportedLocationDetailsAriaLabel = t('libresign', 'Device-reported location details')
// TRANSLATORS Disclaimer that stored coordinates are not verified proof of physical presence.
const physicalPresenceDisclaimer = t('libresign', 'Physical presence not verified')

const latitude = computed(() => {
	const value = props.geolocation?.latitude
	return typeof value === 'number' && Number.isFinite(value) ? String(value) : null
})

const longitude = computed(() => {
	const value = props.geolocation?.longitude
	return typeof value === 'number' && Number.isFinite(value) ? String(value) : null
})

const hasContent = computed(() => latitude.value !== null && longitude.value !== null)

const toggleAriaLabel = computed(() =>
	open.value
		// TRANSLATORS ARIA label for button action that hides device-reported location details.
		? t('libresign', 'Collapse device-reported location details')
		// TRANSLATORS ARIA label for button action that reveals device-reported location details.
		: t('libresign', 'Expand device-reported location details'),
)

defineExpose({
	open,
	hasContent,
	latitude,
	longitude,
	physicalPresenceDisclaimer,
	deviceReportedLocationLabel,
	toggleAriaLabel,
})
</script>

<style scoped lang="scss">
.device-reported-location-wrapper {
	padding-inline-start: 44px;
}

.device-reported-location-item {
	padding: 0;
}

.device-reported-location-details {
	display: flex;
	flex-direction: column;
	gap: 2px;
	width: 100%;
	margin: 0;
	padding: 4px 0;
	list-style: none;
}

.device-reported-location-field {
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
