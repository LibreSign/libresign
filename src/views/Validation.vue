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
			<div v-else class="infor-container">
				<component :is="validationComponent"
					:document="document"
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

<script setup>
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
import { computed, defineAsyncComponent, getCurrentInstance, onBeforeUnmount, ref, watch } from 'vue'

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

const EnvelopeValidation = defineAsyncComponent(() => import('../components/validation/EnvelopeValidation.vue'))
const FileValidation = defineAsyncComponent(() => import('../components/validation/FileValidation.vue'))
const SigningProgress = defineAsyncComponent(() => import('../components/validation/SigningProgress.vue'))

import logoGray from '../../img/logo-gray.svg'
import { openDocument } from '../utils/viewer.js'
import { getStatusLabel } from '../utils/fileStatus.js'
import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../constants.js'
import logger from '../logger.js'
import { useSignStore } from '../store/sign.js'
import { useSidebarStore } from '../store/sidebar.js'

defineOptions({
	name: 'Validation',
})

const signStore = useSignStore()
const sidebarStore = useSidebarStore()
const instance = getCurrentInstance()
const EXPIRATION_WARNING_DAYS = 30

const route = computed(() => instance?.proxy?.$route ?? { params: {}, query: {} })
const router = computed(() => instance?.proxy?.$router ?? { push: () => {}, replace: () => {} })

const logo = ref(logoGray)
const uuidToValidate = ref(route.value.params?.uuid ?? '')
const hasInfo = ref(false)
const loading = ref(false)
const document = ref({})
const legalInformation = ref(loadState('libresign', 'legal_information', ''))
const clickedValidate = ref(false)
const getUUID = ref(false)
const validationStatusOpenState = ref({})
const extensionsOpenState = ref({})
const tsaOpenState = ref({})
const chainOpenState = ref({})
const notificationsOpenState = ref({})
const docMdpOpenState = ref({})
const validationErrorMessage = ref(null)
const documentValidMessage = ref(null)
const isAsyncSigning = ref(false)
const shouldFireAsyncConfetti = ref(false)
const isActiveView = ref(true)

const signRequestUuidForProgress = computed(() => {
	const doc = signStore?.document || {}
	const signer = doc.signers?.find(row => row.me) || doc.signers?.[0] || {}
	const fromDoc = doc.signRequestUuid || doc.sign_request_uuid || doc.signUuid || doc.sign_uuid
	const fromSigner = signer.sign_uuid
	return route.value.query?.signRequestUuid
		|| route.value.params?.signRequestUuid
		|| fromDoc
		|| fromSigner
		|| loadState('libresign', 'sign_request_uuid', null)
		|| uuidToValidate.value
})

const isAfterSigned = computed(() => history.state?.isAfterSigned ?? shouldFireAsyncConfetti.value ?? false)

const isEnvelope = computed(() => document.value?.nodeType === 'envelope'
	|| (Array.isArray(document.value?.files) && document.value.files.length > 0))

const validationComponent = computed(() => (isEnvelope.value ? EnvelopeValidation : FileValidation))

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

const size = computed(() => formatFileSize(document.value.size))
const documentStatus = computed(() => getStatusLabel(document.value.status))

const validityStatusMap = computed(() => ({
	unknown: { text: t('libresign', 'Unknown validity'), variant: 'tertiary', icon: mdiHelpCircle },
	expired: { text: t('libresign', 'Expired'), variant: 'error', icon: mdiCancel },
	expiring: { text: t('libresign', 'Expiring soon'), variant: 'warning', icon: mdiAlertCircleOutline },
	valid: { text: t('libresign', 'Currently valid'), variant: 'success', icon: mdiCheckCircle },
}))

const crlStatusMap = computed(() => ({
	valid: { text: t('libresign', 'Not revoked'), variant: 'success', icon: mdiCheckCircle },
	revoked: { text: t('libresign', 'Certificate revoked'), variant: 'error', icon: mdiCancel },
	missing: { text: t('libresign', 'No CRL information'), variant: 'warning', icon: mdiAlertCircle },
	no_urls: { text: t('libresign', 'No CRL URLs found'), variant: 'warning', icon: mdiAlertCircle },
	urls_inaccessible: { text: t('libresign', 'CRL URLs inaccessible'), variant: 'tertiary', icon: mdiHelpCircle },
	validation_failed: { text: t('libresign', 'CRL validation failed'), variant: 'tertiary', icon: mdiHelpCircle },
	validation_error: { text: t('libresign', 'CRL validation error'), variant: 'tertiary', icon: mdiHelpCircle },
}))

