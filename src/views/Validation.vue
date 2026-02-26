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
					<h1>{{ t('libresign', 'Validate signature') }}</h1>
					<NcNoteCard v-if="validationErrorMessage" type="error">
						{{ validationErrorMessage }}
					</NcNoteCard>
					<NcActions :menu-name="t('libresign', 'Validate signature')" :inline="3" :force-name="true">
						<NcActionButton :wide="true" :disabled="loading" @click="openUuidDialog()">
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
					<NcDialog v-if="getUUID" :name="t('libresign', 'Validate signature')" is-form
						@closing="getUUID = false">
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
								{{ t('libresign', 'Validation') }}
							</NcButton>
						</template>
					</NcDialog>
				</div>
			</div>
			<div v-else class="infor-container">
				<component :is="isEnvelope ? 'EnvelopeValidation' : 'FileValidation'"
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

<script>
import {
	mdiAlertCircle,
	mdiAlertCircleOutline,
	mdiArrowLeft,
	mdiCancel,
	mdiCheckCircle,
	mdiCheckboxMarkedCircle,
	mdiKey,
	mdiUpload,
} from '@mdi/js'
import JSConfetti from 'js-confetti'
import axios from '@nextcloud/axios'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { defineAsyncComponent } from 'vue'

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

export default {
	name: 'Validation',
	components: {
		NcActionButton,
		NcActions,
		NcAvatar,
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		NcListItem,
		NcLoadingIcon,
		NcNoteCard,
		NcRichText,
		NcTextField,
		EnvelopeValidation,
		FileValidation,
		SigningProgress,
	},
	setup() {
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return {
			t,
			signStore,
			sidebarStore,
			mdiAlertCircle,
			mdiAlertCircleOutline,
			mdiArrowLeft,
			mdiCancel,
			mdiCheckCircle,
			mdiCheckboxMarkedCircle,
			mdiKey,
			mdiUpload,
		}
	},
	data() {
		return {
			logo: logoGray,
			uuidToValidate: this.$route.params?.uuid ?? '',
			hasInfo: false,
			loading: false,
			document: {},
			legalInformation: loadState('libresign', 'legal_information', ''),
			clickedValidate: false,
			getUUID: false,
			EXPIRATION_WARNING_DAYS: 30,
			validationStatusOpenState: {},
			extensionsOpenState: {},
			tsaOpenState: {},
			chainOpenState: {},
			notificationsOpenState: {},
			docMdpOpenState: {},
			validationErrorMessage: null,
			documentValidMessage: null,
			isAsyncSigning: false,
			shouldFireAsyncConfetti: false,
			isActiveView: true,
		}
	},
	computed: {
		signRequestUuidForProgress() {
			const doc = this.signStore?.document || {}
			const signer = doc.signers?.find(row => row.me) || doc.signers?.[0] || {}
			const fromDoc = doc.signRequestUuid || doc.sign_request_uuid || doc.signUuid || doc.sign_uuid
			const fromSigner = signer.sign_uuid
			return this.$route.query?.signRequestUuid
				|| this.$route.params?.signRequestUuid
				|| fromDoc
				|| fromSigner
				|| loadState('libresign', 'sign_request_uuid', null)
				|| this.uuidToValidate
		},
		isAfterSigned() {
			return history.state?.isAfterSigned ?? this.shouldFireAsyncConfetti ?? false
		},
		isEnvelope() {
			return this.document?.nodeType === 'envelope'
				|| (Array.isArray(this.document?.files) && this.document.files.length > 0)
		},
		canValidate() {
			if (!this.uuidToValidate) {
				return false
			}
			const isNumericId = /^\d+$/.test(this.uuidToValidate)
			const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
			return isNumericId || (this.uuidToValidate.length === 36 && uuidRegex.test(this.uuidToValidate))
		},
		helperTextValidation() {
			if (this.uuidToValidate && this.uuidToValidate.length > 0 && !this.canValidate) {
				return t('libresign', 'Invalid UUID')
			}
			return ''
		},
		size() {
			return formatFileSize(this.document.size)
		},
		documentStatus() {
			return getStatusLabel(this.document.status)
		},
		validityStatusMap() {
			return {
				unknown: { text: t('libresign', 'Unknown validity'), variant: 'tertiary', icon: this.mdiHelpCircle },
				expired: { text: t('libresign', 'Expired'), variant: 'error', icon: this.mdiCancel },
				expiring: { text: t('libresign', 'Expiring soon'), variant: 'warning', icon: this.mdiAlertCircleOutline },
				valid: { text: t('libresign', 'Currently valid'), variant: 'success', icon: this.mdiCheckCircle },
			}
		},
		crlStatusMap() {
			return {
				valid: { text: t('libresign', 'Not revoked'), variant: 'success', icon: this.mdiCheckCircle },
				revoked: { text: t('libresign', 'Certificate revoked'), variant: 'error', icon: this.mdiCancel },
				missing: { text: t('libresign', 'No CRL information'), variant: 'warning', icon: this.mdiAlertCircle },
				no_urls: { text: t('libresign', 'No CRL URLs found'), variant: 'warning', icon: this.mdiAlertCircle },
				urls_inaccessible: { text: t('libresign', 'CRL URLs inaccessible'), variant: 'tertiary', icon: this.mdiHelpCircle },
				validation_failed: { text: t('libresign', 'CRL validation failed'), variant: 'tertiary', icon: this.mdiHelpCircle },
				validation_error: { text: t('libresign', 'CRL validation error'), variant: 'tertiary', icon: this.mdiHelpCircle },
			}
		},
	},
	watch: {
		isAsyncSigning(active) {
			if (active) {
				this.sidebarStore.hideSidebar()
			}
		},
		'$route.params.uuid'(uuid) {
			this.validate(uuid)
		},
	},
	created() {
		this.document = loadState('libresign', 'file_info', {})

		if (!this.uuidToValidate) {
			this.document = {}
			this.hasInfo = false
			return
		}

		this.hasInfo = !!this.document?.name

		if (this.uuidToValidate !== this.document?.uuid) {
			this.document = {}
			this.hasInfo = false
			this.validate(this.uuidToValidate)
		} else if (this.hasInfo && this.document.signers) {
			this.document.signers.forEach(signer => {
				signer.opened = false
			})
		} else if (this.uuidToValidate.length > 0) {
			this.validate(this.uuidToValidate)
		}

		if (history.state?.isAsync === true) {
			this.isAsyncSigning = true
			this.shouldFireAsyncConfetti = true
			this.loading = true
		}
	},
	beforeUnmount() {
		this.isActiveView = false
	},
	methods: {
		async upload(file) {
			const formData = new FormData()
			formData.append('file', file)
			await axios.postForm(generateOcsUrl('/apps/libresign/api/v1/file/validate'), formData, {
				headers: {
					'Content-Type': 'multipart/form-data',
				},
			})
				.then(({ data }) => {
					this.clickedValidate = true
					this.handleValidationSuccess(data.ocs.data)
				})
				.catch(({ response }) => {
					const errorMsg = response?.data?.ocs?.data?.errors?.length > 0
						? response.data.ocs.data.errors[0].message
						: t('libresign', 'Failed to validate document')
					this.setValidationError(errorMsg)
				})
		},
		async uploadFile() {
			this.loading = true
			const input = document.createElement('input')
			input.accept = 'application/pdf'
			input.type = 'file'

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					await this.upload(file)
				}
				this.loading = false

				input.remove()
			}

			input.click()
		},
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
		toggleDetail(signer) {
			signer.opened = !signer.opened
		},
		toggleFileDetail(file) {
			file.opened = !file.opened
		},
		getSignerStatus(status) {
			const statusMap = {
				pending: t('libresign', 'Pending'),
				partial: t('libresign', 'Partial'),
				complete: t('libresign', 'Complete'),
			}
			return statusMap[status] || status
		},
		async validate(id, { suppressLoading = false, forceRefresh = false } = {}) {
			this.validationErrorMessage = null
			this.documentValidMessage = null
			if (id === this.document?.uuid && !forceRefresh) {
				this.documentValidMessage = t('libresign', 'This document is valid')
				this.hasInfo = true
			} else if (id.length === 36) {
				await this.validateByUUID(id, { suppressLoading })
			} else {
				await this.validateByNodeID(id, { suppressLoading })
			}
			this.getUUID = false
		},
		async validateByUUID(uuid, { suppressLoading = false } = {}) {
			if (!suppressLoading) {
				this.loading = true
			}
			const cacheBuster = suppressLoading ? `?_t=${Date.now()}` : ''
			await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}${cacheBuster}`))
				.then(({ data }) => {
					this.handleValidationSuccess(data.ocs.data)
				})
				.catch(({ response }) => {
					if (response?.status === 404) {
						this.setValidationError(t('libresign', 'Document not found'))
					} else if (response?.data?.ocs?.data?.errors?.length > 0) {
						this.setValidationError(response.data.ocs.data.errors[0].message)
					} else {
						this.setValidationError(t('libresign', 'Failed to validate document'))
					}
				})
			if (!suppressLoading) {
				this.loading = false
			}
		},
		async validateByNodeID(nodeId, { suppressLoading = false } = {}) {
			if (!suppressLoading) {
				this.loading = true
			}
			const cacheBuster = suppressLoading ? `?_t=${Date.now()}` : ''
			await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}${cacheBuster}`))
				.then(({ data }) => {
					this.handleValidationSuccess(data.ocs.data)
				})
				.catch(({ response }) => {
					if (response?.status === 404) {
						this.setValidationError(t('libresign', 'Document not found'))
					} else if (response?.data?.ocs?. data?.errors?.length > 0) {
						this.setValidationError(response.data.ocs.data.errors[0].message)
					} else {
						this.setValidationError(t('libresign', 'Failed to validate document'))
					}
				})
			if (!suppressLoading) {
				this.loading = false
			}
		},
		getName(signer) {
			return signer.displayName || signer.email || signer.signature_validation?.label || t('libresign', 'Unknown')
		},
		getIconValidityPath(signer) {
			if (signer.signature_validation?.id === 1) {
				return mdiCheckboxMarkedCircle
			}
			return mdiAlertCircle
		},
		async viewDocument() {
			const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: this.document.uuid })
			await openDocument({
				fileUrl,
				filename: this.document.name,
				nodeId: this.document.nodeId,
			})
		},
		goBack() {
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
			this.hasInfo = false
			this.uuidToValidate = this.$route.params?.uuid ?? ''
			this.validationErrorMessage = null
			this.documentValidMessage = null
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
				return t('libresign', 'Document integrity verified')
			}
			// TRANSLATORS validationStatus is the signature validation status (e.g., "Valid", "Invalid")
			return t('libresign', 'Signature: {validationStatus}', { validationStatus: signer.signature_validation.label })
		},
		getCertificateTrustMessage(signer) {
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

			if (valid && status === 2) return this.mdiCheckCircle
			if (status === 1) return this.mdiCheckCircle
			if (status === 3) return this.mdiCancel
			return this.mdiHelpCircle
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
		validateAndProceed() {
			this.clickedValidate = true
			this.validate(this.uuidToValidate)
		},
		toggleState(stateObject, index) {
			stateObject[index] = !stateObject[index]
		},
		hasValidationStatus(signer) {
			return signer.signature_validation
				|| signer.certificate_validation
				|| (signer.valid_from && signer.valid_to && signer.signed)
				|| signer.crl_validation
		},
		setValidationError(message, timeout = 5000) {
			this.validationErrorMessage = message
			if (timeout > 0) {
				setTimeout(() => {
					this.validationErrorMessage = null
				}, timeout)
			}
		},
		openUuidDialog() {
			this.validationErrorMessage = null
			this.getUUID = true
		},
		handleValidationSuccess(data) {
			if (!this.isActiveView) {
				return
			}
			this.documentValidMessage = t('libresign', 'This document is valid')
			if (!data?.nodeType && Array.isArray(data?.files) && data.files.length > 0) {
				data.nodeType = 'envelope'
			}
			const routeName = this.$route?.name || ''
			const shouldUpdateRoute = routeName === 'validation'
				|| routeName === 'ValidationFile'
				|| routeName === 'ValidationFileExternal'
				|| routeName === 'ValidationFileShortUrl'
			if (shouldUpdateRoute && data?.uuid && this.$route.params?.uuid !== data.uuid) {
				this.$router.replace({
					name: this.$route.name,
					params: {
						...this.$route.params,
						uuid: data.uuid,
					},
					query: this.$route.query,
				})
			}
			this.document = data
			this.document.signers?.forEach(signer => {
				signer.opened = false
			})
			this.document.files?.forEach(file => {
				file.opened = false
			})
			this.hasInfo = true
			const isSignedStatus = status => Number(status) === FILE_STATUS.SIGNED
			const isSignedDoc = isSignedStatus(this.document?.status)
			const allFilesSigned = Array.isArray(this.document?.files)
				&& this.document.files.length > 0
				&& this.document.files.every(file => isSignedStatus(file.status))
			const signerCompleted = this.isCurrentSignerSigned()
			if ((isSignedDoc || allFilesSigned || signerCompleted) && (this.isAfterSigned || this.shouldFireAsyncConfetti)) {
				const jsConfetti = new JSConfetti()
				jsConfetti.addConfetti()
				this.shouldFireAsyncConfetti = false
			}
		},
		async refreshAfterAsyncSigning() {
			const maxAttempts = 8
			for (let attempt = 1; attempt <= maxAttempts; attempt++) {
				if (!this.isActiveView) {
					return
				}
				await this.validate(this.uuidToValidate, { suppressLoading: true, forceRefresh: true })

				if (this.isCurrentSignerSigned()) {
					return
				}

				const isSignedStatus = status => Number(status) === FILE_STATUS.SIGNED
				const isSigned = isSignedStatus(this.document?.status)
				const allFilesSigned = Array.isArray(this.document?.files)
					&& this.document.files.length > 0
					&& this.document.files.every(file => isSignedStatus(file.status))

				if (isSigned || allFilesSigned) {
					return
				}

				await new Promise(resolve => setTimeout(resolve, 900))
			}
		},
		handleSigningComplete(file) {
			if (!this.isActiveView) {
				return
			}
			this.isAsyncSigning = false
			this.shouldFireAsyncConfetti = true
			if (file) {
				this.loading = false
				this.handleValidationSuccess(file)
				return
			}
			this.loading = true
			this.refreshAfterAsyncSigning()
				.finally(() => {
					this.loading = false
				})
		},
		handleSigningError(message) {
			this.loading = false
			const errorMessage = message || t('libresign', 'Signing failed. Please try again.')
			this.setValidationError(errorMessage)
		},
		isCurrentSignerSigned() {
			const signer = this.document?.signers?.find(row => row.me)
			return !!signer?.signed || Number(signer?.status) === SIGN_REQUEST_STATUS.SIGNED
		},
	},
}
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
