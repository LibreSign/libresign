<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<div class="logo">
			<img :src="logo" :alt="t('libresign', 'LibreSign logo')" draggable="false">
		</div>
		<div id="validation-content">
				<div v-if="isAsyncSigning" class="infor-container">
					<div class="section">
						<SigningProgress
							:sign-request-uuid="signRequestUuidForProgress"
							@completed="handleSigningComplete"
							@error="handleSigningError" />
					</div>
				</div>
			<div v-else-if="!hasInfo" class="infor-container">
				<div class="section">
					<!-- TRANSLATORS: "Validate" here is a technical process: checking the cryptographic integrity of the signatures, the certificate chain and revocation status. It does NOT mean approving or authorizing something. Choose a word in your language that conveys "to check" or "to verify", not "to approve" or "to authorize". -->
					<h1>{{ t('libresign', 'Validate signature') }}</h1>
					<NcNoteCard v-if="validationErrorMessage" type="error">
						{{ validationErrorMessage }}
					</NcNoteCard>
					<!-- TRANSLATORS: Same meaning as the previous string: technical process of checking cryptographic integrity of signatures, NOT an approval. -->
					<NcActions :menu-name="t('libresign', 'Validate signature')" :inline="3" :force-name="true">
						<NcActionButton :wide="true" :disabled="loading" @click="openUuidDialog()">
							<!-- TRANSLATORS: "UUID" is a unique technical identifier for a document (a code like '550e8400-e29b-41d4-a716-446655440000'). Keep "UUID" untranslated. -->
							{{ t('libresign', 'From UUID') }}
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
								<NcIconSvgWrapper v-else :path="mdiKey" />
							</template>
						</NcActionButton>
						<NcActionButton :wide="true" :disabled="loading" @click="uploadFile">
							{{ t('libresign', 'Upload') }}
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
								<NcIconSvgWrapper v-else :path="mdiUpload" />
							</template>
						</NcActionButton>
					</NcActions>
					<!-- TRANSLATORS: Same meaning as the first string in this section: technical process of checking cryptographic integrity of signatures, NOT an approval. -->
					<NcDialog v-if="getUUID" :name="t('libresign', 'Validate signature')" is-form
						@closing="getUUID = false">
						<!-- TRANSLATORS: Same meaning as the previous string: technical process of checking cryptographic integrity of signatures, NOT an approval. -->
						<h1>{{ t('libresign', 'Validate signature') }}</h1>
						<NcTextField v-model="uuidToValidate"
							:label="t('libresign', 'Enter the ID or UUID of the document to validate.')"
							:helper-text="helperTextValidation" :error="!!uuidToValidate && !canValidate" />
						<template #actions>
							<NcButton variant="primary" :disabled="loading || !canValidate"
								@click.prevent="validateAndProceed">
								<template #icon>
									<NcLoadingIcon v-if="loading" :size="20" />
								</template>
								<!-- TRANSLATORS: "Validation" here is the technical process of checking cryptographic integrity of signatures, NOT an approval or authorization. -->
								{{ t('libresign', 'Validation') }}
							</NcButton>
						</template>
					</NcDialog>
				</div>
			</div>
			<div v-else-if="validationEnvelopeDocument || validationFileDocument" class="infor-container">
				<EnvelopeValidation
					v-if="validationEnvelopeDocument"
					:document="validationEnvelopeDocument"
					:legal-information="legalInformation"
					:document-valid-message="documentValidMessage"
					:is-after-signed="isAfterSigned" />
				<FileValidation
					v-else-if="validationFileDocument"
					:document="validationFileDocument"
					:legal-information="legalInformation"
					:document-valid-message="documentValidMessage"
					:is-after-signed="isAfterSigned" />
				<NcButton v-if="clickedValidate" class="change" variant="primary" @click="goBack()">
					<template #icon>
						<NcIconSvgWrapper :path="mdiArrowLeft" />
					</template>
					{{ t('libresign', 'Return') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import {
	mdiAlertCircle,
	mdiAlertCircleOutline,
	mdiArrowLeft,
	mdiCancel,
	mdiCheckCircle,
	mdiCheckboxMarkedCircle,
	mdiHelpCircle,
	mdiKey,
	mdiUpload,
} from '@mdi/js'
import JSConfetti from 'js-confetti'
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { computed, getCurrentInstance, onBeforeUnmount, ref, watch } from 'vue'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
// eslint-disable-next-line import/no-named-as-default
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import EnvelopeValidation from '../components/validation/EnvelopeValidation.vue'
import FileValidation from '../components/validation/FileValidation.vue'
import SigningProgress from '../components/validation/SigningProgress.vue'

import logoGray from '../../img/logo-gray.svg'
import { openDocument } from '../utils/viewer.js'
import { getStatusLabel } from '../utils/fileStatus.js'
import { getSigningRouteUuid } from '../utils/signRequestUuid.ts'
import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../constants.js'
import logger from '../logger.js'
import { useFilesStore } from '../store/files.js'
import { useSignStore } from '../store/sign.js'
import { useSidebarStore } from '../store/sidebar.js'
import type {
	LoadedValidationEnvelopeDocument,
	LoadedValidationFileDocument,
	SignerDetailRecord,
	ValidatedChildFileRecord,
	ValidationFileRecord,
} from '../types/index'

defineOptions({
	name: 'Validation',
})

type RouteState = {
	name: string | null
	params: Record<string, string>
	query: Record<string, string>
}

type RouterState = {
	push: (location: unknown) => void
	replace: (location: unknown) => void
}

type ToggleOpenState = Record<number, boolean>
type ValidationStatus = ValidationFileRecord['status']
type ValidationStatusInfo = {
	id?: number
	label?: string
}
type ValidationModificationInfo = {
	status?: number
	valid?: boolean
}
type ValidationDisplaySigner = SignerDetailRecord & {
	signature_validation?: ValidationStatusInfo
	certificate_validation?: ValidationStatusInfo
	modification_validation?: ValidationModificationInfo
	crl_validation?: string
	docmdp?: unknown
	modifications?: unknown
	isLibreSignRootCA?: boolean
	status?: string | number
}
type StatusPresentation = {
	text: string
	variant: string
	icon: string
}
type ErrorMessageEntry = {
	message?: string
}
type ValidationErrorResponse = {
	status?: number
	data?: {
		ocs?: {
			data?: {
				errors?: ErrorMessageEntry[]
			}
		}
	}
}

type ValidationMetadataDimension = {
	w: number
	h: number
}

type UnknownRecord = Record<string, unknown>

function isRecord(value: unknown): value is Record<string, unknown> {
	return typeof value === 'object' && value !== null
}

function hasOwn(record: UnknownRecord, key: string): boolean {
	return Object.prototype.hasOwnProperty.call(record, key)
}

function isOptionalField(record: UnknownRecord, key: string, guard: (value: unknown) => boolean): boolean {
	return !hasOwn(record, key) || guard(record[key])
}

function normalizeRouteRecord(value: unknown, source: 'params' | 'query'): Record<string, string> {
	if (!isRecord(value)) {
		return {}
	}

	const result: Record<string, string> = {}
	const droppedKeys: string[] = []
	for (const [key, entry] of Object.entries(value)) {
		if (typeof entry === 'string') {
			result[key] = entry
		} else {
			droppedKeys.push(key)
		}
	}

	if (droppedKeys.length > 0) {
		logger.warn('Validation route normalization dropped non-string entries', {
			source,
			droppedKeys,
		})
	}

	return result
}

function toNumber(value: unknown): number | null {
	return typeof value === 'number' && Number.isFinite(value) ? value : null
}

const VALIDATION_STATUS_VALUES: readonly ValidationStatus[] = [
	FILE_STATUS.DRAFT,
	FILE_STATUS.ABLE_TO_SIGN,
	FILE_STATUS.PARTIAL_SIGNED,
	FILE_STATUS.SIGNED,
	FILE_STATUS.DELETED,
]

function isValidationStatus(value: unknown): value is ValidationStatus {
	const normalizedValue = toNumber(value)
	return normalizedValue !== null && VALIDATION_STATUS_VALUES.includes(normalizedValue as ValidationStatus)
}

const SIGNER_STATUS_VALUES: readonly SignerDetailRecord['status'][] = [
	SIGN_REQUEST_STATUS.DRAFT,
	SIGN_REQUEST_STATUS.ABLE_TO_SIGN,
	SIGN_REQUEST_STATUS.SIGNED,
]

function isSignerStatus(value: unknown): value is SignerDetailRecord['status'] {
	const normalizedValue = toNumber(value)
	return normalizedValue !== null && SIGNER_STATUS_VALUES.includes(normalizedValue as SignerDetailRecord['status'])
}

function isString(value: unknown): value is string {
	return typeof value === 'string'
}

function isNullableString(value: unknown): value is string | null {
	return value === null || typeof value === 'string'
}

function isValidationStatusInfo(value: unknown): value is ValidationStatusInfo {
	if (!isRecord(value)) {
		return false
	}

	return isOptionalField(value, 'id', fieldValue => typeof fieldValue === 'number')
		&& isOptionalField(value, 'label', isString)
}

function isValidationModificationInfo(value: unknown): value is ValidationModificationInfo {
	if (!isRecord(value)) {
		return false
	}

	return isOptionalField(value, 'status', fieldValue => typeof fieldValue === 'number')
		&& isOptionalField(value, 'valid', fieldValue => typeof fieldValue === 'boolean')
}

function isValidationMetadataDimension(value: unknown): value is ValidationMetadataDimension {
	if (!isRecord(value)) {
		return false
	}

	return typeof value.w === 'number' && Number.isFinite(value.w)
		&& typeof value.h === 'number' && Number.isFinite(value.h)
}

function isRequestedBy(value: unknown): value is ValidationFileRecord['requested_by'] {
	if (!isRecord(value)) {
		return false
	}
	return isString(value.userId) && isString(value.displayName)
}

function isValidationMetadata(value: unknown): value is NonNullable<ValidationFileRecord['metadata']> {
	if (!isRecord(value)) {
		return false
	}

	if (!isString(value.extension) || typeof value.p !== 'number') {
		return false
	}

	return isOptionalField(value, 'd', fieldValue => Array.isArray(fieldValue) && fieldValue.every(isValidationMetadataDimension))
		&& isOptionalField(value, 'original_file_deleted', fieldValue => typeof fieldValue === 'boolean')
		&& isOptionalField(value, 'pdfVersion', isString)
		&& isOptionalField(value, 'status_changed_at', isString)
}

function isValidationSettings(value: unknown): value is NonNullable<ValidationFileRecord['settings']> {
	if (!isRecord(value)) {
		return false
	}
	return typeof value.canSign === 'boolean'
		&& typeof value.canRequestSign === 'boolean'
		&& typeof value.phoneNumber === 'string'
		&& typeof value.hasSignatureFile === 'boolean'
		&& typeof value.needIdentificationDocuments === 'boolean'
		&& typeof value.identificationDocumentsWaitingApproval === 'boolean'
		&& isOptionalField(value, 'isApprover', fieldValue => typeof fieldValue === 'boolean')
}

function isSignerDetailRecord(value: unknown): value is SignerDetailRecord {
	if (!isRecord(value)) {
		return false
	}

	return typeof value.signRequestId === 'number'
		&& isString(value.displayName)
		&& isString(value.email)
		&& isNullableString(value.signed)
		&& isSignerStatus(value.status)
		&& isString(value.statusText)
		&& isNullableString(value.description)
		&& isString(value.request_sign_date)
		&& typeof value.me === 'boolean'
		&& Array.isArray(value.visibleElements)
		&& isOptionalField(value, 'signature_validation', isValidationStatusInfo)
		&& isOptionalField(value, 'certificate_validation', isValidationStatusInfo)
		&& isOptionalField(value, 'modification_validation', isValidationModificationInfo)
		&& isOptionalField(value, 'crl_validation', isString)
		&& isOptionalField(value, 'isLibreSignRootCA', fieldValue => typeof fieldValue === 'boolean')
}

function isValidatedChildFileRecord(value: unknown): value is ValidatedChildFileRecord {
	if (!isRecord(value)) {
		return false
	}

	return typeof value.id === 'number'
		&& isString(value.uuid)
		&& isString(value.name)
		&& isValidationStatus(value.status)
		&& isString(value.statusText)
		&& typeof value.nodeId === 'number'
		&& typeof value.size === 'number'
		&& Array.isArray(value.signers)
		&& isString(value.file)
		&& isValidationMetadata(value.metadata)
}

function isValidationDocumentRecord(data: unknown): data is ValidationFileRecord {
	if (!isRecord(data)) {
		return false
	}
	if (
		typeof data.id !== 'number'
		|| !isString(data.uuid)
		|| !isString(data.name)
		|| !isValidationStatus(data.status)
		|| !isString(data.statusText)
		|| typeof data.nodeId !== 'number'
		|| (data.nodeType !== 'file' && data.nodeType !== 'envelope')
		|| typeof data.signatureFlow !== 'number'
		|| typeof data.docmdpLevel !== 'number'
		|| typeof data.filesCount !== 'number'
		|| !Array.isArray(data.files)
		|| typeof data.totalPages !== 'number'
		|| typeof data.size !== 'number'
		|| !isString(data.pdfVersion)
		|| !isString(data.created_at)
		|| !isRequestedBy(data.requested_by)
	) {
		return false
	}

	if (!data.files.every(isValidatedChildFileRecord)) {
		return false
	}

	if (hasOwn(data, 'signers') && (!Array.isArray(data.signers) || !data.signers.every(isSignerDetailRecord))) {
		return false
	}

	if (hasOwn(data, 'metadata') && !isValidationMetadata(data.metadata)) {
		return false
	}

	if (hasOwn(data, 'settings') && !isValidationSettings(data.settings)) {
		return false
	}

	return true
}

function toValidationDocument(data: unknown): ValidationFileRecord | null {
	return isValidationDocumentRecord(data) ? data : null
}

function getValidationErrorMessage(response: ValidationErrorResponse | undefined, fallback: string): string {
	if (response?.data?.ocs?.data?.errors?.length) {
		return response.data.ocs.data.errors[0]?.message || fallback
	}
	return fallback
}

function isLoadedValidationEnvelopeDocument(document: ValidationFileRecord | null): document is LoadedValidationEnvelopeDocument {
	return document?.nodeType === 'envelope'
}

function isLoadedValidationFileDocument(document: ValidationFileRecord | null): document is LoadedValidationFileDocument {
	return document?.nodeType === 'file'
}

const signStore = useSignStore()
const sidebarStore = useSidebarStore()
const filesStore = useFilesStore()
const instance = getCurrentInstance()
const EXPIRATION_WARNING_DAYS = 30

const route = computed<RouteState>(() => {
	const rawRoute = (instance?.proxy?.$route as Partial<RouteState> | undefined) ?? {}
	return {
		name: typeof rawRoute.name === 'string' ? rawRoute.name : null,
		params: normalizeRouteRecord(rawRoute.params, 'params'),
		query: normalizeRouteRecord(rawRoute.query, 'query'),
	}
})
const router = computed<RouterState>(() => (instance?.proxy?.$router as RouterState | undefined) ?? { push: () => {}, replace: () => {} })

const logo = ref(logoGray)
const uuidToValidate = ref(route.value.params.uuid ?? '')
const hasInfo = ref(false)
const loading = ref(false)
const document = ref<ValidationFileRecord | null>(null)
const legalInformation = ref(loadState('libresign', 'legal_information', ''))
const clickedValidate = ref(false)
const getUUID = ref(false)
const validationStatusOpenState = ref<ToggleOpenState>({})
const extensionsOpenState = ref<ToggleOpenState>({})
const tsaOpenState = ref<ToggleOpenState>({})
const chainOpenState = ref<ToggleOpenState>({})
const notificationsOpenState = ref<ToggleOpenState>({})
const docMdpOpenState = ref<ToggleOpenState>({})
const validationErrorMessage = ref<string | null>(null)
const documentValidMessage = ref<string | null>(null)
const isAsyncSigning = ref(false)
const shouldFireAsyncConfetti = ref(false)
const isActiveView = ref(true)

const signRequestUuidForProgress = computed(() => {
	const doc = signStore?.document || {}
	const fromState = loadState('libresign', 'sign_request_uuid', null)
	const fromDocument = getSigningRouteUuid(doc, typeof fromState === 'string' ? fromState : null)
	return route.value.query.signRequestUuid
		|| route.value.params.signRequestUuid
		|| fromDocument
		|| uuidToValidate.value
})

const isAfterSigned = computed(() => history.state?.isAfterSigned ?? shouldFireAsyncConfetti.value ?? false)

const isEnvelope = computed(() => document.value?.nodeType === 'envelope'
	|| (Array.isArray(document.value?.files) && document.value.files.length > 0))
const validationComponent = computed(() => (isEnvelope.value ? EnvelopeValidation : FileValidation))
const validationDocument = computed(() => document.value)
const validationEnvelopeDocument = computed<LoadedValidationEnvelopeDocument | null>(() => (isLoadedValidationEnvelopeDocument(document.value) ? document.value : null))
const validationFileDocument = computed<LoadedValidationFileDocument | null>(() => (isLoadedValidationFileDocument(document.value) ? document.value : null))

const canValidate = computed(() => {
	if (!uuidToValidate.value) {
		return false
	}
	const isNumericId = /^\d+$/.test(uuidToValidate.value)
	const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
	return isNumericId || (uuidToValidate.value.length === 36 && uuidRegex.test(uuidToValidate.value))
})

const helperTextValidation = computed(() => {
	if (uuidToValidate.value && uuidToValidate.value.length > 0 && !canValidate.value) {
		return t('libresign', 'Invalid UUID')
	}
	return ''
})

const size = computed(() => formatFileSize(document.value?.size ?? 0))
const documentStatus = computed(() => getStatusLabel(document.value?.status))

const validityStatusMap = computed(() => ({
	unknown: { text: t('libresign', 'Unknown validity'), variant: 'tertiary', icon: mdiHelpCircle },
	expired: { text: t('libresign', 'Expired'), variant: 'error', icon: mdiCancel },
	expiring: { text: t('libresign', 'Expiring soon'), variant: 'warning', icon: mdiAlertCircleOutline },
	valid: { text: t('libresign', 'Currently valid'), variant: 'success', icon: mdiCheckCircle },
}))

const crlStatusMap = computed<Record<string, StatusPresentation>>(() => ({
	valid: { text: t('libresign', 'Not revoked'), variant: 'success', icon: mdiCheckCircle },
	revoked: { text: t('libresign', 'Certificate revoked'), variant: 'error', icon: mdiCancel },
	missing: { text: t('libresign', 'No CRL information'), variant: 'warning', icon: mdiAlertCircle },
	no_urls: { text: t('libresign', 'No CRL URLs found'), variant: 'warning', icon: mdiAlertCircle },
	urls_inaccessible: { text: t('libresign', 'CRL URLs inaccessible'), variant: 'tertiary', icon: mdiHelpCircle },
	validation_failed: { text: t('libresign', 'CRL validation failed'), variant: 'tertiary', icon: mdiHelpCircle },
	validation_error: { text: t('libresign', 'CRL validation error'), variant: 'tertiary', icon: mdiHelpCircle },
}))

async function upload(file: File) {
	const formData = new FormData()
	formData.append('file', file)
	await axios.postForm(generateOcsUrl('/apps/libresign/api/v1/file/validate'), formData, {
		headers: {
			'Content-Type': 'multipart/form-data',
		},
	})
		.then(({ data }) => {
			clickedValidate.value = true
			handleValidationSuccess(data.ocs.data)
		})
		.catch((error: { response?: ValidationErrorResponse }) => {
			const errorMsg = getValidationErrorMessage(error.response, t('libresign', 'Failed to validate document'))
			setValidationError(errorMsg)
		})
}

async function uploadFile() {
	loading.value = true
	const input = window.document.createElement('input')
	input.accept = 'application/pdf'
	input.type = 'file'

	input.onchange = async (ev) => {
		const target = ev.target as HTMLInputElement | null
		const file = target?.files?.[0]

		if (file) {
			await upload(file)
		}

		loading.value = false
		input.remove()
	}

	input.click()
}

function dateFromSqlAnsi(date: string) {
	return Moment(Date.parse(date)).format('LL LTS')
}

function toggleDetail(_signer: SignerDetailRecord) {
}

function toggleFileDetail(_file: ValidatedChildFileRecord) {
}

function getSignerStatus(status: string) {
	const statusMap: Record<string, string> = {
		pending: t('libresign', 'Pending'),
		partial: t('libresign', 'Partial'),
		complete: t('libresign', 'Complete'),
	}
	return statusMap[status] || status
}

async function validate(id: string, { suppressLoading = false, forceRefresh = false }: { suppressLoading?: boolean; forceRefresh?: boolean } = {}) {
	validationErrorMessage.value = null
	documentValidMessage.value = null
	if (id === document.value?.uuid && !forceRefresh) {
		documentValidMessage.value = t('libresign', 'This document is valid')
		hasInfo.value = true
	} else if (id.length === 36) {
		await validateByUUID(id, { suppressLoading })
	} else {
		await validateByNodeID(id, { suppressLoading })
	}
	getUUID.value = false
}

async function validateByUUID(uuid: string, { suppressLoading = false }: { suppressLoading?: boolean } = {}) {
	if (!suppressLoading) {
		loading.value = true
	}
	const cacheBuster = suppressLoading ? `?_t=${Date.now()}` : ''
	await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}${cacheBuster}`))
		.then(({ data }) => {
			handleValidationSuccess(data.ocs.data)
		})
		.catch((error: { response?: ValidationErrorResponse }) => {
			const response = error.response
			if (response?.status === 404) {
				setValidationError(t('libresign', 'Document not found'))
			} else {
				setValidationError(getValidationErrorMessage(response, t('libresign', 'Failed to validate document')))
			}
		})
	if (!suppressLoading) {
		loading.value = false
	}
}

async function validateByNodeID(nodeId: string, { suppressLoading = false }: { suppressLoading?: boolean } = {}) {
	if (!suppressLoading) {
		loading.value = true
	}
	const cacheBuster = suppressLoading ? `?_t=${Date.now()}` : ''
	await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}${cacheBuster}`))
		.then(({ data }) => {
			handleValidationSuccess(data.ocs.data)
		})
		.catch((error: { response?: ValidationErrorResponse }) => {
			const response = error.response
			if (response?.status === 404) {
				setValidationError(t('libresign', 'Document not found'))
			} else {
				setValidationError(getValidationErrorMessage(response, t('libresign', 'Failed to validate document')))
			}
		})
	if (!suppressLoading) {
		loading.value = false
	}
}

