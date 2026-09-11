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
					<div v-if="accuracy"
						class="device-reported-location-field">
						<!-- TRANSLATORS Label for the approximate accuracy radius of device-reported coordinates. -->
						<dt>{{ t('libresign', 'Accuracy:') }}</dt>
						<dd>{{ accuracy }}</dd>
					</div>
				</dl>
				<div class="device-reported-location-actions">
					<NcButton variant="tertiary"
						:aria-label="copyCoordinatesAriaLabel"
						@click="copyCoordinates">
						<template #icon>
							<NcIconSvgWrapper :path="copied ? mdiCheck : mdiContentCopy" :size="20" />
						</template>
						{{ copyCoordinatesLabel }}
					</NcButton>
				</div>
				<!-- TRANSLATORS Disclaimer that stored coordinates are not verified proof of physical presence. -->
				<p class="serial-hex">{{ physicalPresenceDisclaimer }}</p>
			</div>
		</div>
	</template>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { computed, ref } from 'vue'

import {
	mdiCheck,
	mdiContentCopy,
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
} from '@mdi/js'

import {
	formatDeviceReportedCoordinates,
	formatDeviceReportedLocationAccuracy,
	type DeviceReportedLocation as DeviceReportedLocationData,
} from '../../helpers/signerGeolocation'

defineOptions({
	name: 'DeviceReportedLocation',
})

const props = defineProps<{
	geolocation?: DeviceReportedLocationData | null
}>()

const open = ref(false)
const copied = ref(false)
let copiedResetTimer: ReturnType<typeof setTimeout> | null = null

// TRANSLATORS Collapsible section title for coordinates reported by the signer's device at signing time.
const deviceReportedLocationLabel = t('libresign', 'Device-reported location')
// TRANSLATORS ARIA label for the expandable region with device-reported location details.
const deviceReportedLocationDetailsAriaLabel = t('libresign', 'Device-reported location details')
// TRANSLATORS Disclaimer that stored coordinates are not verified proof of physical presence.
const physicalPresenceDisclaimer = t('libresign', 'Physical presence not verified')
// TRANSLATORS Button label that copies latitude and longitude to the clipboard without opening an external map.
const copyCoordinatesLabel = t('libresign', 'Copy coordinates')
// TRANSLATORS Accessible label for the action that copies device-reported coordinates to the clipboard.
const copyCoordinatesAriaLabel = t('libresign', 'Copy device-reported coordinates')

const latitude = computed(() => {
	const value = props.geolocation?.latitude
	return typeof value === 'number' && Number.isFinite(value) ? String(value) : null
})

const longitude = computed(() => {
	const value = props.geolocation?.longitude
	return typeof value === 'number' && Number.isFinite(value) ? String(value) : null
})

const accuracy = computed(() => {
	const value = props.geolocation?.accuracy
	if (typeof value !== 'number' || !Number.isFinite(value)) {
		return null
	}
	return formatDeviceReportedLocationAccuracy(value)
})

const coordinatesText = computed(() => formatDeviceReportedCoordinates(props.geolocation))

const hasContent = computed(() => latitude.value !== null && longitude.value !== null)

const toggleAriaLabel = computed(() =>
	open.value
		// TRANSLATORS ARIA label for button action that hides device-reported location details.
		? t('libresign', 'Collapse device-reported location details')
		// TRANSLATORS ARIA label for button action that reveals device-reported location details.
		: t('libresign', 'Expand device-reported location details'),
)

async function copyCoordinates() {
	const text = coordinatesText.value
	if (!text) {
		return
	}

	try {
		await navigator.clipboard.writeText(text)
	} catch {
		prompt('', text)
	}

	copied.value = true
	if (copiedResetTimer) {
		clearTimeout(copiedResetTimer)
	}
	copiedResetTimer = setTimeout(() => {
		copied.value = false
		copiedResetTimer = null
	}, 2000)

	// TRANSLATORS Toast confirming that latitude and longitude were copied to the clipboard.
	showSuccess(t('libresign', 'Coordinates copied'))
}

defineExpose({
	open,
	hasContent,
	latitude,
	longitude,
	accuracy,
	copied,
	copyCoordinates,
	physicalPresenceDisclaimer,
	deviceReportedLocationLabel,
	copyCoordinatesLabel,
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

.device-reported-location-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-block: 4px 0;
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
