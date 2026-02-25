<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="document-sign">
		<div class="sign-elements">
			<Signatures v-if="hasSignatures" />
		</div>
		<div v-if="!loading" class="button-wrapper">
			<div v-if="needCreateSignature" class="no-signature-warning">
				<p>
					{{ t('libresign', 'You do not have any signature defined.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="actionHandler.showModal('createSignature')">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCertificate()">
				<p>
					{{ t('libresign', 'You need to upload your certificate to sign the document.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="actionHandler.showModal('uploadCertificate')">
					{{ t('libresign', 'Upload certificate') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCreatePassword()">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="actionHandler.showModal('createPassword')">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needIdentificationDocuments" class="no-identification-warning">
				<Documents :sign-request-uuid="signRequestUuid" />
			</div>
			<NcButton v-else-if="ableToSign"
				:wide="true"
				:disabled="loading"
				variant="primary"
				@click="confirmSignDocument">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Sign the document.') }}
			</NcButton>
			<div v-else>
				<p>
					{{ t('libresign', 'Unable to sign.') }}
				</p>
			</div>
		</div>
		<NcDialog v-if="signMethodsStore.modal.clickToSign"
			:no-close="loading"
			:name="t('libresign', 'Confirm')"
			size="small"
			dialog-classes="libresign-dialog"
			@closing="signMethodsStore.closeModal('clickToSign')">
			<NcNoteCard v-for="(error, index) in signStore.errors"
				:key="index"
				:heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message"
					:use-markdown="true" />
			</NcNoteCard>
			{{ t('libresign', 'Confirm your signature') }}
			<template #actions>
				<NcButton :disabled="loading"
					@click="signMethodsStore.closeModal('clickToSign')">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="loading"
					@click="signWithClick">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Confirm') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-if="signMethodsStore.modal.password"
			:no-close="loading"
			:name="t('libresign', 'Confirm your signature')"
			size="small"
			dialog-classes="libresign-dialog"
			@closing="onCloseConfirmPassword">
			<NcNoteCard v-for="(error, index) in signStore.errors"
				:key="index"
				:heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message"
					:use-markdown="true" />
			</NcNoteCard>
			{{ t('libresign', 'Subscription password.') }}
			<form @submit.prevent="signWithPassword()">
				<NcPasswordField v-model="signPassword" type="password" />
			</form>
			<a id="lost-password" @click="toggleManagePassword">{{ t('libresign', 'Forgot password?') }}</a>
			<ManagePassword v-if="showManagePassword"
				@certificate:uploaded="onSignatureFileCreated" />
			<template #actions>
				<NcButton :disabled="signPassword.length < 3 || loading"
					type="submit"
					variant="primary"
					@click="signWithPassword()">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Sign the document.') }}
				</NcButton>
			</template>
		</NcDialog>
		<Draw v-if="signMethodsStore.modal.createSignature"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			:sign-request-uuid="signRequestUuid"
			type="signature"
			@save="saveSignature"
			@close="signMethodsStore.closeModal('createSignature')" />
		<CreatePassword @password:created="onSignatureFileCreated" />
		<UploadCertificate
			:useModal="true"
			:errors="signStore.errors"
			@certificate:uploaded="onSignatureFileCreated" />
		<TokenManager v-if="signMethodsStore.modal.token"
			:phone-number="user?.account?.phoneNumber || ''"
			@change="signWithTokenCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="signMethodsStore.closeModal('token')" />
		<EmailManager v-if="signMethodsStore.modal.emailToken"
			@change="signWithEmailToken"
			@close="signMethodsStore.closeModal('emailToken')" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import EmailManager from './ModalEmailManager.vue'
import TokenManager from './ModalTokenManager.vue'
import Draw from '../../../components/Draw/Draw.vue'
import Documents from '../../../views/Account/partials/Documents.vue'
import Signatures from '../../../views/Account/partials/Signatures.vue'
import CreatePassword from '../../../views/CreatePassword.vue'
import ManagePassword from '../../Account/partials/ManagePassword.vue'
import UploadCertificate from '../../../views/UploadCertificate.vue'

import { useSidebarStore } from '../../../store/sidebar.js'
import { useSignStore } from '../../../store/sign.js'
import { useSignatureElementsStore } from '../../../store/signatureElements.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'
import { useIdentificationDocumentStore } from '../../../store/identificationDocument.js'
import { SigningRequirementValidator } from '../../../services/SigningRequirementValidator'
import { SignFlowHandler } from '../../../services/SignFlowHandler'
import { FILE_STATUS } from '../../../constants.js'
import { getFileSigners, getVisibleElementsFromDocument, idsMatch } from '../../../services/visibleElementsService'

export default {
	name: 'Sign',
	components: {
		NcDialog,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcPasswordField,
		NcRichText,
		CreatePassword,
		TokenManager,
		EmailManager,
		UploadCertificate,
		Documents,
		Signatures,
		ManagePassword,
		Draw,
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		const signatureElementsStore = useSignatureElementsStore()
		const sidebarStore = useSidebarStore()
		const identificationDocumentStore = useIdentificationDocumentStore()
		return {
			signStore,
			signMethodsStore,
			signatureElementsStore,
			sidebarStore,
			identificationDocumentStore,
		}
	},
	data() {
		return {
			loading: true,
			user: {
				account: { uid: '', displayName: '' },
	},
			signPassword: '',
			showManagePassword: false,
			isModal: window.self !== window.top,
		}
	},
	computed: {
		elements() {
				const document = this.signStore.document || {}
				const signer = document?.signers?.find(row => row.me) || {}

				const signRequestIds = new Set()
				if (signer.signRequestId) {
					signRequestIds.add(String(signer.signRequestId))
				}

				if (Array.isArray(document?.files)) {
					document.files
						.flatMap(file => getFileSigners(file))
						.filter(row => row.me && row.signRequestId)
						.forEach(row => signRequestIds.add(String(row.signRequestId)))
				}

				if (signRequestIds.size === 0) {
					return []
				}

				const visibleElements = getVisibleElementsFromDocument(document)
				.filter(row => {
					return this.signatureElementsStore.hasSignatureOfType(row.type)
						&& signRequestIds.has(String(row.signRequestId))
				})
			return visibleElements
		},
		hasSignatures() {
			return this.elements.length > 0
		},
		needCreateSignature() {
			const document = this.signStore.document || {}
			const signer = document?.signers?.find(row => row.me) || {}

			const signRequestIds = new Set()
			if (signer.signRequestId) {
				signRequestIds.add(String(signer.signRequestId))
			}
			if (Array.isArray(document?.files)) {
				document.files
					.flatMap(file => getFileSigners(file))
					.filter(row => row.me && row.signRequestId)
					.forEach(row => signRequestIds.add(String(row.signRequestId)))
			}

			const visibleElements = getVisibleElementsFromDocument(document)
			return signRequestIds.size > 0
				&& visibleElements.some(row => signRequestIds.has(String(row.signRequestId)))
				&& !this.hasSignatures
				&& this.canCreateSignature
		},
		needIdentificationDocuments() {
			return this.identificationDocumentStore.showDocumentsComponent()
		},
		canCreateSignature() {
			return getCapabilities()?.libresign?.config?.['sign-elements']?.['can-create-signature'] === true
		},
		ableToSign() {
			return this.signStore.ableToSign
		},
		signRequestUuid() {
			const doc = this.signStore.document || {}
			const signer = doc.signers?.find(row => row.me) || doc.signers?.[0] || {}
			const fromDoc = doc.signRequestUuid || doc.sign_request_uuid || doc.signUuid || doc.sign_uuid
			const fromSigner = signer.sign_uuid
			const isApprover = doc.settings?.isApprover
			const fromFile = isApprover ? doc.uuid : null
			return fromDoc || fromSigner || fromFile || loadState('libresign', 'sign_request_uuid', null)
		},
	},
	beforeUnmount() {
		this.resetSignMethodsState()
		if (this.unwatchPendingAction) {
			this.unwatchPendingAction()
		}
	},
	mounted() {
		this.loading = true
		this.signatureElementsStore.signRequestUuid = this.signRequestUuid
		this.signatureElementsStore.loadSignatures()

		this.initializeServices()

		this.unwatchPendingAction = this.$watch(
			() => this.signStore.pendingAction,
			(newAction) => {
				if (newAction) {
					this.executeSigningAction(newAction)
					this.signStore.clearPendingAction()
				}
			}
		)

		if (this.signStore.pendingAction) {
			this.$nextTick(() => {
				this.executeSigningAction(this.signStore.pendingAction)
				this.signStore.clearPendingAction()
			})
		}

		Promise.all([
			this.loadUser(),
		])
			.then(() => {
				this.loading = false
				if (this.signStore.document?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
					this.$emit('signing-started', {
						signRequestUuid: this.signRequestUuid,
						async: true,
					})
				}
			})
	},
	watch: {
		signRequestUuid(newUuid, oldUuid) {
			if (newUuid && oldUuid && newUuid !== oldUuid) {
				Object.keys(this.signMethodsStore.modal).forEach(key => {
					this.signMethodsStore.closeModal(key)
				})
				this.signStore.clearSigningErrors()
				this.showManagePassword = false
				this.signPassword = ''
			}
		},
	},
	methods: {
		t,
		initializeServices() {
			this.requirementValidator = new SigningRequirementValidator(
				this.signStore,
				this.signMethodsStore,
				this.identificationDocumentStore
			)

			this.actionHandler = new SignFlowHandler(this.signMethodsStore)
		},

		async loadUser() {
			if (getCurrentUser()) {
				try {
					const { data } = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/me'))
					this.user = data.ocs.data
				} catch (err) {
				}
			}
		},
		toggleManagePassword() {
			this.showManagePassword = !this.showManagePassword
		},
		onCloseConfirmPassword() {
			this.showManagePassword = false
			this.signMethodsStore.closeModal('password')
		},
		resetSignMethodsState() {
			if (typeof this.signMethodsStore?.$reset === 'function') {
				this.signMethodsStore.$reset()
			} else {
				Object.keys(this.signMethodsStore.modal || {}).forEach(key => {
					this.signMethodsStore.closeModal(key)
				})
				this.signMethodsStore.settings = {}
			}
			this.signStore.clearSigningErrors()
			this.showManagePassword = false
			this.signPassword = ''
		},
		onSignatureFileCreated() {
			this.signStore.clearSigningErrors()
			this.showManagePassword = false
		},
		saveSignature() {
			if (this.signatureElementsStore.success.length) {
				showSuccess(this.signatureElementsStore.success)
			} else if (this.signatureElementsStore.error.length) {
				showError(this.signatureElementsStore.error)
			}
			this.signMethodsStore.closeModal('createSignature')
		},
		async signWithClick() {
			await this.submitSignature({ method: 'clickToSign' })
		},
		async signWithPassword() {
			await this.submitSignature({
				method: 'password',
				token: this.signPassword,
			})
		},
		async signWithTokenCode(token) {
			const tokenMethods = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
			const activeMethod = tokenMethods.find(method =>
				Object.hasOwn(this.signMethodsStore.settings, method)
			)

			if (!activeMethod) {
				throw new Error('No active token method found')
			}

			const signatureMethodData = this.signMethodsStore.settings[activeMethod]
			const identifyMethod = signatureMethodData.identifyMethod

			await this.submitSignature({
				method: identifyMethod,
				token,
			})
		},
		async signWithEmailToken() {
			await this.submitSignature({
				method: this.signMethodsStore.settings.emailToken.identifyMethod,
				token: this.signMethodsStore.settings.emailToken.token,
			})
		},
		async submitSignature(methodConfig = {}) {
			this.loading = true
			this.signStore.clearSigningErrors()

			try {
				const payload = {
					method: methodConfig.method,
				}

				if (methodConfig.token) {
					payload.token = methodConfig.token
				}

				if (this.elements.length > 0) {
					if (this.canCreateSignature) {
						payload.elements = this.elements
							.map(row => ({
								documentElementId: row.elementId,
								profileNodeId: this.signatureElementsStore.signs[row.type].file.nodeId,
							}))
					} else {
						payload.elements = this.elements
							.map(row => ({
								documentElementId: row.elementId,
							}))
					}
				}

				const result = await this.signStore.submitSignature(
					payload,
					this.signRequestUuid,
					{
						documentId: this.signStore.document.id,
					}
				)

				if (result.status === 'signingInProgress') {
					this.actionHandler.closeModal(methodConfig.method)
					this.$emit('signing-started', {
						signRequestUuid: this.signRequestUuid,
						async: true,
					})
				} else if (result.status === 'signed') {
					this.actionHandler.closeModal(methodConfig.method)
					this.sidebarStore.hideSidebar()
					this.$emit('signed', {
						...result.data,
						signRequestUuid: this.signRequestUuid,
					})
				}
			} catch (error) {
				if (error.type === 'missingCertification') {
					const modalCode = this.signMethodsStore.certificateEngine === 'none'
						? 'uploadCertificate'
						: 'createPassword'
					this.actionHandler.showModal(modalCode)
				}

				this.signStore.setSigningErrors(error.errors || [])
			} finally {
				this.loading = false
			}
		},
		confirmSignDocument() {
			this.signStore.clearSigningErrors()

			const unmetRequirement = this.requirementValidator.getFirstUnmetRequirement({
				errors: this.signStore.errors,
				hasSignatures: this.hasSignatures,
				canCreateSignature: this.canCreateSignature,
			})

			const result = this.actionHandler.handleAction('sign', { unmetRequirement })

			if (result === 'ready') {
				this.proceedWithSigning()
			}
		},
		proceedWithSigning() {
			if (this.signMethodsStore.needClickToSign()) {
				this.actionHandler.showModal('clickToSign')
			} else if (this.signMethodsStore.needSignWithPassword()) {
				this.actionHandler.showModal('password')
			} else if (this.signMethodsStore.needTokenCode()) {
				this.actionHandler.showModal('token')
			}
		},
		executeSigningAction(action) {
			this.signStore.clearSigningErrors()

			const unmetRequirement = this.requirementValidator.getFirstUnmetRequirement({
				errors: this.signStore.errors,
				hasSignatures: this.hasSignatures,
				canCreateSignature: this.canCreateSignature,
			})

			const config = unmetRequirement ? { unmetRequirement } : {}
			const result = this.actionHandler.handleAction(action, config)

			if (result === 'ready') {
				this.proceedWithSigning()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.no-signature-warning {
	margin-top: 1em;
}

.no-identification-warning {
	margin-top: 1em;
}

.button-wrapper {
	padding: calc(var(--default-grid-baseline, 4px)*2);
}

.sign-elements {
	img {
		max-width: 100%;
	}
}

.modal {
	&__content {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 20px;
		gap: 4px 0;
	}
	&__header {
		font-weight: bold;
		font-size: 20px;
		margin-bottom: 12px;
		line-height: 30px;
		color: var(--color-text-light);
	}
	&__button-row {
		display: flex;
		width: 100%;
		justify-content: space-between;
	}
}
</style>

<style lang="scss">
/* Targeted override: keep small dialog compact on guest/mobile */
@media only screen and ((max-width: 512px) or (max-height: 400px)) {
	.libresign-dialog .modal-wrapper--small > .modal-container {
		width: fit-content !important;
		height: unset !important;
		max-height: 90% !important;
		position: relative !important;
		top: unset !important;
		border-radius: var(--border-radius-large) !important;
	}

	/* Apply same rule to NcDialog's default wrapper class */
	.dialog__modal .modal-wrapper--small > .modal-container {
		width: fit-content !important;
		height: unset !important;
		max-height: 90% !important;
		position: relative !important;
		top: unset !important;
		border-radius: var(--border-radius-large) !important;
	}
}
</style>
