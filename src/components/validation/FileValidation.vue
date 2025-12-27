<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<!-- Document Information -->
		<div class="section">
			<div class="header">
				<NcIconSvgWrapper :path="mdiInformationSlabCircle" :size="30" />
				<h1>{{ t('libresign', 'Document information') }}</h1>
			</div>
			<NcNoteCard v-if="documentValidMessage" type="success">
				{{ documentValidMessage }}
			</NcNoteCard>
			<NcNoteCard v-if="isAfterSigned" type="success">
				{{ t('libresign', 'Congratulations you have digitally signed a document using LibreSign') }}
			</NcNoteCard>
			<ul>
				<NcListItem class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'Name:') }}</strong>
						{{ document.name }}
					</template>
				</NcListItem>
				<NcListItem v-if="document.status" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'Status:') }}</strong>
						{{ documentStatus }}
					</template>
				</NcListItem>
				<NcListItem v-if="document.totalPages" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'Total pages:') }}</strong>
						{{ document.totalPages }}
					</template>
				</NcListItem>
				<NcListItem v-if="document.size" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'File size:') }}</strong>
						{{ size }}
					</template>
				</NcListItem>
				<NcListItem v-if="document.pdfVersion" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'PDF version:') }}</strong>
						{{ document.pdfVersion }}
					</template>
				</NcListItem>
			</ul>
			<div class="info-document">
				<NcRichText v-if="legalInformation && document.signers && document.signers.length > 0"
					class="legal-information"
					:text="legalInformation"
					:use-markdown="true" />
				<NcButton v-if="document.nodeId" variant="primary" @click="viewDocument">
					<template #icon>
						<NcIconSvgWrapper :path="mdiEye" :size="20" />
					</template>
					{{ t('libresign', 'View document') }}
				</NcButton>
			</div>
		</div>

		<!-- Signatories with full validation details -->
		<div v-if="document.signers && document.signers.length > 0" class="section">
			<div class="header">
				<NcIconSvgWrapper :path="mdiSignatureFreehand" :size="30" />
				<h1>{{ n('libresign', 'Signatory', 'Signatories', document.signers.length) }}</h1>
			</div>
			<ul class="signers">
				<SignerDetails v-for="(signer, signerIndex) in document.signers"
					:key="signerIndex"
					:signer="signer" />
			</ul>
		</div>
	</div>
</template>

<script>
import {
	mdiAlertCircle,
	mdiAlertCircleOutline,
	mdiCancel,
	mdiCheckboxMarkedCircle,
	mdiCheckCircle,
	mdiEye,
	mdiHelpCircle,
	mdiInformationOutline,
	mdiInformationSlabCircle,
	mdiKey,
	mdiShieldCheck,
	mdiShieldOff,
	mdiSignatureFreehand,
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
} from '@mdi/js'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'

import Moment from '@nextcloud/moment'

import { fileStatus } from '../../helpers/fileStatus.js'
import SignerDetails from './SignerDetails.vue'

