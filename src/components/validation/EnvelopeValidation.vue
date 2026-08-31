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
				<NcListItem v-if="envelopeFilesCount !== null" class="extra" compact>
					<template #name>
						<strong>{{ t('libresign', 'Number of documents:') }}</strong>
						{{ envelopeFilesCount }}
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
					<NcListItem :name="file.name" :active="isFileOpen(fileIndex)" @click="toggleFileDetail(fileIndex)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFilePdfBox" :size="44" />
						</template>
						<template #subname>
							<strong>{{ t('libresign', 'Status:') }}</strong> {{ getFileStatusText(file) }}
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
							<NcButton variant="tertiary" :aria-label="isFileOpen(fileIndex) ? t('libresign', 'Hide details') : t('libresign', 'Show details')" @click.stop="toggleFileDetail(fileIndex)">
								<template #icon>
									<NcIconSvgWrapper v-if="isFileOpen(fileIndex)" :path="mdiChevronUp" :size="20" />
									<NcIconSvgWrapper v-else :path="mdiChevronDown" :size="20" />
								</template>
							</NcButton>
						</template>
					</NcListItem>
					<div v-if="isFileOpen(fileIndex)" class="file-signers">
						<DocumentValidationDetails
							:document="file" />
					</div>
				</li>
			</ul>
		</div>

		<!-- Consolidated Signers and Observers -->
		<div v-if="hasParticipants" class="section card-list-context">
			<div class="header">
				<NcIconSvgWrapper :path="mdiAccountMultiple" :size="30" />
				<h1>{{ participantsSummaryTitle }}</h1>
			</div>
			<p v-if="signingParticipants.length > 0" class="section-help">
				{{ t('libresign', 'Overall progress of each signer across all documents') }}
			</p>
			<template v-for="section in participantSections" :key="section.role">
				<h2 class="participants-subheading">
					{{ section.title }}
				</h2>
				<ul class="signers-list">
					<li v-for="(signer, signerIndex) in section.participants" :key="`${section.role}-${signerIndex}`">
						<NcListItem :name="getName(signer)" :active="isParticipantOpen(section.role, signerIndex)" @click="toggleParticipantDetail(section.role, signerIndex)">
							<template #icon>
								<NcAvatar disable-menu :is-no-user="!signer.userId" :size="44" :user="signer.userId ? signer.userId : getName(signer)" :display-name="getName(signer)" />
							</template>
							<template #subname>
								<span class="signer-progress">
									{{ getSignerProgressText(signer) }}
								</span>
							</template>
							<template #extra-actions>
								<NcButton variant="tertiary" :aria-label="isParticipantOpen(section.role, signerIndex) ? t('libresign', 'Hide details') : t('libresign', 'Show details')" @click.stop="toggleParticipantDetail(section.role, signerIndex)">
									<template #icon>
										<NcIconSvgWrapper v-if="isParticipantOpen(section.role, signerIndex)" :path="mdiChevronUp" :size="20" />
										<NcIconSvgWrapper v-else :path="mdiChevronDown" :size="20" />
									</template>
								</NcButton>
							</template>
						</NcListItem>
						<div v-if="isParticipantOpen(section.role, signerIndex)" class="signer-details">
							<NcListItem v-if="signer.request_sign_date" class="detail-item" compact>
								<template #name>
									<strong>{{ t('libresign', 'Requested on:') }}</strong>
									{{ dateFromSqlAnsi(signer.request_sign_date) }}
								</template>
							</NcListItem>
							<NcListItem class="detail-item" compact>
								<template #name>
									<strong>{{ t('libresign', 'Date signed:') }}</strong>
									<span v-if="isObserverParticipant(signer)">{{ t('libresign', 'Observing') }}</span>
									<span v-else-if="signer.signed">{{ dateFromSqlAnsi(signer.signed) }}</span>
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
			</template>
		</div>
	</div>
</template>

<script setup lang="ts">
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import { computed, ref, watch } from 'vue'

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
import { isObserverParticipant, filterParticipantsByRole, PARTICIPANT_ROLE } from '../../utils/participantRole.ts'
import type {
	LoadedValidationEnvelopeDocument,
	SignerDetailRecord,
} from '../../types/index'

defineOptions({
	name: 'EnvelopeValidation',
})

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

type EnvelopeFile = NonNullable<LoadedValidationEnvelopeDocument['files']>[number]

type EnvelopeSigner = Partial<Pick<SignerDetailRecord, 'displayName' | 'email' | 'userId' | 'request_sign_date' | 'remote_address' | 'user_agent' | 'participantRole' | 'status'>> & {
	signed?: string | null
	documentsSignedCount?: number
	totalDocuments?: number
}

