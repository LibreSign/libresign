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
			:aria-expanded="chainOpen ? 'true' : 'false'"
			@click="chainOpen = !chainOpen">
			<template #name>
				<!-- TRANSLATORS: "Certificate chain" is the sequence of digital identity cards that prove who signed the document — starting with the signer's own certificate and going up to the authority that vouches for everyone (the root CA). Like a chain of trust: "I trust you because this institution trusts you." -->
				<strong>{{ t('libresign', 'Certificate chain') }}</strong>
			</template>
			<template #extra-actions>
				<NcButton variant="tertiary"
				:aria-label="getToggleAriaLabel()"
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

		<!-- TRANSLATORS: Label read aloud by screen readers (for blind users) when they navigate into this section. It is not visible on screen. Describes the area that shows the certificate chain (the sequence of digital identity cards behind the signature) -->
		<div v-if="chainOpen" class="chain-wrapper" role="region" :aria-label="t('libresign', 'Certificate chain details')">
			<div v-for="(cert, certIndex) in chain"
				:key="certIndex"
				class="extra-chain certificate-item"
				:aria-label="getCertItemLabel(certIndex)">
				<dl class="cert-details">
					<div class="cert-field">
						<dt>{{ getCertRoleLabel(certIndex) }}</dt>
						<dd>{{ cert.subject?.CN || cert.name || cert.displayName }}</dd>
					</div>
					<div v-if="cert.issuer?.CN" class="cert-field cert-issuer">
						<!-- TRANSLATORS: Label shown next to the name of the organization or authority (Certificate Authority, CA) that issued and digitally signed the certificate, vouching for authenticity. Like the government agency that issues a passport. -->
						<dt>{{ t('libresign', 'Issued by:') }}</dt>
						<dd>{{ cert.issuer.CN }}</dd>
					</div>
					<div v-if="cert.serialNumber" class="cert-field">
						<!-- TRANSLATORS: Label shown next to the unique number assigned to a certificate by the issuing authority (CA), used to identify and, if necessary, revoke it. Like a passport number — every certificate has a different one. -->
						<dt>{{ t('libresign', 'Serial Number:') }}</dt>
						<dd>
							{{ cert.serialNumber }}
							<span v-if="cert.serialNumberHex" class="serial-hex">
								(hex: {{ cert.serialNumberHex }})
							</span>
						</dd>
					</div>
					<div v-if="cert.validFrom_time_t" class="cert-field">
						<!-- TRANSLATORS: Label shown next to the date and time from which a certificate becomes valid. Before that date the certificate cannot be trusted, even if it looks genuine. -->
						<dt>{{ t('libresign', 'Valid from:') }}</dt>
						<dd>{{ formatTimestamp(cert.validFrom_time_t) }}</dd>
					</div>
					<div v-if="cert.validTo_time_t" class="cert-field">
						<!-- TRANSLATORS: The date and time after which this certificate expires and can no longer be trusted. Like an expiry date on an ID card. -->
						<dt>{{ t('libresign', 'Valid to:') }}</dt>
						<dd>{{ formatTimestamp(cert.validTo_time_t) }}</dd>
					</div>
				</dl>
			</div>
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
		getToggleAriaLabel() {
			if (this.chainOpen) {
				// TRANSLATORS: Button label read by screen readers. Clicking it hides the list of certificates in the trust chain (the digital identity cards behind the signature)
				return t('libresign', 'Collapse certificate chain')
			}
			// TRANSLATORS: Button label read by screen readers. Clicking it reveals the list of certificates in the trust chain (the digital identity cards behind the signature)
			return t('libresign', 'Expand certificate chain')
		},
		getCertItemLabel(certIndex) {
			if (certIndex === 0) {
				// TRANSLATORS: Label read by screen readers to identify the first certificate — the one belonging to the person who actually signed the document
				return t('libresign', 'Signer certificate')
			}
			// TRANSLATORS: Label read by screen readers to identify additional certificates higher up in the trust chain. {index} is a number starting at 2 (e.g. "Certificate 2" is the issuing authority of the signer, "Certificate 3" is the authority above that, and so on)
			return t('libresign', 'Certificate {index}', { index: certIndex + 1 })
		},
		getCertRoleLabel(certIndex) {
			if (certIndex === 0) {
				// TRANSLATORS: Label shown next to the name of the person or entity who signed the document. Their identity is proven by their certificate.
				return t('libresign', 'Signer:')
			}
			// TRANSLATORS: Label shown next to the name of the Certificate Authority (CA) that issued the certificate above it in the chain. A CA is an organization trusted to verify and certify digital identities, like a notary or government agency.
			return t('libresign', 'Issuer:')
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
	padding: 0;
}

.certificate-item {
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 4px;
	margin-bottom: 4px;

	&:last-child {
		border-bottom: none;
		margin-bottom: 0;
		padding-bottom: 0;
	}
}

.cert-details {
	display: flex;
	flex-direction: column;
	gap: 2px;
	width: 100%;
	margin: 0;
	padding: 4px 0;
	list-style: none;
}

.cert-field {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 4px;
	line-height: 1.5;
	word-break: break-word;

	dt {
		font-weight: bold;
		min-width: 90px;
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

.cert-issuer {
	font-size: 0.9em;
	opacity: 0.8;
}

.serial-hex {
	opacity: 0.7;
}

@media screen and (max-width: 700px) {
	.extra {
		padding-left: 8px !important;
	}

	.cert-details {
		padding-left: 8px !important;
	}
}
</style>
