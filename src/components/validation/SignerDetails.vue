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
			<template #extra-actions>
				<NcButton v-if="signer.signed" variant="tertiary"
					:aria-label="toggleDetailsAriaLabel"
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
					{{ getCrlStatusText(signer) }}
				</template>
			</NcListItem>
		</div>

		<!-- Document Certification (DocMDP) Section -->
		<NcListItem v-if="isOpen && hasDocMdpInfo(signer)"
			class="extra"
			compact
			:name="t('libresign', 'Document certification')"
			:aria-expanded="docMdpOpen ? 'true' : 'false'"
			role="button"
			@click="docMdpOpen = !docMdpOpen">
			<template #name>
				<strong>{{ t('libresign', 'Document certification') }}</strong>
			</template>
			<template #extra-actions>
				<NcButton variant="tertiary"
					:aria-label="docMdpOpen ? t('libresign', 'Collapse document certification') : t('libresign', 'Expand document certification')"
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
			<NcListItem v-if="signer.docmdp_validation" class="extra-chain" compact>
				<template #icon>
					<NcIconSvgWrapper :path="mdiAlertCircle" class="icon-warning" />
				</template>
				<template #name>
					{{ signer.docmdp_validation.message }}
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
					{{ n('libresign', 'Document has %n revision', 'Document has %n revisions', signer.modifications.revisionCount ?? 0) }}
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

