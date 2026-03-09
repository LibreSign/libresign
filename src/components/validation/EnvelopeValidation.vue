<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<!-- Envelope Summary -->
		<div class="section card-list-context">
			<div class="header">
				<NcIconSvgWrapper :path="mdiPackageVariantClosed" :size="30" />
				<h1>{{ t('libresign', 'Envelope information') }}</h1>
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
				<NcListItem v-if="document.filesCount" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'Number of documents:') }}</strong>
						{{ document.filesCount }}
					</template>
				</NcListItem>
				<NcListItem v-if="document.signedDate" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'Last signature date:') }}</strong>
						{{ dateFromSqlAnsi(document.signedDate) }}
					</template>
				</NcListItem>
			</ul>
			<div v-if="legalInformation" class="info-document">
				<NcRichText class="legal-information" :text="legalInformation" :use-markdown="true" />
			</div>
		</div>

		<!-- Documents List -->
		<div v-if="document.files && document.files.length > 0" class="section card-list-context">
			<div class="header">
				<NcIconSvgWrapper :path="mdiFileMultiple" :size="30" />
				<h1>{{ t('libresign', 'Documents in this envelope') }}</h1>
			</div>
			<p class="section-help">
				{{ t('libresign', 'Click on a document to view its details and signatures') }}
			</p>
			<ul class="documents-list">
				<li v-for="(file, fileIndex) in document.files" :key="fileIndex" class="document-item">
					<NcListItem :name="file.name" :active="file.opened" @click="toggleFileDetail(file)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFilePdfBox" :size="44" />
						</template>
						<template #subname>
							<strong>{{ t('libresign', 'Status:') }}</strong> {{ file.statusText }}
						</template>
						<template v-if="!isTouchDevice && file.nodeId" #actions>
							<NcActionButton @click.stop="viewFile(file)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiEye" :size="20" />
								</template>
								{{ t('libresign', 'View PDF') }}
							</NcActionButton>
						</template>
						<template #extra-actions>
							<NcButton v-if="isTouchDevice && file.nodeId" variant="tertiary" :aria-label="t('libresign', 'View PDF')" @click.stop="viewFile(file)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiEye" :size="20" />
								</template>
							</NcButton>
							<NcButton variant="tertiary" :aria-label="file.opened ? t('libresign', 'Hide details') : t('libresign', 'Show details')" @click.stop="toggleFileDetail(file)">
								<template #icon>
									<NcIconSvgWrapper v-if="file.opened" :path="mdiChevronUp" :size="20" />
									<NcIconSvgWrapper v-else :path="mdiChevronDown" :size="20" />
								</template>
							</NcButton>
						</template>
					</NcListItem>
					<div v-if="file.opened" class="file-signers">
						<DocumentValidationDetails
							:document="file"
						/>
					</div>
				</li>
			</ul>
		</div>

		<!-- Consolidated Signers -->
		<div v-if="document.signers && document.signers.length > 0" class="section card-list-context">
			<div class="header">
				<NcIconSvgWrapper :path="mdiAccountMultiple" :size="30" />
				<h1>{{ t('libresign', 'Signers summary') }}</h1>
			</div>
			<p class="section-help">
					{{ t('libresign', 'Overall progress of each signer across all documents') }}
			</p>
			<ul class="signers-list">
				<li v-for="(signer, signerIndex) in document.signers" :key="signerIndex">
					<NcListItem :name="getName(signer)" :active="signer.opened" @click="toggleDetail(signer)">
						<template #icon>
							<NcAvatar disable-menu :is-no-user="!signer.userId" :size="44" :user="signer.userId ? signer.userId : getName(signer)" :display-name="getName(signer)" />
						</template>
						<template #subname>
								<span class="signer-progress">
									{{ getSignerProgressText(signer) }}
								</span>
						</template>
						<template #extra-actions>
							<NcButton variant="tertiary" :aria-label="signer.opened ? t('libresign', 'Hide details') : t('libresign', 'Show details')" @click.stop="toggleDetail(signer)">
								<template #icon>
									<NcIconSvgWrapper v-if="signer.opened" :path="mdiChevronUp" :size="20" />
									<NcIconSvgWrapper v-else :path="mdiChevronDown" :size="20" />
								</template>
							</NcButton>
						</template>
					</NcListItem>
					<div v-if="signer.opened" class="signer-details">
						<NcListItem v-if="signer.request_sign_date" class="detail-item" compact>
							<template #name>
								<strong>{{ t('libresign', 'Requested on:') }}</strong>
								{{ dateFromSqlAnsi(signer.request_sign_date) }}
							</template>
						</NcListItem>
						<NcListItem class="detail-item" compact>
							<template #name>
								<strong>{{ t('libresign', 'Date signed:') }}</strong>
								<span v-if="signer.signed">{{ dateFromSqlAnsi(signer.signed) }}</span>
								<span v-else>{{ t('libresign', 'Not signed yet') }}</span>
							</template>
						</NcListItem>
						<NcListItem v-if="signer.remote_address" class="detail-item" compact>
							<template #name>
								<strong>{{ t('libresign', 'Remote address:') }}</strong>
								{{ signer.remote_address }}
							</template>
						</NcListItem>
						<NcListItem v-if="signer.user_agent" class="detail-item" compact>
							<template #name>
								<strong>{{ t('libresign', 'User agent:') }}</strong>
								{{ signer.user_agent }}
							</template>
						</NcListItem>
					</div>
				</li>
			</ul>
		</div>
	</div>
</template>

<script setup lang="ts">
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, watch } from 'vue'

