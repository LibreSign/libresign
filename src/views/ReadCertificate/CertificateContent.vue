<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="Object.keys(certificate).length" class="certificate-content">
		<NcSettingsSection :name="t('libresign', 'Owner of certificate')">
			<div class="certificate-fields">
				<div v-for="(value, customName, index) in orderList(certificate.subject)"
					:key="`subject-${customName}-${index}`"
					class="certificate-field">
					<span class="field-label">{{ getLabelFromId(customName) }}</span>
					<span class="field-value">{{ Array.isArray(value) ? value.join(', ') : value }}</span>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection v-if="index !== 0"
			:name="t('libresign', 'Issuer of certificate')">
			<div class="certificate-fields">
				<div v-for="(value, customName, index) in orderList(certificate.issuer)"
					:key="`issuer-${customName}-${index}`"
					class="certificate-field">
					<span class="field-label">{{ getLabelFromId(customName) }}</span>
					<span class="field-value">{{ Array.isArray(value) ? value.join(', ') : value }}</span>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection v-if="certificate.extracerts && index === '0'"
			:name="t('libresign', 'Certificate chain')">
			<div class="certificate-chain-container">
				<div v-for="(extra, key) in certificate.extracerts"
					:key="`extracerts-${key}`"
					class="chain-certificate">
					<h4 class="chain-certificate-title">
						{{ getChainCertificateLabel(key, extra) }}
					</h4>
					<CertificateContent :certificate="extra" :index="index + '_' + key" />
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection :name="t('libresign', 'Certificate Information')">
			<div class="certificate-fields">
				<div v-if="index === '0'" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Valid from') }}</span>
					<span class="field-value">{{ certificate.valid_from }}</span>
				</div>
				<div v-if="index === '0'" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Valid to') }}</span>
					<span class="field-value">
						<div class="certificate-validity">
							<NcChip
								:text="certificateValidityStatus.text"
								:variant="certificateValidityStatus.variant"
								:icon-path="certificateValidityStatus.icon"
								no-close />
							{{ certificate.valid_to }}
						</div>
					</span>
				</div>
				<div v-if="certificate.version !== undefined" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Version') }}</span>
					<span class="field-value">{{ certificate.version + 1 }}</span>
				</div>
				<div v-if="certificate.hash" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Fingerprint') }}</span>
					<span class="field-value">{{ certificate.hash }}</span>
				</div>
				<div v-if="certificate.signatureTypeLN" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Signature algorithm') }}</span>
					<span class="field-value">{{ certificate.signatureTypeLN }}</span>
				</div>
				<div v-if="certificate.serialNumber" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Serial number') }}</span>
					<span class="field-value">{{ certificate.serialNumber }}</span>
				</div>
				<div v-if="certificate.serialNumberHex" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'Serial number (hex)') }}</span>
					<span class="field-value">{{ certificate.serialNumberHex }}</span>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection v-if="index === '0'"
			:name="t('libresign', 'Technical Details')">
			<div class="certificate-fields">
				<div class="certificate-field">
					<span class="field-label">Name</span>
					<span class="field-value">{{ certificate.name }}</span>
				</div>
				<div v-for="(value, name, index) in certificate.extensions"
					:key="`extension-${name}-${index}`"
					class="certificate-field">
					<span class="field-label">{{ camelCaseToTitleCase(name) }}</span>
					<span class="field-value">{{ value }}</span>
				</div>
				<div v-if="certificate.crl_validation !== undefined" class="certificate-field">
					<span class="field-label">{{ t('libresign', 'CRL Validation Status') }}</span>
					<span class="field-value">
						<NcChip
							:text="crlValidationStatus.text"
							:variant="crlValidationStatus.variant"
							:icon-path="crlValidationStatus.icon"
							no-close />
					</span>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection v-if="shouldShowPurposes"
			:name="t('libresign', 'Certificate purposes')">
			<div class="purposes-grid">
				<NcNoteCard v-for="(purpose, purposeIndex) in certificate.purposes"
					:key="purposeIndex"
					:type="purpose[0] ? 'success' : 'error'"
					:heading="formatPurposeName(purpose[2])">
					<div class="purpose-status">
						<span v-if="purpose[0]">{{ t('libresign', 'Allowed') }}</span>
						<span v-else>{{ t('libresign', 'Not allowed') }}</span>
						<NcChip v-if="purpose[1]" no-close>CA</NcChip>
					</div>
				</NcNoteCard>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script>

import { selectCustonOption } from '../../helpers/certification.js'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcChip from '@nextcloud/vue/dist/Components/NcChip.js'
import {
	mdiCheckCircle,
	mdiCancel,
	mdiAlertCircleOutline,
	mdiHelpCircle,
	mdiShieldCheck,
	mdiShieldAlert,
	mdiShieldOff,
} from '@mdi/js'