<script setup lang="ts">
import { n, t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import Moment from '@nextcloud/moment'
import { computed, ref } from 'vue'

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


type ValidationState = {
	id?: number
	message?: string
	trustedBy?: string
}

type SignerDocMdp = {
	isCertifying?: boolean
	label?: string
	description?: string
}

type SignerModificationValidation = {
	status?: number
	message?: string
}

type SignerModifications = {
	modified?: boolean
	revisionCount?: number
}

type SignerModel = {
	displayName?: string
	email?: string | null
	name?: string
	remote_address?: string
	user_agent?: string
	valid_from?: string | number
	valid_to?: string | number
	signed?: string | null
	signature_validation?: ValidationState
	certificate_validation?: ValidationState
	crl_validation?: string
	crl_revoked_at?: string
	docmdp?: SignerDocMdp
	docmdp_validation?: { message?: string }
	modification_validation?: SignerModificationValidation
	modifications?: SignerModifications
	signatureTypeSN?: string
	hash?: string
	chain?: Record<string, unknown>[]
}

type CrlStatusMeta = {
	icon: string
	text: string
	class: string
}

defineOptions({
	name: 'SignerDetails',
})

const props = withDefaults(defineProps<{
	signer: SignerModel
	initiallyOpen?: boolean
}>(), {
	initiallyOpen: false,
})

const isOpen = ref(props.initiallyOpen)
const validationStatusOpen = ref(false)
const docMdpOpen = ref(false)
const chainOpen = ref(false)
const MODIFICATION_UNMODIFIED = 1
const MODIFICATION_ALLOWED = 2
const MODIFICATION_VIOLATION = 3
const crlStatusMap: Record<string, CrlStatusMeta> = {
	valid: { icon: mdiCheckCircle, text: t('libresign', 'CRL: Not revoked'), class: 'icon-success' },
	revoked: { icon: mdiCloseCircle, text: t('libresign', 'CRL: Certificate revoked'), class: 'icon-error' },
	missing: { icon: mdiAlertCircle, text: t('libresign', 'CRL: No information'), class: 'icon-warning' },
	no_urls: { icon: mdiAlertCircle, text: t('libresign', 'CRL: No URLs found'), class: 'icon-warning' },
	urls_inaccessible: { icon: mdiHelpCircle, text: t('libresign', 'CRL: URLs inaccessible'), class: 'icon-warning' },
	validation_failed: { icon: mdiHelpCircle, text: t('libresign', 'CRL: Validation failed'), class: 'icon-warning' },
	validation_error: { icon: mdiHelpCircle, text: t('libresign', 'CRL: Validation error'), class: 'icon-warning' },
}

function toggleOpen() {
	if (!props.signer?.signed) {
		return
	}
	isOpen.value = !isOpen.value
}

function getName(signer: SignerModel) {
	return signer.displayName || signer.email || signer.name || t('libresign', 'Unknown')
}

function isRevokedStatus(status?: string) {
	return status === 'revoked'
}

function isRevokedBeforeSigning(signer: SignerModel) {
	if (!isRevokedStatus(signer.crl_validation)) {
		return false
	}

	const revokedAt = signer.crl_revoked_at
	const signedAt = signer.signed
	if (!revokedAt || !signedAt) {
		return true
	}

	const revokedDate = new Date(revokedAt)
	const signedDate = new Date(signedAt)
	if (Number.isNaN(revokedDate.getTime()) || Number.isNaN(signedDate.getTime())) {
		return true
	}

	return revokedDate <= signedDate
}

function hasValidationIssues(signer: SignerModel) {
	return signer.signature_validation?.id !== 1
		|| signer.certificate_validation?.id !== 1
		|| isRevokedBeforeSigning(signer)
}

function getIconValidityPath(signer: SignerModel) {
	if (signer.signature_validation?.id === 1) {
		return mdiCheckCircle
	}
	return mdiShieldAlert
}

function getValidityStatus(signer: SignerModel) {
	if (!signer.valid_to) return 'valid'

	const now = Date.now() / 1000
	const validTo = new Date(signer.valid_to).getTime() / 1000
	const thirtyDays = 30 * 24 * 60 * 60

	if (validTo < now) return 'expired'
	if (validTo - now < thirtyDays) return 'expiring'
	return 'valid'
}

function hasValidationStatus(signer: SignerModel) {
	return !!(signer.signature_validation || signer.certificate_validation || signer.crl_validation
		|| (signer.valid_from && signer.valid_to && signer.signed))
}

function getSignatureValidationMessage(signer: SignerModel) {
	if (signer.signature_validation?.id === 1) {
		return t('libresign', 'Document integrity verified')
	}
	return signer.signature_validation?.message || t('libresign', 'Document integrity check failed')
}

function getCertificateTrustMessage(signer: SignerModel) {
	if (signer.certificate_validation?.id === 1) {
		const trustedBy = signer.certificate_validation?.trustedBy || 'LibreSign CA'
		return t('libresign', 'Trust Chain: Trusted ({trustedBy})', { trustedBy })
	}
	return signer.certificate_validation?.message || t('libresign', 'Trust Chain: Not Trusted')
}

function getValidityStatusAtSigning(signer: SignerModel) {
	if (!signer.valid_from || !signer.valid_to || !signer.signed) return 'unknown'

	const signedTime = new Date(signer.signed).getTime() / 1000
	const validFrom = new Date(signer.valid_from).getTime() / 1000
	const validTo = new Date(signer.valid_to).getTime() / 1000

	return (signedTime >= validFrom && signedTime <= validTo) ? 'valid' : 'invalid'
}

function getCrlValidationIconClass(signer: SignerModel) {
	if (isRevokedStatus(signer.crl_validation)) {
		return isRevokedBeforeSigning(signer) ? 'icon-error' : 'icon-success'
	}
	return crlStatusMap[signer.crl_validation ?? '']?.class || 'icon-warning'
}

function dateFromSqlAnsi(date?: string | number | null) {
	if (!date) return ''
	return Moment(String(date)).format('LLL')
}

function getCrlStatusText(signer: SignerModel) {
	const status = signer.crl_validation
	if (!isRevokedStatus(status)) {
		return crlStatusMap[status ?? '']?.text || status
	}

	if (isRevokedBeforeSigning(signer)) {
		return t('libresign', 'CRL: Certificate revoked before signing')
	}

	if (signer.crl_revoked_at) {
		const formattedDate = dateFromSqlAnsi(signer.crl_revoked_at)
		return t('libresign', 'CRL: Valid at signing time (revoked on {date})', { date: formattedDate })
	}
	return t('libresign', 'CRL: Valid at signing time')
}

function hasDocMdpInfo(signer: SignerModel) {
	return !!(signer.docmdp
		|| signer.docmdp_validation
		|| signer.modification_validation
		|| (signer.modifications && signer.modifications.modified))
}

function getModificationStatusIcon(signer: SignerModel) {
	if (!signer.modification_validation) return undefined
	const status = signer.modification_validation.status
	if (status === MODIFICATION_UNMODIFIED || status === MODIFICATION_ALLOWED) {
		return mdiCheckCircle
	}
	return mdiAlertCircle
}

function getModificationStatusClass(signer: SignerModel) {
	if (!signer.modification_validation) return ''
	const status = signer.modification_validation.status
	if (status === MODIFICATION_UNMODIFIED || status === MODIFICATION_ALLOWED) {
		return 'icon-success'
	}
	return 'icon-error'
}

function formatTimestamp(timestamp?: number | null) {
	if (!timestamp) return ''
	return Moment.unix(timestamp).format('LLL')
}

const toggleDetailsAriaLabel = computed(() => {
	const signerName = getName(props.signer)
	if (isOpen.value) {
		return t('libresign', 'Collapse details of {signerName}', { signerName })
	}
	return t('libresign', 'Expand details of {signerName}', { signerName })
})

defineExpose({
	isOpen,
	validationStatusOpen,
	docMdpOpen,
	chainOpen,
	MODIFICATION_UNMODIFIED,
	MODIFICATION_ALLOWED,
	MODIFICATION_VIOLATION,
	crlStatusMap,
	toggleDetailsAriaLabel,
	toggleOpen,
	getName,
	hasValidationIssues,
	isRevokedStatus,
	isRevokedBeforeSigning,
	getIconValidityPath,
	getValidityStatus,
	hasValidationStatus,
	getSignatureValidationMessage,
	getCertificateTrustMessage,
	getValidityStatusAtSigning,
	getCrlValidationIconClass,
	getCrlStatusText,
	hasDocMdpInfo,
	getModificationStatusIcon,
	getModificationStatusClass,
	dateFromSqlAnsi,
	formatTimestamp,
})
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