export default {
	name: 'FileValidation',
	components: {
		NcAvatar,
		NcButton,
		NcIconSvgWrapper,
		NcListItem,
		NcNoteCard,
		NcRichText,
		SignerDetails,
	},
	props: {
		document: {
			type: Object,
			required: true,
		},
		legalInformation: {
			type: String,
			default: '',
		},
		documentValidMessage: {
			type: String,
			default: '',
		},
		isAfterSigned: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			mdiAlertCircle,
			mdiAlertCircleOutline,
			mdiCancel,
			mdiCheckboxMarkedCircle,
			mdiCheckCircle,
			mdiEye,
			mdiHelpCircle,
			mdiInformationOutline,
			mdiInformationSlabCircle,
			mdiKey,
			mdiShieldCheck,
			mdiShieldOff,
			mdiSignatureFreehand,
			mdiUnfoldLessHorizontal,
			mdiUnfoldMoreHorizontal,
			EXPIRATION_WARNING_DAYS: 30,
			validationStatusOpenState: {},
			docMdpOpenState: {},
			extensionsOpenState: {},
			tsaOpenState: {},
			notificationsOpenState: {},
			chainOpenState: {},
			crlStatusMap: {
				revoked: {
					text: this.t('libresign', 'CRL: Certificate Revoked'),
					icon: mdiCancel,
					variant: 'error',
				},
				valid: {
					text: this.t('libresign', 'CRL: Certificate Valid'),
					icon: mdiCheckCircle,
					variant: 'success',
				},
				unknown: {
					text: this.t('libresign', 'CRL: Status Unknown'),
					icon: mdiHelpCircle,
					variant: 'warning',
				},
				unavailable: {
					text: this.t('libresign', 'CRL: Distribution Point Unavailable'),
					icon: mdiAlertCircle,
					variant: 'warning',
				},
			},
		}
	},
	computed: {
		size() {
			if (!this.document.size) {
				return ''
			}
			const size = parseInt(this.document.size)
			if (size < 1024) {
				return size + ' B'
			} else if (size < 1048576) {
				return (size / 1024).toFixed(2) + ' KB'
			} else {
				return (size / 1048576).toFixed(2) + ' MB'
			}
		},
		documentStatus() {
			const actual = fileStatus.find(item => item.id === this.document.status)
			if (actual === undefined) {
				return fileStatus.find(item => item.id === -1).label
			}
			return actual.label
		},
	},
	methods: {
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
		toggleDetail(signer) {
			this.$set(signer, 'opened', !signer.opened)
		},
		getName(signer) {
			return signer.displayName || signer.email || signer.signature_validation?.label || this.t('libresign', 'Unknown')
		},
		getIconValidityPath(signer) {
			if (signer.signature_validation?.id === 1) {
				return mdiCheckboxMarkedCircle
			}
			return mdiAlertCircle
		},
		viewDocument() {
			if (OCA?.Viewer !== undefined) {
				const fileInfo = {
					source: this.document.file,
					basename: this.document.name,
					mime: 'application/pdf',
					fileid: this.document.nodeId,
				}
				OCA.Viewer.open({
					fileInfo,
					list: [fileInfo],
				})
			} else {
				window.open(`${this.document.file}?_t=${Date.now()}`)
			}
		},
		getValidityStatus(signer) {
			if (!signer.valid_to) {
				return 'unknown'
			}

			const now = new Date()
			const expirationDate = new Date(signer.valid_to)

			if (expirationDate <= now) {
				return 'expired'
			}

			const warningDate = new Date()
			warningDate.setDate(now.getDate() + this.EXPIRATION_WARNING_DAYS)

			if (expirationDate <= warningDate) {
				return 'expiring'
			}

			return 'valid'
		},
		getValidityStatusAtSigning(signer) {
			if (!signer.signed || !signer.valid_from || !signer.valid_to) {
				return 'unknown'
			}

			const signedDate = new Date(signer.signed)
			const validFrom = new Date(signer.valid_from)
			const validTo = new Date(signer.valid_to)

			if (signedDate < validFrom || signedDate > validTo) {
				return 'expired'
			}

			return 'valid'
		},
		getSignatureValidationMessage(signer) {
			if (signer.signature_validation.id === 1) {
				return this.t('libresign', 'Document integrity verified')
			}
			return this.t('libresign', 'Signature: {validationStatus}', { validationStatus: signer.signature_validation.label })
		},
		getCertificateTrustMessage(signer) {
			if (!signer.certificate_validation) {
				return this.t('libresign', 'Trust Chain: Unknown')
			}

			if (signer.certificate_validation.id === 1) {
				if (signer.isLibreSignRootCA) {
					return this.t('libresign', 'Trust Chain: Trusted (LibreSign CA)')
				}
				return this.t('libresign', 'Trust Chain: Trusted')
			}

			return this.t('libresign', 'Trust chain: {validationStatus}', { validationStatus: signer.certificate_validation.label })
		},
		getCrlValidationIconClass(signer) {
			const variant = this.crlStatusMap[signer.crl_validation]?.variant
			if (variant === 'success') return 'icon-success'
			if (variant === 'error') return 'icon-error'
			if (variant === 'warning') return 'icon-warning'
			return 'icon-default'
		},
		camelCaseToTitleCase(text) {
			if (text.includes(' ')) {
				return text.replace(/^./, str => str.toUpperCase())
			}

			return text
				.replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
				.replace(/([a-z])([A-Z])/g, '$1 $2')
				.replace(/^./, str => str.toUpperCase())
				.trim()
		},
		hasValidationIssues(signer) {
			if (signer.signature_validation && signer.signature_validation.id !== 1) {
				return true
			}
			if (signer.certificate_validation && signer.certificate_validation.id !== 1) {
				return true
			}
			if (signer.crl_validation === 'revoked') {
				return true
			}
			if (signer.valid_from && signer.valid_to && signer.signed && this.getValidityStatusAtSigning(signer) !== 'valid') {
				return true
			}
			const currentStatus = this.getValidityStatus(signer)
			if (currentStatus === 'expired' || currentStatus === 'expiring') {
				return true
			}
			return false
		},
		hasDocMdpInfo(signer) {
			return signer.docmdp || signer.modifications || signer.modification_validation
		},
		getModificationStatusIcon(signer) {
			if (!signer.modification_validation) {
				return null
			}
			const status = signer.modification_validation.status
			const valid = signer.modification_validation.valid

			if (valid && status === 2) return mdiCheckCircle
			if (status === 1) return mdiCheckCircle
			if (status === 3) return mdiCancel
			return mdiHelpCircle
		},
		getModificationStatusClass(signer) {
			if (!signer.modification_validation) {
				return ''
			}
			const status = signer.modification_validation.status
			const valid = signer.modification_validation.valid

			if (valid && status === 2) return 'icon-success'
			if (status === 1) return 'icon-success'
			if (status === 3) return 'icon-error'
			return ''
		},
		formatTimestamp(timestamp) {
			return timestamp ? new Date(timestamp * 1000).toLocaleString() : ''
		},
		toggleState(stateObject, index) {
			this.$set(stateObject, index, !stateObject[index])
		},
		hasValidationStatus(signer) {
			return signer.signature_validation
				|| signer.certificate_validation
				|| (signer.valid_from && signer.valid_to && signer.signed)
				|| signer.crl_validation
		},
	},
}
</script>