function getName(signer: ValidationDisplaySigner) {
	return signer.displayName || signer.email || signer.signature_validation?.label || t('libresign', 'Unknown')
}

function getIconValidityPath(signer: ValidationDisplaySigner) {
	if (signer.signature_validation?.id === 1) {
		return mdiCheckboxMarkedCircle
	}
	return mdiAlertCircle
}

async function viewDocument() {
	if (!document.value?.uuid || !document.value?.name || typeof document.value.nodeId !== 'number') {
		return
	}
	const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: document.value.uuid })
	await openDocument({
		fileUrl,
		filename: document.value.name,
		nodeId: document.value.nodeId,
	})
}

function goBack() {
	const urlParams = new URLSearchParams(window.location.search)
	if (urlParams.has('path')) {
		try {
			const redirectPath = window.atob(urlParams.get('path') ?? '')
			if (redirectPath && redirectPath.startsWith('/apps')) {
				window.location.href = generateUrl(redirectPath)
				return
			}
		} catch (error) {
			logger.error('Failed going back', { error })
		}
	}
	hasInfo.value = false
	document.value = null
	uuidToValidate.value = route.value.params.uuid ?? ''
	validationErrorMessage.value = null
	documentValidMessage.value = null
}

function getValidityStatus(signer: ValidationDisplaySigner) {
	if (!signer.valid_to) {
		return 'unknown'
	}

	const now = new Date()
	const expirationDate = new Date(signer.valid_to)

	if (expirationDate <= now) {
		return 'expired'
	}

	const warningDate = new Date()
	warningDate.setDate(now.getDate() + EXPIRATION_WARNING_DAYS)

	if (expirationDate <= warningDate) {
		return 'expiring'
	}

	return 'valid'
}

