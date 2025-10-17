<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="Object.keys(certificate).length" class="certificate-content">
		<NcSettingsSection :name="t('libresign', 'Owner of certificate')">
			<div class="certificate-fields">
				<div v-for="(value, customName) in orderList(certificate.subject)"
					:key="customName"
					class="certificate-field">
					<span class="field-label">{{ getLabelFromId(customName) }}</span>
					<span class="field-value">{{ Array.isArray(value) ? value.join(', ') : value }}</span>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection v-if="index !== 0"
			:name="t('libresign', 'Issuer of certificate')">
			<div class="certificate-fields">
				<div v-for="(value, customName) in orderList(certificate.issuer)"
					:key="value"
					class="certificate-field">
					<span class="field-label">{{ getLabelFromId(customName) }}</span>
					<span class="field-value">{{ value }}</span>
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
					<span class="field-value">{{ certificate.valid_to }}</span>
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
			</div>
		</NcSettingsSection>

		<NcSettingsSection v-if="index === '0'"
			:name="t('libresign', 'Technical Details')">
			<div class="certificate-fields">
				<div class="certificate-field">
					<span class="field-label">Name</span>
					<span class="field-value">{{ certificate.name }}</span>
				</div>
				<div v-for="(value, name) in certificate.extensions"
					:key="name"
					class="certificate-field">
					<span class="field-label">{{ camelCaseToTitleCase(name) }}</span>
					<span class="field-value">{{ value }}</span>
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
		return {}
	},
	computed: {
		shouldShowPurposes() {
			return this.certificate.purposes &&
				Object.keys(this.certificate.purposes).length &&
				this.index === '0'
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
	},
}
</script>

<style lang="scss" scoped>
$border: 1px solid var(--color-border-dark);
$desktop: 768px;

.certificate-field {
	display: flex;
	flex-direction: column;
	padding: 12px 0;
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
</style>
