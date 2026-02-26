<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcListItem
			class="extra"
			compact
			role="button"
			@click="chainOpen = !chainOpen">
			<template #name>
				<strong>{{ t('libresign', 'Certificate chain') }}</strong>
			</template>
			<template #extra-actions>
				<NcButton variant="tertiary"
					@click.stop="chainOpen = !chainOpen">
					<template #icon>
						<NcIconSvgWrapper v-if="chainOpen"
							:path="mdiUnfoldLessHorizontal" />
						<NcIconSvgWrapper v-else
							:path="mdiUnfoldMoreHorizontal" />
					</template>
				</NcButton>
			</template>
		</NcListItem>

		<div v-if="chainOpen" class="chain-wrapper" role="region" :aria-label="t('libresign', 'Certificate chain details')">
			<NcListItem v-for="(cert, certIndex) in chain"
				:key="certIndex"
				class="extra-chain certificate-item"
				compact
				:name="certIndex === 0 ? t('libresign', 'Signer:') : t('libresign', 'Issuer:')">
				<template #name>
					<div class="cert-details">
						<div>
							<strong>{{ certIndex === 0 ? t('libresign', 'Signer:') : t('libresign', 'Issuer:') }}</strong>
							{{ cert.subject?.CN || cert.name || cert.displayName }}
						</div>
						<div v-if="cert.issuer?.CN" class="cert-issuer">
							<strong>{{ t('libresign', 'Issued by:') }}</strong>
							{{ cert.issuer.CN }}
						</div>
						<div v-if="cert.serialNumber">
							<strong>{{ t('libresign', 'Serial Number:') }}</strong>
							{{ cert.serialNumber }}
							<span v-if="cert.serialNumberHex" class="serial-hex">
								(hex: {{ cert.serialNumberHex }})
							</span>
						</div>
						<div v-if="cert.validFrom_time_t || cert.validTo_time_t">
							<small>
								<strong v-if="cert.validFrom_time_t">{{ t('libresign', 'Valid from:') }}</strong>
								{{ formatTimestamp(cert.validFrom_time_t) }}
								<br v-if="cert.validFrom_time_t && cert.validTo_time_t">
								<strong v-if="cert.validTo_time_t">{{ t('libresign', 'Valid to:') }}</strong>
								{{ formatTimestamp(cert.validTo_time_t) }}
							</small>
						</div>
					</div>
				</template>
			</NcListItem>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import Moment from '@nextcloud/moment'

import {
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
} from '@mdi/js'

export default {
	name: 'CertificateChain',
	components: {
		NcButton,
		NcIconSvgWrapper,
		NcListItem,
	},
	props: {
		chain: {
			type: Array,
			required: true,
		},
	},
	setup() {
		return {
			mdiUnfoldLessHorizontal,
			mdiUnfoldMoreHorizontal,
			t,
		}
	},
	data() {
		return {
			chainOpen: false,
		}
	},
	methods: {
		formatTimestamp(timestamp) {
			if (!timestamp) return ''
			return Moment.unix(timestamp).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
.extra {
	padding-left: 44px;

	:deep(.list-item-content__name) {
		white-space: unset;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	:deep(.list-item__anchor) {
		height: unset;
	}
}

.chain-wrapper {
	padding-left: 44px;
}

.extra-chain {
	:deep(.list-item-content__name) {
		white-space: unset;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	:deep(.list-item__anchor) {
		height: unset;
	}
}

.certificate-item {
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 12px;
	margin-bottom: 12px;

	&:last-child {
		border-bottom: none;
		margin-bottom: 0;
		padding-bottom: 0;
	}
}

.cert-details {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 100%;

	> div {
		line-height: 1.4;
		word-break: break-word;
	}
}

.cert-issuer {
	font-size: 0.9em;
	opacity: 0.8;
}

.serial-hex {
	opacity: 0.7;
}

@media screen and (max-width: 700px) {
	.extra {
		phain-wrapper: 8px !important;
	}

	.cert-details {
		padding-left: 8px !important;
	}
}
</style>