function getValidityStatusAtSigning(signer: ValidationDisplaySigner) {
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
}

function getSignatureValidationMessage(signer: ValidationDisplaySigner) {
	if (!signer.signature_validation) {
		return t('libresign', 'Signature: Unknown')
	}
	if (signer.signature_validation.id === 1) {
		return t('libresign', 'Document integrity verified')
	}
	return t('libresign', 'Signature: {validationStatus}', { validationStatus: signer.signature_validation.label ?? t('libresign', 'Unknown') })
}

function getCertificateTrustMessage(signer: ValidationDisplaySigner) {
	if (!signer.certificate_validation) {
		return t('libresign', 'Trust Chain: Unknown')
	}

	if (signer.certificate_validation.id === 1) {
		if (signer.isLibreSignRootCA) {
			return t('libresign', 'Trust Chain: Trusted (LibreSign CA)')
		}
		return t('libresign', 'Trust Chain: Trusted')
	}

	return t('libresign', 'Trust chain: {validationStatus}', { validationStatus: signer.certificate_validation.label ?? t('libresign', 'Unknown') })
}

function getCrlValidationIconClass(signer: ValidationDisplaySigner) {
	if (!signer.crl_validation) {
		return 'icon-default'
	}
	const variant = crlStatusMap.value[signer.crl_validation]?.variant
	if (variant === 'success') return 'icon-success'
	if (variant === 'error') return 'icon-error'
	if (variant === 'warning') return 'icon-warning'
	return 'icon-default'
}