async function upload(file) {
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
		.catch(({ response }) => {
			const errorMsg = response?.data?.ocs?.data?.errors?.length > 0
				? response.data.ocs.data.errors[0].message
				: t('libresign', 'Failed to validate document')
			setValidationError(errorMsg)
		})
}

async function uploadFile() {
	loading.value = true
	const input = window.document.createElement('input')
	input.accept = 'application/pdf'
	input.type = 'file'

	input.onchange = async (ev) => {
		const file = ev.target.files[0]

		if (file) {
			await upload(file)
		}

		loading.value = false
		input.remove()
	}

	input.click()
}

function dateFromSqlAnsi(date) {
	return Moment(Date.parse(date)).format('LL LTS')
}

function toggleDetail(signer) {
	signer.opened = !signer.opened
}

function toggleFileDetail(file) {
	file.opened = !file.opened
}

function getSignerStatus(status) {
	const statusMap = {
		pending: t('libresign', 'Pending'),
		partial: t('libresign', 'Partial'),
		complete: t('libresign', 'Complete'),
	}
	return statusMap[status] || status
}

async function validate(id, { suppressLoading = false, forceRefresh = false } = {}) {
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

async function validateByUUID(uuid, { suppressLoading = false } = {}) {
	if (!suppressLoading) {
		loading.value = true
	}
	const cacheBuster = suppressLoading ? `?_t=${Date.now()}` : ''
	await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}${cacheBuster}`))
		.then(({ data }) => {
			handleValidationSuccess(data.ocs.data)
		})
		.catch(({ response }) => {
			if (response?.status === 404) {
				setValidationError(t('libresign', 'Document not found'))
			} else if (response?.data?.ocs?.data?.errors?.length > 0) {
				setValidationError(response.data.ocs.data.errors[0].message)
			} else {
				setValidationError(t('libresign', 'Failed to validate document'))
			}
		})
	if (!suppressLoading) {
		loading.value = false
	}
}

async function validateByNodeID(nodeId, { suppressLoading = false } = {}) {
	if (!suppressLoading) {
		loading.value = true
	}
	const cacheBuster = suppressLoading ? `?_t=${Date.now()}` : ''
	await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}${cacheBuster}`))
		.then(({ data }) => {
			handleValidationSuccess(data.ocs.data)
		})
		.catch(({ response }) => {
			if (response?.status === 404) {
				setValidationError(t('libresign', 'Document not found'))
			} else if (response?.data?.ocs?.data?.errors?.length > 0) {
				setValidationError(response.data.ocs.data.errors[0].message)
			} else {
				setValidationError(t('libresign', 'Failed to validate document'))
			}
		})
	if (!suppressLoading) {
		loading.value = false
	}
}

function getName(signer) {
	return signer.displayName || signer.email || signer.signature_validation?.label || t('libresign', 'Unknown')
}

function getIconValidityPath(signer) {
	if (signer.signature_validation?.id === 1) {
		return mdiCheckboxMarkedCircle
	}
	return mdiAlertCircle
}

async function viewDocument() {
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
			const redirectPath = window.atob(urlParams.get('path'))
			if (redirectPath && redirectPath.startsWith('/apps')) {
				window.location = generateUrl(redirectPath)
				return
			}
		} catch (error) {
			logger.error('Failed going back', { error })
		}
	}
	hasInfo.value = false
	uuidToValidate.value = route.value.params?.uuid ?? ''
	validationErrorMessage.value = null
	documentValidMessage.value = null
}

