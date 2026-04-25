<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<template v-if="hasContent">
		<NcListItem class="extra"
			compact
			:name="t('libresign', 'Timestamp Authority (TSA)')"
			:aria-expanded="open ? 'true' : 'false'"
			role="button"
			@click="open = !open">
			<template #name>
				<strong>{{ t('libresign', 'Timestamp Authority (TSA)') }}</strong>
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
		<div v-if="open" class="timestamp-wrapper" role="region" :aria-label="t('libresign', 'Timestamp authority details')">
			<div class="extra-chain timestamp-item">
				<dl class="timestamp-details">
					<div v-if="authority" class="timestamp-field">
						<dt>{{ t('libresign', 'Authority:') }}</dt>
						<dd>{{ authority }}</dd>
					</div>
					<div v-if="timestamp?.genTime" class="timestamp-field">
						<dt>{{ t('libresign', 'Generated at:') }}</dt>
						<dd>{{ dateFromSqlAnsi(timestamp.genTime) }}</dd>
					</div>
					<div v-if="policy" class="timestamp-field">
						<dt>{{ t('libresign', 'Policy:') }}</dt>
						<dd>{{ policy }}</dd>
					</div>
					<div v-if="hashAlgorithm" class="timestamp-field">
						<dt>{{ t('libresign', 'Hash algorithm:') }}</dt>
						<dd>{{ hashAlgorithm }}</dd>
					</div>
					<div v-if="serialNumber" class="timestamp-field">
						<dt>{{ t('libresign', 'Serial number:') }}</dt>
						<dd>{{ serialNumber }}</dd>
					</div>
				</dl>
			</div>
		</div>
	</template>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import Moment from '@nextcloud/moment'
import { computed, ref } from 'vue'

import {
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
} from '@mdi/js'

type SignerTimestampData = {
	genTime?: string
	policy?: string
	policyName?: string
	hash?: string
	hashAlgorithm?: string
	serialNumber?: string | number
	authority?: string
	tsaName?: string
	cnHints?: {
		commonName?: string
	}
}

defineOptions({
	name: 'SignerTimestamp',
})

const props = defineProps<{
	timestamp?: SignerTimestampData
}>()

const open = ref(false)

const authority = computed(() =>
	props.timestamp?.cnHints?.commonName
	|| props.timestamp?.authority
	|| props.timestamp?.tsaName
	|| '',
)

const policy = computed(() =>
	props.timestamp?.policyName || props.timestamp?.policy || '',
)

const hashAlgorithm = computed(() =>
	props.timestamp?.hashAlgorithm || props.timestamp?.hash || '',
)

const serialNumber = computed(() => {
	const serial = props.timestamp?.serialNumber
	if (typeof serial === 'number') {
		return String(serial)
	}
	return serial || ''
})

const hasContent = computed(() =>
	!!(authority.value
	|| props.timestamp?.genTime
	|| policy.value
	|| hashAlgorithm.value
	|| serialNumber.value),
)

const toggleAriaLabel = computed(() =>
	open.value
		? t('libresign', 'Collapse timestamp authority details')
		: t('libresign', 'Expand timestamp authority details'),
)

function dateFromSqlAnsi(date?: string | number | null) {
	if (!date) return ''
	return Moment(String(date)).format('LLL')
}

defineExpose({
	open,
	authority,
	policy,
	hashAlgorithm,
	serialNumber,
	hasContent,
	toggleAriaLabel,
	dateFromSqlAnsi,
})
</script>

<style scoped lang="scss">
.timestamp-wrapper {
	padding-left: 44px;
}

.timestamp-item {
	padding: 0;
}

.timestamp-details {
	display: flex;
	flex-direction: column;
	gap: 2px;
	width: 100%;
	margin: 0;
	padding: 4px 0;
	list-style: none;
}

.timestamp-field {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 4px;
	line-height: 1.5;
	word-break: break-word;

	dt {
		font-weight: bold;
		min-width: 120px;
		text-align: right;
		margin: 0;
		padding: 0;
	}

	dd {
		margin: 0;
		padding: 0;
		word-break: break-all;
	}
}

.extra {
	padding-left: 44px;
	background-color: var(--color-background-hover);

	:deep(.list-item-content__name) {
		white-space: normal;
		line-height: 1.4;
	}
}

.extra-chain {
	padding-left: 48px;

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