function camelCaseToTitleCase(text: string) {
	if (text.includes(' ')) {
		return text.replace(/^./, str => str.toUpperCase())
	}

	return text
		.replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
		.replace(/([a-z])([A-Z])/g, '$1 $2')
		.replace(/^./, str => str.toUpperCase())
		.trim()
}

function hasValidationIssues(signer: ValidationDisplaySigner) {
	if (signer.signature_validation && signer.signature_validation.id !== 1) {
		return true
	}
	if (signer.certificate_validation && signer.certificate_validation.id !== 1) {
		return true
	}
	if (signer.crl_validation === 'revoked') {
		return true
	}
	if (signer.valid_from && signer.valid_to && signer.signed && getValidityStatusAtSigning(signer) !== 'valid') {
		return true
	}
	const currentStatus = getValidityStatus(signer)
	if (currentStatus === 'expired' || currentStatus === 'expiring') {
		return true
	}
	return false
}

function hasDocMdpInfo(signer: ValidationDisplaySigner) {
	return signer.docmdp || signer.modifications || signer.modification_validation
}

function getModificationStatusIcon(signer: ValidationDisplaySigner) {
	if (!signer.modification_validation) {
		return null
	}
	const status = signer.modification_validation.status
	const valid = signer.modification_validation.valid

	if (valid && status === 2) return mdiCheckCircle
	if (status === 1) return mdiCheckCircle
	if (status === 3) return mdiCancel
	return mdiHelpCircle
}