type EnvelopeDocument = LoadedValidationEnvelopeDocument & {
	signers?: EnvelopeSigner[]
	signedDate?: string
}

type ParticipantSectionRole = typeof PARTICIPANT_ROLE.SIGNER | typeof PARTICIPANT_ROLE.OBSERVER

type ParticipantSection = {
	role: ParticipantSectionRole
	title: string
	participants: EnvelopeSigner[]
}

const { isTouchDevice } = useIsTouchDevice()
const fileOpenState = ref<Record<number, boolean>>({})
const participantOpenState = ref<Record<string, boolean>>({})

const signingParticipants = computed(() => filterParticipantsByRole(props.document.signers, PARTICIPANT_ROLE.SIGNER))
const observerParticipants = computed(() => filterParticipantsByRole(props.document.signers, PARTICIPANT_ROLE.OBSERVER))
const hasParticipants = computed(() => signingParticipants.value.length > 0 || observerParticipants.value.length > 0)
const participantsSummaryTitle = computed(() => {
	if (signingParticipants.value.length > 0) {
		return t('libresign', 'Signers summary')
	}
	return t('libresign', 'Observers')
})
const participantSections = computed<ParticipantSection[]>(() => {
	const sections: ParticipantSection[] = []
	if (signingParticipants.value.length > 0) {
		sections.push({
			role: PARTICIPANT_ROLE.SIGNER,
			title: t('libresign', 'Signers'),
			participants: signingParticipants.value,
		})
	}
	if (observerParticipants.value.length > 0) {
		sections.push({
			role: PARTICIPANT_ROLE.OBSERVER,
			title: t('libresign', 'Observers'),
			participants: observerParticipants.value,
		})
	}
	return sections
})

const documentStatus = computed(() => getStatusLabel(props.document.status))
const envelopeFilesCount = computed(() => {
	if (typeof props.document.filesCount === 'number') {
		return props.document.filesCount
	}
	if (Array.isArray(props.document.files)) {
		return props.document.files.length
	}
	return null
})

function resetDisclosureState() {
	fileOpenState.value = {}
	participantOpenState.value = {}
}

function getParticipantStateKey(role: ParticipantSectionRole, participantIndex: number) {
	return `${role}-${participantIndex}`
}

function isParticipantOpen(role: ParticipantSectionRole, participantIndex: number) {
	return !!participantOpenState.value[getParticipantStateKey(role, participantIndex)]
}

function toggleParticipantDetail(role: ParticipantSectionRole, participantIndex: number) {
	const key = getParticipantStateKey(role, participantIndex)
	participantOpenState.value[key] = !isParticipantOpen(role, participantIndex)
}

function dateFromSqlAnsi(date: string) {
	return Moment(Date.parse(date)).format('LL LTS')
}

function toggleDetail(signerIndex: number) {
	toggleParticipantDetail(PARTICIPANT_ROLE.SIGNER, signerIndex)
}

function isSignerOpen(signerIndex: number) {
	return isParticipantOpen(PARTICIPANT_ROLE.SIGNER, signerIndex)
}

function isFileOpen(fileIndex: number) {
	return !!fileOpenState.value[fileIndex]
}

function toggleFileDetail(fileIndex: number) {
	fileOpenState.value[fileIndex] = !isFileOpen(fileIndex)
}

function getFileStatusText(file: EnvelopeFile) {
	return getStatusLabel(file.status)
}

function getName(signer: EnvelopeSigner) {
	// TRANSLATORS Fallback signer name shown when no display name and no email are available.
	return signer.displayName || signer.email || t('libresign', 'Unknown')
}

function getSignerProgressText(signer: EnvelopeSigner) {
	if (isObserverParticipant(signer)) {
		return t('libresign', 'Observing')
	}

	const progress = signer.documentsSignedCount || 0
	const total = signer.totalDocuments || 0
	// TRANSLATORS {progress} is how many envelope documents this signer has already signed. {total} is total documents assigned to this signer.
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

watch(() => props.document, () => {
	resetDisclosureState()
}, { immediate: true })

defineExpose({
	isTouchDevice,
	documentStatus,
	envelopeFilesCount,
	hasParticipants,
	participantsSummaryTitle,
	participantSections,
	isSignerOpen,
	isParticipantOpen,
	isFileOpen,
	getFileStatusText,
	dateFromSqlAnsi,
	toggleDetail,
	toggleParticipantDetail,
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

	.participants-subheading {
		font-size: 1.1rem;
		font-weight: 600;
		margin: 0 0 12px 0;
		color: var(--color-main-text);

		&:not(:first-of-type) {
			margin-top: 24px;
		}
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