function getValidityStatus(signer) {
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

function getValidityStatusAtSigning(signer) {
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

function getSignatureValidationMessage(signer) {
	if (signer.signature_validation.id === 1) {
		return t('libresign', 'Document integrity verified')
	}
	return t('libresign', 'Signature: {validationStatus}', { validationStatus: signer.signature_validation.label })
}

function getCertificateTrustMessage(signer) {
	if (!signer.certificate_validation) {
		return t('libresign', 'Trust Chain: Unknown')
	}

	if (signer.certificate_validation.id === 1) {
		if (signer.isLibreSignRootCA) {
			return t('libresign', 'Trust Chain: Trusted (LibreSign CA)')
		}
		return t('libresign', 'Trust Chain: Trusted')
	}

	return t('libresign', 'Trust chain: {validationStatus}', { validationStatus: signer.certificate_validation.label })
}

function getCrlValidationIconClass(signer) {
	const variant = crlStatusMap.value[signer.crl_validation]?.variant
	if (variant === 'success') return 'icon-success'
	if (variant === 'error') return 'icon-error'
	if (variant === 'warning') return 'icon-warning'
	return 'icon-default'
}

function camelCaseToTitleCase(text) {
	if (text.includes(' ')) {
		return text.replace(/^./, str => str.toUpperCase())
	}

	return text
		.replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
		.replace(/([a-z])([A-Z])/g, '$1 $2')
		.replace(/^./, str => str.toUpperCase())
		.trim()
}

function hasValidationIssues(signer) {
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

function hasDocMdpInfo(signer) {
	return signer.docmdp || signer.modifications || signer.modification_validation
}

function getModificationStatusIcon(signer) {
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

function getModificationStatusClass(signer) {
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

function formatTimestamp(timestamp) {
	return timestamp ? new Date(timestamp * 1000).toLocaleString() : ''
}

function validateAndProceed() {
	clickedValidate.value = true
	validate(uuidToValidate.value)
}

function toggleState(stateObject, index) {
	stateObject[index] = !stateObject[index]
}

function hasValidationStatus(signer) {
	return signer.signature_validation
		|| signer.certificate_validation
		|| (signer.valid_from && signer.valid_to && signer.signed)
		|| signer.crl_validation
}

function setValidationError(message, timeout = 5000) {
	validationErrorMessage.value = message
	if (timeout > 0) {
		setTimeout(() => {
			validationErrorMessage.value = null
		}, timeout)
	}
}

function openUuidDialog() {
	validationErrorMessage.value = null
	getUUID.value = true
}

function handleValidationSuccess(data) {
	if (!isActiveView.value) {
		return
	}
	documentValidMessage.value = t('libresign', 'This document is valid')
	if (!data?.nodeType && Array.isArray(data?.files) && data.files.length > 0) {
		data.nodeType = 'envelope'
	}
	const routeName = route.value?.name || ''
	const shouldUpdateRoute = routeName === 'validation'
		|| routeName === 'ValidationFile'
		|| routeName === 'ValidationFileExternal'
		|| routeName === 'ValidationFileShortUrl'
	if (shouldUpdateRoute && data?.uuid && route.value.params?.uuid !== data.uuid) {
		router.value.replace({
			name: route.value.name,
			params: {
				...route.value.params,
				uuid: data.uuid,
			},
			query: route.value.query,
		})
	}
	document.value = data
	document.value.signers?.forEach(signer => {
		signer.opened = false
	})
	document.value.files?.forEach(file => {
		file.opened = false
	})
	hasInfo.value = true
	const isSignedStatus = status => Number(status) === FILE_STATUS.SIGNED
	const isSignedDoc = isSignedStatus(document.value?.status)
	const allFilesSigned = Array.isArray(document.value?.files)
		&& document.value.files.length > 0
		&& document.value.files.every(file => isSignedStatus(file.status))
	const signerCompleted = isCurrentSignerSigned()
	if ((isSignedDoc || allFilesSigned || signerCompleted) && (isAfterSigned.value || shouldFireAsyncConfetti.value)) {
		if (getCapabilities()?.libresign?.config?.['show-confetti'] === true) {
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

		const isSignedStatus = status => Number(status) === FILE_STATUS.SIGNED
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

function handleSigningComplete(file) {
	if (!isActiveView.value) {
		return
	}
	isAsyncSigning.value = false
	shouldFireAsyncConfetti.value = true
	if (file) {
		loading.value = false
		handleValidationSuccess(file)
		return
	}
	loading.value = true
	refreshAfterAsyncSigning()
		.finally(() => {
			loading.value = false
		})
}

function handleSigningError(message) {
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

document.value = loadState('libresign', 'file_info', {})

if (!uuidToValidate.value) {
	document.value = {}
	hasInfo.value = false
} else {
	hasInfo.value = !!document.value?.name

	if (uuidToValidate.value !== document.value?.uuid) {
		document.value = {}
		hasInfo.value = false
		void validate(uuidToValidate.value)
	} else if (hasInfo.value && document.value.signers) {
		document.value.signers.forEach(signer => {
			signer.opened = false
		})
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