function getModificationStatusClass(signer: ValidationDisplaySigner) {
	if (!signer.modification_validation) {
		return ''
	}
	const status = signer.modification_validation.status
	const valid = signer.modification_validation.valid

	if (valid && status === 2) return 'icon-success'
	if (status === 1) return 'icon-success'
	if (status === 3) return 'icon-error'
	return ''
}

function formatTimestamp(timestamp: number | null | undefined) {
	return timestamp ? new Date(timestamp * 1000).toLocaleString() : ''
}

function validateAndProceed() {
	clickedValidate.value = true
	validate(uuidToValidate.value)
}

function toggleState(stateObject: ToggleOpenState, index: number) {
	stateObject[index] = !stateObject[index]
}

function hasValidationStatus(signer: ValidationDisplaySigner) {
	return signer.signature_validation
		|| signer.certificate_validation
		|| (signer.valid_from && signer.valid_to && signer.signed)
		|| signer.crl_validation
}

function setValidationError(message: string, timeout = 5000) {
	validationErrorMessage.value = message
	if (timeout > 0) {
		setTimeout(() => {
			validationErrorMessage.value = null
		}, timeout)
	}
}

function getTrackedFileId(file: ValidationFileRecord | ValidatedChildFileRecord): number | null {
	const fileId = toNumber(file.id)
	if (fileId !== null && Object.hasOwn(filesStore.files, fileId)) {
		return fileId
	}

	if (typeof file.uuid === 'string' && file.uuid.length > 0) {
		const trackedByUuid = toNumber(filesStore.getFileIdByUuid(file.uuid))
		if (trackedByUuid !== null) {
			return trackedByUuid
		}
	}

	const nodeId = toNumber(file.nodeId)
	if (nodeId !== null) {
		const trackedByNodeId = toNumber(filesStore.getFileIdByNodeId(nodeId))
		if (trackedByNodeId !== null) {
			return trackedByNodeId
		}
	}

	return null
}