<style lang="scss" scoped>
.section {
	margin-bottom: 30px;

	.header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 16px;

		h1 {
			font-size: 20px;
			font-weight: 600;
			margin: 0;
		}
	}

	ul {
		list-style: none;
		padding: 0;
		margin: 0;

		&.signers > li {
			margin-bottom: 12px;
		}
	}

	.extra {
		padding-left: 16px;
	}

	.extra-chain {
		padding-left: 32px;
	}

	.info-document {
		display: flex;
		flex-direction: column;
		gap: 16px;
		margin-top: 16px;

		.legal-information {
			padding: 12px;
			background-color: var(--color-background-hover);
			border-radius: var(--border-radius-large);
		}
	}

	.certificate-item {
		.cert-details {
			display: flex;
			flex-direction: column;
			gap: 4px;

			.cert-issuer {
				color: var(--color-text-maxcontrast);
			}

			.serial-hex {
				color: var(--color-text-maxcontrast);
				font-size: 0.9em;
			}
		}
	}

	.extension-value {
		word-break: break-all;
	}
}

.icon-success {
	color: var(--color-success);
}

.icon-error {
	color: var(--color-error);
}

.icon-warning {
	color: var(--color-warning);
}

.icon-default {
	color: var(--color-text-maxcontrast);
}

@media (max-width: 768px) {
	.section {
		.header h1 {
			font-size: 18px;
		}

		.extra {
			padding-left: 8px;
		}

		.extra-chain {
			padding-left: 16px;
		}
	}
}
</style>