export default {
	name: 'CertificateContent',
	components: {
		NcSettingsSection,
		NcNoteCard,
		NcChip,
	},
	props: {
		certificate: {
			type: Object,
			default: () => {},
			required: false,
		},
		index: {
			type: String,
			default: '0',
		},
	},
	data() {
		return {
			mdiCheckCircle,
			mdiCancel,
			mdiAlertCircleOutline,
			mdiHelpCircle,
			mdiShieldCheck,
			mdiShieldAlert,
			mdiShieldOff,
			EXPIRATION_WARNING_DAYS: 30,
		}
	},
	computed: {
		shouldShowPurposes() {
			return this.certificate.purposes &&
				Object.keys(this.certificate.purposes).length &&
				this.index === '0'
		},
		validityStatusMap() {
			return {
				unknown: { text: this.t('libresign', 'Unknown'), variant: 'tertiary', icon: this.mdiHelpCircle },
				expired: { text: this.t('libresign', 'Expired'), variant: 'error', icon: this.mdiCancel },
				expiring: { text: this.t('libresign', 'Expires Soon'), variant: 'warning', icon: this.mdiAlertCircleOutline },
				valid: { text: this.t('libresign', 'Valid'), variant: 'success', icon: this.mdiCheckCircle }
			}
		},
		crlStatusMap() {
			return {
				valid: { text: this.t('libresign', 'Valid (Not Revoked)'), variant: 'success', icon: this.mdiShieldCheck },
				revoked: { text: this.t('libresign', 'Revoked'), variant: 'error', icon: this.mdiShieldOff },
				missing: { text: this.t('libresign', 'No CRL Information'), variant: 'warning', icon: this.mdiShieldAlert },
				no_urls: { text: this.t('libresign', 'No CRL URLs Found'), variant: 'warning', icon: this.mdiShieldAlert },
				urls_inaccessible: { text: this.t('libresign', 'CRL URLs Inaccessible'), variant: 'tertiary', icon: this.mdiHelpCircle },
				validation_failed: { text: this.t('libresign', 'CRL Validation Failed'), variant: 'tertiary', icon: this.mdiHelpCircle },
				validation_error: { text: this.t('libresign', 'CRL Validation Error'), variant: 'tertiary', icon: this.mdiHelpCircle }
			}
		},
		certificateValidityStatus() {
			return this.validityStatusMap[this.getValidityStatus()]
		},
		crlValidationStatus() {
			return this.crlStatusMap[this.certificate.crl_validation] || {
				text: this.t('libresign', 'Unknown Status'),
				variant: 'tertiary',
				icon: this.mdiHelpCircle
			}
		},
	},
	methods: {
		orderList(data) {
			const sorted = {};
			['CN', 'OU', 'O'].forEach(element => {
				if (data[element]) {
					sorted[element] = data[element]
				}
			})
			Object.keys(data).forEach((key) => {
				if (!sorted[key]) {
					sorted[key] = data[key]
				}
			})
			return sorted
		},
		getLabelFromId(id) {
			const option = selectCustonOption(id)
			if (option.isSome()) {
				return this.camelCaseToTitleCase(option.unwrap().label)
			}
			return this.camelCaseToTitleCase(id)
		},
		camelCaseToTitleCase(text) {
			if (text.includes(' ')) {
				return text.replace(/^./, str => str.toUpperCase())
			}

			return text
				// Handle acronyms (consecutive uppercase letters)
				.replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
				// Add space before uppercase letters that follow lowercase
				.replace(/([a-z])([A-Z])/g, '$1 $2')
				// Capitalize first letter
				.replace(/^./, str => str.toUpperCase())
				.trim()
		},
		formatPurposeName(purpose) {
			const purposeNames = {
				'sslclient': this.t('libresign', 'SSL Client'),
				'sslserver': this.t('libresign', 'SSL Server'),
				'nssslserver': this.t('libresign', 'Netscape SSL Server'),
				'smimesign': this.t('libresign', 'S/MIME Signing'),
				'smimeencrypt': this.t('libresign', 'S/MIME Encryption'),
				'crlsign': this.t('libresign', 'CRL Signing'),
				'any': this.t('libresign', 'Any Purpose'),
				'ocsphelper': this.t('libresign', 'OCSP Helper'),
				'timestampsign': this.t('libresign', 'Timestamp Signing'),
				'codesign': this.t('libresign', 'Code Signing'),
			}
			return purposeNames[purpose] || purpose
		},
		getChainCertificateLabel(index, certificate) {
			if (index === 0) {
				return this.t('libresign', 'Intermediate Certificate')
			}
			if (certificate.subject && certificate.issuer &&
				JSON.stringify(certificate.subject) === JSON.stringify(certificate.issuer)) {
				return this.t('libresign', 'Root Certificate (CA)')
			}
			return this.t('libresign', 'Certificate {number}', { number: index + 1 })
		},
		getValidityStatus() {
			if (!this.certificate.validTo_time_t) {
				return 'unknown'
			}

			const now = new Date()
			const expirationDate = this.unixTimestampToDate(this.certificate.validTo_time_t)

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
		unixTimestampToDate(unixTimestamp) {
			return new Date(unixTimestamp * 1000)
		},
	},
}
</script>

<style lang="scss" scoped>
$border: 1px solid var(--color-border-dark);
$desktop: 768px;

.certificate-field {
	display: flex;
	flex-direction: column;
	padding: 6px 0;
	border-bottom: $border;
	gap: 4px;

	&:last-child { border-bottom: none; }

	@media (min-width: $desktop) {
		flex-direction: row;
		gap: 16px;
	}
}

.field {
	&-label {
		color: var(--color-text-maxcontrast);
		align-self: flex-start;

		@media (min-width: $desktop) {
			min-width: 140px;
			max-width: 140px;
			text-align: right;
			padding-right: 16px;
			border-right: $border;
			word-wrap: break-word;
		}
	}

	&-value {
		word-break: break-all;
		align-self: flex-start;
	}
}

.chain-certificate {
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
	margin-bottom: 16px;

	&-title {
		background: var(--color-background-dark);
		padding: 12px 16px;
		font-weight: 600;
		border-radius: var(--border-radius) var(--border-radius) 0 0;
	}
}

.purposes {
	&-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
		gap: 12px;

		:deep(.notecard__heading) { font-size: unset; }
	}
}

.purpose-status {
	display: flex;
	gap: 8px;
}

.certificate-validity {
	display: flex;
	align-items: center;
	gap: 8px;
}
</style>