function syncValidatedDocumentToFilesStore(validationDocument: ValidationFileRecord) {
	const pendingFiles: Array<ValidationFileRecord | ValidatedChildFileRecord> = [validationDocument]

	while (pendingFiles.length > 0) {
		const currentFile = pendingFiles.shift()
		if (!currentFile) {
			continue
		}

		const trackedFileId = getTrackedFileId(currentFile)
		if (trackedFileId !== null) {
			const storeFilePayload = {
				...currentFile,
				id: trackedFileId,
			} as Parameters<typeof filesStore.addFile>[0]
			void filesStore.addFile(storeFilePayload, { detailsLoaded: true })
		}

		const nestedFiles = 'files' in currentFile && Array.isArray(currentFile.files)
			? currentFile.files
			: []
		if (nestedFiles.length > 0) {
			pendingFiles.push(...nestedFiles.filter((file): file is ValidatedChildFileRecord => isRecord(file)))
		}
	}
}

function openUuidDialog() {
	validationErrorMessage.value = null
	getUUID.value = true
}

function handleValidationSuccess(data: unknown) {
	if (!isActiveView.value) {
		return
	}
	documentValidMessage.value = t('libresign', 'This document is valid')
	const normalizedDocument = toValidationDocument(data)
	if (!normalizedDocument) {
		setValidationError(t('libresign', 'Failed to validate document'))
		return
	}
	const routeName = route.value?.name || ''
	const shouldUpdateRoute = routeName === 'validation'
		|| routeName === 'ValidationFile'
		|| routeName === 'ValidationFileExternal'
		|| routeName === 'ValidationFileShortUrl'
	if (shouldUpdateRoute && route.value.params.uuid !== normalizedDocument.uuid) {
		router.value.replace({
			name: route.value.name,
			params: {
				...route.value.params,
				uuid: normalizedDocument.uuid,
			},
			query: route.value.query,
		})
	}
	document.value = normalizedDocument
	hasInfo.value = true
	const isSignedStatus = (status: unknown) => Number(status) === FILE_STATUS.SIGNED
	const isSignedDoc = isSignedStatus(document.value?.status)
	const allFilesSigned = Array.isArray(document.value?.files)
		&& document.value.files.length > 0
		&& document.value.files.every(file => isSignedStatus(file.status))
	const signerCompleted = isCurrentSignerSigned()
	if (isSignedDoc || allFilesSigned || signerCompleted) {
		syncValidatedDocumentToFilesStore(normalizedDocument)
	}
	if ((isSignedDoc || allFilesSigned || signerCompleted) && (isAfterSigned.value || shouldFireAsyncConfetti.value)) {
		const capabilities = getCapabilities() as { libresign?: { config?: Record<string, unknown> } } | undefined
		if (capabilities?.libresign?.config?.['show-confetti'] === true) {
			const jsConfetti = new JSConfetti()
			jsConfetti.addConfetti()
		}
		shouldFireAsyncConfetti.value = false
	}
}