import {
	mdiAccountMultiple,
	mdiChevronDown,
	mdiChevronUp,
	mdiEye,
	mdiFileMultiple,
	mdiFilePdfBox,
	mdiPackageVariantClosed,
} from '@mdi/js'
import Moment from '@nextcloud/moment'
import { getStatusLabel } from '../../utils/fileStatus.js'
import { openDocument } from '../../utils/viewer.js'
import { useIsTouchDevice } from '../../composables/useIsTouchDevice.js'
import DocumentValidationDetails from './DocumentValidationDetails.vue'
import type { FileRecord, SignerRecord } from '../../types/index'

defineOptions({
	name: 'EnvelopeValidation',
})

type EnvelopeFile = Omit<FileRecord, 'status' | 'statusText' | 'signers'> & {
	status: string | number
	opened?: boolean
	statusText?: string
	totalPages?: number
	size?: string | number
	pdfVersion?: string
	signers?: Array<Record<string, unknown>>
}

type EnvelopeSigner = Omit<Pick<SignerRecord, 'displayName' | 'email' | 'userId' | 'request_sign_date' | 'signed' | 'remote_address' | 'user_agent'>, 'signed'> & {
	opened?: boolean
	signed?: string
	documentsSignedCount?: number
	totalDocuments?: number
}

type EnvelopeDocument = Omit<FileRecord, 'status' | 'files' | 'signers'> & {
	status?: string | number
	files?: EnvelopeFile[]
	signers?: EnvelopeSigner[]
	signedDate?: string
}

const props = withDefaults(defineProps<{
	document: EnvelopeDocument
	legalInformation?: string
	documentValidMessage?: string | null
	isAfterSigned?: boolean
}>(), {
	legalInformation: '',
	documentValidMessage: null,
	isAfterSigned: false,
})

const { isTouchDevice } = useIsTouchDevice()

const documentStatus = computed(() => getStatusLabel(props.document.status))

function initializeDocument(doc: EnvelopeDocument) {
	doc.files?.forEach((file) => {
		file.opened = false
		file.statusText = getStatusLabel(file.status)
	})
}

function dateFromSqlAnsi(date: string) {
	return Moment(Date.parse(date)).format('LL LTS')
}

function toggleDetail(signer: EnvelopeSigner) {
	signer.opened = !signer.opened
}

function toggleFileDetail(file: EnvelopeFile) {
	file.opened = !file.opened
}

function getName(signer: EnvelopeSigner) {
	return signer.displayName || signer.email || t('libresign', 'Unknown')
}

function getSignerProgressText(signer: EnvelopeSigner) {
	const progress = signer.documentsSignedCount || 0
	const total = signer.totalDocuments || 0
	return n('libresign', '{progress} of {total} document signed', '{progress} of {total} documents signed', total, { progress, total })
}

function viewFile(file: EnvelopeFile) {
	if (!file.uuid || !file.name || typeof file.nodeId !== 'number') {
		return
	}
	const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: file.uuid })
	openDocument({
		fileUrl,
		filename: file.name,
		nodeId: file.nodeId,
	})
}

watch(() => props.document, (newDoc) => {
	initializeDocument(newDoc)
}, { immediate: true })

defineExpose({
	isTouchDevice,
	documentStatus,
	initializeDocument,
	dateFromSqlAnsi,
	toggleDetail,
	toggleFileDetail,
	getName,
	getSignerProgressText,
	viewFile,
})
</script>

<style lang="scss" scoped>
.section {
	background-color: var(--color-main-background);
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 0 6px 0 var(--color-box-shadow);
	margin-bottom: 16px;

	@media screen and (max-width: 700px) {
		padding: 12px 8px;
		box-shadow: none;
		border-top: 2px solid var(--color-border-dark);
		border-radius: 0;
		margin-bottom: 0;
		margin-top: 12px;

		&:first-child {
			border-top: none;
			margin-top: 0;
		}
	}

	.header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 1.5rem;

		h1 {
			font-size: 1.5rem;
			margin: 0;
		}
	}

	.section-help {
		color: var(--color-text-maxcontrast);
		margin: -8px 0 16px 0;
		font-size: 0.95rem;
	}

	.info-document {
		margin-top: 16px;

		.legal-information {
			opacity: 0.8;
			font-size: 1rem;
		}
	}
}

.extra {
	:deep(.list-item-content__name) {
		white-space: normal;
	}
}

.documents-list,
.signers-list {
	list-style: none;
	padding: 0;
	margin: 0;

	li {
		margin-bottom: 8px;
	}
}

.document-item {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: visible;
	margin-bottom: 12px;

	&:last-child {
		margin-bottom: 0;
	}
}

.file-signers {
	background-color: var(--color-background-dark);
	padding: 12px 16px;
	border-top: 1px solid var(--color-border);

	.signers-title {
		font-size: 0.9rem;
		font-weight: 600;
		margin: 0 0 12px 0;
		color: var(--color-text-maxcontrast);
	}

	.file-signers-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}
}

.card-list-context {
	:deep(.list-item__wrapper) {
		margin-left: 0;
		margin-right: 0;
		border-radius: 8px;
	}
}

.signer-details {
	background-color: var(--color-main-background);
	padding: 8px 16px 8px 60px;
	margin-top: 4px;
	border-left: 3px solid var(--color-border);

	@media screen and (max-width: 700px) {
		padding-left: 16px;
	}

	.detail-item {
		margin-bottom: 4px;

		:deep(.list-item-content__name) {
			white-space: normal;
			line-height: 1.4;
		}
	}
}

.signer-progress {
	font-size: 0.95em;
	color: var(--color-text-maxcontrast);

	:deep(.list-item__wrapper--active) & {
		color: var(--color-primary-element-text);
	}
}
</style>
