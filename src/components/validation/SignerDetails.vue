<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<li>
		<NcListItem :name="getName(signer)"
			:active="isOpen"
			@click="toggleOpen">
			<template #icon>
				<NcAvatar v-if="!hasValidationIssues(signer)"
					:display-name="getName(signer)"
					:is-no-user="true" />
				<NcIconSvgWrapper v-else
					:path="getIconValidityPath(signer)"
					:class="signer.signature_validation?.id === 1 ? 'icon-success' : 'icon-error'"
					:size="44" />
			</template>
			<template #subname>
				<template v-if="!signer.signed">
					<strong>{{ t('libresign', 'Status:') }}</strong>
					<span>{{ t('libresign', 'Not signed yet') }}</span>
				</template>
				<template v-else>
					<strong>{{ t('libresign', 'Expiration:') }}</strong>
					<span v-if="signer.valid_to">
						{{ dateFromSqlAnsi(signer.valid_to) }}
					</span>
					<span v-else>{{ t('libresign', 'No expiration date') }}</span>
				</template>
			</template>
			<template #indicator>
				<NcIconSvgWrapper v-if="signer.signed && getValidityStatus(signer) === 'expired'"
					:path="mdiAlertCircleOutline"
					class="icon-error"
					:size="20" />
				<NcIconSvgWrapper v-else-if="signer.signed && getValidityStatus(signer) === 'expiring'"
					:path="mdiAlertCircleOutline"
					class="icon-warning"
					:size="20" />
			</template>
			<template #extra-actions>
				<NcButton v-if="signer.signed" variant="tertiary"
					:aria-label="isOpen ? t('libresign', 'Collapse details') : t('libresign', 'Expand details')"
					@click.stop="toggleOpen">
					<template #icon>
						<NcIconSvgWrapper v-if="isOpen"
							:path="mdiUnfoldLessHorizontal"
							:size="20" />
						<NcIconSvgWrapper v-else
							:path="mdiUnfoldMoreHorizontal"
							:size="20" />
					</template>
				</NcButton>
			</template>
		</NcListItem>

		<!-- Date Signed -->
		<NcListItem v-if="isOpen && signer.signed"
			class="extra"
			compact
			:name="t('libresign', 'Date signed:')">
			<template #name>
				<strong>{{ t('libresign', 'Date signed:') }}</strong>
				<span v-if="signer.signed">
					{{ dateFromSqlAnsi(signer.signed) }}
				</span>
				<span v-else>{{ t('libresign', 'No date') }}</span>
			</template>
		</NcListItem>

		<!-- Validation Status Section -->
		<NcListItem v-if="isOpen && hasValidationStatus(signer)"
			class="extra"
			compact
			:name="t('libresign', 'Validation status')"
			:aria-expanded="validationStatusOpen ? 'true' : 'false'"
			role="button"
			@click="validationStatusOpen = !validationStatusOpen">
			<template #name>
				<strong>{{ t('libresign', 'Validation status') }}</strong>
			</template>
			<template #extra-actions>
				<NcButton variant="tertiary"
					:aria-label="validationStatusOpen ? t('libresign', 'Collapse validation status') : t('libresign', 'Expand validation status')"
					@click.stop="validationStatusOpen = !validationStatusOpen">
					<template #icon>
						<NcIconSvgWrapper v-if="validationStatusOpen"
							:path="mdiUnfoldLessHorizontal"
							:size="20" />
						<NcIconSvgWrapper v-else
							:path="mdiUnfoldMoreHorizontal"
							:size="20" />
					</template>
				</NcButton>
			</template>
		</NcListItem>
		<div v-if="isOpen && validationStatusOpen" role="region">
			<NcListItem v-if="signer.signature_validation" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="signer.signature_validation.id === 1 ? mdiCheckCircle : mdiAlertCircle"
						:class="signer.signature_validation?.id === 1 ? 'icon-success' : 'icon-error'" />
				</template>
				<template #name>
					{{ getSignatureValidationMessage(signer) }}
				</template>
			</NcListItem>
			<NcListItem v-if="signer.certificate_validation" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="signer.certificate_validation.id === 1 ? mdiCheckCircle : mdiAlertCircle"
						:class="signer.certificate_validation?.id === 1 ? 'icon-success' : 'icon-error'" />
				</template>
				<template #name>
					{{ getCertificateTrustMessage(signer) }}
				</template>
			</NcListItem>
			<NcListItem v-if="signer.valid_from && signer.valid_to && signer.signed" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="getValidityStatusAtSigning(signer) === 'valid' ? mdiCheckCircle : mdiCancel"
						:class="getValidityStatusAtSigning(signer) === 'valid' ? 'icon-success' : 'icon-error'" />
				</template>
				<template #name>
					{{ getValidityStatusAtSigning(signer) === 'valid' ? t('libresign', 'Valid at signing time') : t('libresign', 'NOT valid at signing time') }}
				</template>
			</NcListItem>
			<NcListItem v-if="signer.crl_validation" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="crlStatusMap[signer.crl_validation]?.icon || mdiHelpCircle"
						:class="getCrlValidationIconClass(signer)" />
				</template>
				<template #name>
					{{ crlStatusMap[signer.crl_validation]?.text || signer.crl_validation }}
				</template>
			</NcListItem>
		</div>

		<!-- Document Certification (DocMDP) Section -->
		<NcListItem v-if="isOpen && hasDocMdpInfo(signer)"
			class="extra"
			compact
			:name="t('libresign', 'Document certification')"
			role="button"
			@click="docMdpOpen = !docMdpOpen">
			<template #name>
				<strong>{{ t('libresign', 'Document certification') }}</strong>
			</template>
			<template #extra-actions>
				<NcButton variant="tertiary"
					@click.stop="docMdpOpen = !docMdpOpen">
					<template #icon>
						<NcIconSvgWrapper v-if="docMdpOpen"
							:path="mdiUnfoldLessHorizontal"
							:size="20" />
						<NcIconSvgWrapper v-else
							:path="mdiUnfoldMoreHorizontal"
							:size="20" />
					</template>
				</NcButton>
			</template>
			<template #indicator>
				<NcIconSvgWrapper v-if="getModificationStatusIcon(signer)"
					:path="getModificationStatusIcon(signer)"
					:class="getModificationStatusClass(signer)"
					:size="20" />
			</template>
		</NcListItem>
		<div v-if="isOpen && docMdpOpen && hasDocMdpInfo(signer)" role="region">
			<NcListItem v-if="signer.docmdp" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="signer.docmdp.isCertifying ? mdiShieldCheck : mdiShieldOff" />
				</template>
				<template #name>
					<strong>{{ t('libresign', 'Certification level:') }}</strong>
					{{ signer.docmdp.label }}
				</template>
			</NcListItem>
			<NcListItem v-if="signer.docmdp && signer.docmdp.description" class="extra-chain" compact>
				<template #name>
					{{ signer.docmdp.description }}
				</template>
			</NcListItem>
			<NcListItem v-if="signer.modification_validation" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="getModificationStatusIcon(signer)"
						:class="getModificationStatusClass(signer)" />
				</template>
				<template #name>
					{{ signer.modification_validation.message }}
				</template>
			</NcListItem>
			<NcListItem v-if="signer.modifications && signer.modifications.modified" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="mdiInformationOutline" />
				</template>
				<template #name>
					{{ n('libresign', 'Document has %n revision', 'Document has %n revisions', signer.modifications.revisionCount) }}
				</template>
			</NcListItem>
		</div>

		<!-- Hash Algorithm, Certificate Hash -->
		<NcListItem v-if="isOpen && signer.signatureTypeSN" class="extra" compact>
			<template #name>
				<strong>{{ t('libresign', 'Hash algorithm:') }}</strong>
				{{ signer.signatureTypeSN }}
			</template>
		</NcListItem>
		<NcListItem v-if="isOpen && signer.hash" class="extra" compact>
			<template #name>
				<strong>{{ t('libresign', 'Certificate hash:') }}</strong>
				{{ signer.hash }}
			</template>
		</NcListItem>

		<!-- Remote Address and User Agent -->
		<NcListItem v-if="isOpen && signer.remote_address" class="extra" compact>
			<template #name>
				<strong>{{ t('libresign', 'Remote address:') }}</strong>
				{{ signer.remote_address }}
			</template>
		</NcListItem>
		<NcListItem v-if="isOpen && signer.user_agent" class="extra" compact>
			<template #name>
				<strong>{{ t('libresign', 'User agent:') }}</strong>
				{{ signer.user_agent }}
			</template>
		</NcListItem>

		<!-- Certificate Chain Section -->
		<CertificateChain v-if="isOpen && signer.chain && signer.chain.length > 0"
			:chain="signer.chain" />
	</li>