async function refreshAfterAsyncSigning() {
	const maxAttempts = 8
	for (let attempt = 1; attempt <= maxAttempts; attempt++) {
		if (!isActiveView.value) {
			return
		}
		await validate(uuidToValidate.value, { suppressLoading: true, forceRefresh: true })

		if (isCurrentSignerSigned()) {
			return
		}

		const isSignedStatus = (status: unknown) => Number(status) === FILE_STATUS.SIGNED
		const isSigned = isSignedStatus(document.value?.status)
		const allFilesSigned = Array.isArray(document.value?.files)
			&& document.value.files.length > 0
			&& document.value.files.every(file => isSignedStatus(file.status))

		if (isSigned || allFilesSigned) {
			return
		}

		await new Promise(resolve => setTimeout(resolve, 900))
	}
}

function handleSigningComplete(file?: unknown) {
	if (!isActiveView.value) {
		return
	}
	isAsyncSigning.value = false
	shouldFireAsyncConfetti.value = true
	const normalizedFile = toValidationDocument(file)
	if (normalizedFile) {
		loading.value = false
		handleValidationSuccess(normalizedFile)
		return
	}
	loading.value = true
	refreshAfterAsyncSigning()
		.finally(() => {
			loading.value = false
		})
}

function handleSigningError(message?: string) {
	loading.value = false
	const errorMessage = message || t('libresign', 'Signing failed. Please try again.')
	setValidationError(errorMessage)
}

function isCurrentSignerSigned() {
	const signer = document.value?.signers?.find(row => row.me)
	return !!signer?.signed || Number(signer?.status) === SIGN_REQUEST_STATUS.SIGNED
}

watch(isAsyncSigning, (active) => {
	if (active) {
		sidebarStore.hideSidebar()
	}
})

watch(() => route.value.params?.uuid, (uuid) => {
	if (uuid) {
		validate(uuid)
	}
})

document.value = toValidationDocument(loadState('libresign', 'file_info', {}))

if (!uuidToValidate.value) {
	document.value = null
	hasInfo.value = false
} else {
	hasInfo.value = !!document.value?.name

	if (uuidToValidate.value !== document.value?.uuid) {
		document.value = null
		hasInfo.value = false
		void validate(uuidToValidate.value)
	} else if (uuidToValidate.value.length > 0) {
		void validate(uuidToValidate.value)
	}
}

if (history.state?.isAsync === true) {
	isAsyncSigning.value = true
	shouldFireAsyncConfetti.value = true
	loading.value = true
}

onBeforeUnmount(() => {
	isActiveView.value = false
})

defineExpose({
	t,
	signStore,
	sidebarStore,
	mdiAlertCircle,
	mdiAlertCircleOutline,
	mdiArrowLeft,
	mdiCancel,
	mdiCheckCircle,
	mdiCheckboxMarkedCircle,
	mdiHelpCircle,
	mdiKey,
	mdiUpload,
	logo,
	uuidToValidate,
	hasInfo,
	loading,
	document,
	validationDocument,
	legalInformation,
	clickedValidate,
	getUUID,
	EXPIRATION_WARNING_DAYS,
	validationStatusOpenState,
	extensionsOpenState,
	tsaOpenState,
	chainOpenState,
	notificationsOpenState,
	docMdpOpenState,
	validationErrorMessage,
	documentValidMessage,
	isAsyncSigning,
	shouldFireAsyncConfetti,
	isActiveView,
	signRequestUuidForProgress,
	isAfterSigned,
	isEnvelope,
	validationComponent,
	canValidate,
	helperTextValidation,
	size,
	documentStatus,
	validationEnvelopeDocument,
	validationFileDocument,
	validityStatusMap,
	crlStatusMap,
	upload,
	uploadFile,
	dateFromSqlAnsi,
	toggleDetail,
	toggleFileDetail,
	getSignerStatus,
	validate,
	validateByUUID,
	validateByNodeID,
	getName,
	getIconValidityPath,
	viewDocument,
	goBack,
	getValidityStatus,
	getValidityStatusAtSigning,
	getSignatureValidationMessage,
	getCertificateTrustMessage,
	getCrlValidationIconClass,
	camelCaseToTitleCase,
	hasValidationIssues,
	hasDocMdpInfo,
	getModificationStatusIcon,
	getModificationStatusClass,
	formatTimestamp,
	validateAndProceed,
	toggleState,
	hasValidationStatus,
	setValidationError,
	openUuidDialog,
	handleValidationSuccess,
	refreshAfterAsyncSigning,
	handleSigningComplete,
	handleSigningError,
	isCurrentSignerSigned,
})
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	flex-direction: row;
	align-items: stretch;
	justify-content: center;
	overflow-y: auto;
	width: 100%;
	height: 100%;

	@media screen and (max-width: 1400px) {
		flex-direction: column;
		justify-content: flex-start;
	}

	.logo {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		position: sticky;
		top: 0;
		height: 100vh;
		img {
			width: 50%;
			max-width: 422px;
		}
		@media screen and (max-width: 1400px) {
			position: static;
			height: auto;
			padding: 20px 0;
			img {
				width: 60%;
				max-width: 300px;
			}
		}
	}
	#validation-content {
		width: 100%;
		display: flex;
		justify-content: center;
		padding: 20px;
		@media screen and (max-width: 700px) {
			padding: 0;
		}
		h1 {
			font-size: 24px;
			font-weight: bold;
			color: var(--color-main-text);
		}
		form {
			background-color: var(--color-main-background);
			color: var(--color-main-text);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 20px;
			margin: 20px;
			border-radius: 8px;
			max-width: 500px;
			width: 100%;
			box-shadow: 0 0 6px 0 var(--color-box-shadow);

			@media screen and (max-width: 700px) {
				width: 100%;
				display: flex;
				justify-content: center;
				align-items: center;
				max-width: 100%;
				padding: 12px;
				margin: 12px;
				box-shadow: none;
			}
		}
		button {
			float: inline-end;
			align-self: flex-end;
		}
		.infor-container {
			width: 100%;
			margin: auto 0;
			.section {
				background-color: var(--color-main-background);
				padding: 20px;
				border-radius: 8px;
				box-shadow: 0 0 6px 0 var(--color-box-shadow);
				margin-bottom: 10px;
				width: unset;
				overflow: hidden;
				@media screen and (max-width: 700px) {
					max-width: 100%;
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
				.action-items {
					gap: 12px;
					flex-direction: column;
				}

				.header {
					display: flex;
					margin-bottom: 2rem;
			}

				h1 {
					font-size: 1.5rem;
				}

				.extra,
				.extra-chain {
					:deep(.list-item-content__name) {
						white-space: unset;
						display: flex;
						align-items: center;
						gap: 8px;

						.nc-chip {
							display: inline-flex;
						}
					}
					:deep(.list-item__anchor) {
						height: unset;
					}
				}

				.info-document {
					color: var(--color-main-text);
					display: flex;
					flex-direction: column;
					overflow: auto;
					.legal-information {
						opacity: 0.8;
						align-self: center;
						font-size: 1rem;
						overflow: auto;
					}

					p {
						font-size: 1rem;
					}
				}

				.signers {
					:deep(.list-item__wrapper) {
						box-sizing: border-box;
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
					.extra {
						margin-inline-start: 44px;
						padding-inline-end: 44px;
					}
					.extra-chain {
						margin-inline-start: 88px;
						padding-inline-end: 88px;
					}
					.validation-chips {
						display: flex;
						flex-direction: column;
						gap: 8px;
						margin: 8px 0 8px 64px;
					}
					.icon-success {
						color: green;
					}
					.icon-error {
						color: red;
					}
					.icon-warning {
						color: orange;
					}
					.icon-info {
						color: var(--color-primary-element);
					}
					.icon-default {
						color: gray;
					}
					.extension-value {
						white-space: pre-wrap;
						overflow-wrap: break-word;
					}
					.cert-details {
						display: flex;
						flex-direction: column;
						gap: 8px;
					}
					.cert-issuer {
						font-size: 0.9em;
						opacity: 0.8;
					}
					.serial-hex {
						opacity: 0.7;
					}
				}
			}
		}
	}
}

@media screen and (max-width: 700px) {
	.container {
		align-items: flex-start;
		h1 {
			font-size: 1.3rem;
		}
		.infor-container {
			margin-inline-end: 0;
			.section {
				width: unset;

				.signers {
					.date-signed-desktop {
						display: none;
					}
					.extra {
						margin-inline-start: 8px !important;
						padding-inline-end: 8px !important;
					}
					.extra-chain {
						margin-inline-start: 16px !important;
						padding-inline-end: 8px !important;
					}
				}
			}
		}
	}
	.validation-chips {
		margin: 8px 0 8px 32px !important;
	}
}
</style>