</template>

<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import Moment from '@nextcloud/moment'

import CertificateChain from './CertificateChain.vue'

import {
	mdiAlertCircle,
	mdiAlertCircleOutline,
	mdiCancel,
	mdiCheckCircle,
	mdiCloseCircle,
	mdiHelpCircle,
	mdiInformationOutline,
	mdiKey,
	mdiShieldAlert,
	mdiShieldCheck,
	mdiShieldOff,
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
} from '@mdi/js'

export default {
	name: 'SignerDetails',
	components: {
		NcAvatar,
		NcButton,
		NcIconSvgWrapper,
		NcListItem,
		CertificateChain,
	},
	props: {
		signer: {
			type: Object,
			required: true,
		},
		initiallyOpen: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		return {
			mdiAlertCircle,
			mdiAlertCircleOutline,
			mdiCancel,
			mdiCheckCircle,
			mdiCloseCircle,
			mdiHelpCircle,
			mdiInformationOutline,
			mdiKey,
			mdiShieldAlert,
			mdiShieldCheck,
			mdiShieldOff,
			mdiUnfoldLessHorizontal,
			mdiUnfoldMoreHorizontal,
			t,
			n,
		}
	},
	data() {
		return {
			isOpen: this.initiallyOpen,
			validationStatusOpen: false,
			docMdpOpen: false,
			chainOpen: false,
			MODIFICATION_UNMODIFIED: 1,
			MODIFICATION_ALLOWED: 2,
			MODIFICATION_VIOLATION: 3,
			crlStatusMap: {
				CRL_VERIFIED_VALID: { icon: mdiCheckCircle, text: t('libresign', 'CRL: Certificate Valid'), class: 'icon-success' },
				CRL_VERIFIED_REVOKED: { icon: mdiCloseCircle, text: t('libresign', 'CRL: Certificate Revoked'), class: 'icon-error' },
				CRL_NOT_FOUND: { icon: mdiHelpCircle, text: t('libresign', 'CRL: Not Found'), class: 'icon-warning' },
				CRL_PARSE_FAILED: { icon: mdiAlertCircle, text: t('libresign', 'CRL: Parse Failed'), class: 'icon-error' },
				CRL_DOWNLOAD_FAILED: { icon: mdiAlertCircle, text: t('libresign', 'CRL: Download Failed'), class: 'icon-warning' },
			},
		}
	},
	methods: {
		toggleOpen() {
			if (!this.signer?.signed) {
				return
			}
			this.isOpen = !this.isOpen
		},
		getName(signer) {
			return signer.displayName || signer.email || signer.name || t('libresign', 'Unknown')
		},
		hasValidationIssues(signer) {
			return signer.signature_validation?.id !== 1 || signer.certificate_validation?.id !== 1
		},
		getIconValidityPath(signer) {
			if (signer.signature_validation?.id === 1) {
				return mdiCheckCircle
			}
			return mdiShieldAlert
		},
		getValidityStatus(signer) {
			if (!signer.valid_to) return 'valid'

			const now = Date.now() / 1000
			const validTo = new Date(signer.valid_to).getTime() / 1000
			const thirtyDays = 30 * 24 * 60 * 60

			if (validTo < now) return 'expired'
			if (validTo - now < thirtyDays) return 'expiring'
			return 'valid'
		},
		hasValidationStatus(signer) {
			return signer.signature_validation || signer.certificate_validation || signer.crl_validation
				|| (signer.valid_from && signer.valid_to && signer.signed)
		},
		getSignatureValidationMessage(signer) {
			if (signer.signature_validation?.id === 1) {
				return t('libresign', 'Document integrity verified')
			}
			return signer.signature_validation?.message || t('libresign', 'Document integrity check failed')
		},
		getCertificateTrustMessage(signer) {
			if (signer.certificate_validation?.id === 1) {
				const trustedBy = signer.certificate_validation?.trustedBy || 'LibreSign CA'
				return t('libresign', 'Trust Chain: Trusted ({trustedBy})', { trustedBy })
			}
			return signer.certificate_validation?.message || t('libresign', 'Trust Chain: Not Trusted')
		},
		getValidityStatusAtSigning(signer) {
			if (!signer.valid_from || !signer.valid_to || !signer.signed) return 'unknown'

			const signedTime = new Date(signer.signed).getTime() / 1000
			const validFrom = new Date(signer.valid_from).getTime() / 1000
			const validTo = new Date(signer.valid_to).getTime() / 1000

			return (signedTime >= validFrom && signedTime <= validTo) ? 'valid' : 'invalid'
		},
		getCrlValidationIconClass(signer) {
			const status = signer.crl_validation
			return this.crlStatusMap[status]?.class || 'icon-warning'
		},
		hasDocMdpInfo(signer) {
			return signer.docmdp || signer.modification_validation || (signer.modifications && signer.modifications.modified)
		},
		getModificationStatusIcon(signer) {
			if (!signer.modification_validation) return null
			const status = signer.modification_validation.status
			if (status === this.MODIFICATION_UNMODIFIED || status === this.MODIFICATION_ALLOWED) {
				return mdiCheckCircle
			}
			return mdiAlertCircle
		},
		getModificationStatusClass(signer) {
			if (!signer.modification_validation) return ''
			const status = signer.modification_validation.status
			if (status === this.MODIFICATION_UNMODIFIED || status === this.MODIFICATION_ALLOWED) {
				return 'icon-success'
			}
			return 'icon-error'
		},
		dateFromSqlAnsi(date) {
			if (!date) return ''
			return Moment(date).format('LLL')
		},
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

.icon-success {
	color: var(--color-success);
	:deep(svg) {
		fill: currentColor;
	}
}

.icon-error {
	color: var(--color-error);
	:deep(svg) {
		fill: currentColor;
	}
}

.icon-warning {
	color: var(--color-warning);
	:deep(svg) {
		fill: currentColor;
	}
}
</style>
