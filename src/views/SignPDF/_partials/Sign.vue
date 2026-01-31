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
			<NcButton v-if="ableToSign"
				:wide="true"
				:disabled="loading"
				variant="primary"
				@click="confirmSignDocument">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Sign the document.') }}
			</NcButton>
			<div v-else-if="signMethodsStore.needCertificate()">
				<p>
					{{ t('libresign', 'You need to upload your certificate to sign the document.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="showModalAndResetErrors('uploadCertificate')">
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
					@click="showModalAndResetErrors('createPassword')">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needCreateSignature" class="no-signature-warning">
				<p>
					{{ t('libresign', 'You do not have any signature defined.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="showModalAndResetErrors('createSignature')">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else-if="needIdentificationDocuments" class="no-identification-warning">
				<Documents :sign-request-uuid="signRequestUuid" />
			</div>
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
			<NcNoteCard v-for="(error, index) in errors"
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
			<NcNoteCard v-for="(error, index) in errors"
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
			:errors="errors"
			@certificate:uploaded="onSignatureFileCreated" />
		<TokenManager v-if="signMethodsStore.modal.sms"
			:phone-number="user?.account?.phoneNumber || ''"
			@change="signWithTokenCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="signMethodsStore.closeModal('sms')" />
		<EmailManager v-if="signMethodsStore.modal.emailToken"
			@change="signWithEmailToken"
			@close="signMethodsStore.closeModal('emailToken')" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

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
import { FILE_STATUS } from '../../../constants.js'
import { getPrimarySigningAction } from '../../../helpers/SigningActionHelper.js'

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
		Documents,
		Signatures,
		Draw,
		ManagePassword,
		UploadCertificate,
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		const signatureElementsStore = useSignatureElementsStore()
		const sidebarStore = useSidebarStore()
		const identificationDocumentStore = useIdentificationDocumentStore()
		return { signStore, signMethodsStore, signatureElementsStore, sidebarStore, identificationDocumentStore }
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
			errors: [],
		}
	},
	computed: {
		elements() {
			const signer = this.signStore.document?.signers.find(row => row.me) || {}

			if (!signer.signRequestId) {
				return []
			}

			const visibleElements = (this.signStore.document?.visibleElements || [])
				.filter(row => {
					return this.signatureElementsStore.hasSignatureOfType(row.type)
						&& row.signRequestId === signer.signRequestId
				})
			return visibleElements
		},
		hasSignatures() {
			return this.elements.length > 0
		},
		needCreateSignature() {
			const signer = this.signStore.document?.signers.find(row => row.me) || {}
			const visibleElements = this.signStore.document?.visibleElements || []
			return !!signer.signRequestId
				&& visibleElements.some(row => row.signRequestId === signer.signRequestId)
				&& !this.hasSignatures
				&& this.canCreateSignature
		},
		needIdentificationDocuments() {
			const needsFromStore = this.identificationDocumentStore.needIdentificationDocument()

			const hasError = this.errors.some(error =>
				error.message && error.message.includes('approved identification document')
			)

			const isWaitingApproval = this.identificationDocumentStore.enabled && this.identificationDocumentStore.waitingApproval

			return needsFromStore || hasError || isWaitingApproval
		},
		canCreateSignature() {
			return getCapabilities()?.libresign?.config?.['sign-elements']?.['can-create-signature'] === true
		},
		ableToSign() {
			const primaryAction = getPrimarySigningAction(
				this.signStore,
				this.signMethodsStore,
				this.needCreateSignature,
				this.needIdentificationDocuments
			)
			return primaryAction?.action === 'sign'
		},
		signRequestUuid() {
			const doc = this.signStore.document || {}
			const signer = doc.signers?.find(row => row.me) || doc.signers?.[0] || {}
			const fromDoc = doc.signRequestUuid || doc.sign_request_uuid || doc.signUuid || doc.sign_uuid
			const fromSigner = signer.sign_uuid
			return fromDoc || fromSigner || loadState('libresign', 'sign_request_uuid', null)
		},
	},
	beforeUnmount() {
		this.resetSignMethodsState()
	},
	mounted() {
		this.loading = true
		this.signatureElementsStore.signRequestUuid = this.signRequestUuid
		this.signatureElementsStore.loadSignatures()

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
				this.errors = []
				this.showManagePassword = false
				this.signPassword = ''
			}
		},
	},
	methods: {
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
			this.errors = []
			this.showManagePassword = false
			this.signPassword = ''
		},
		showModalAndResetErrors(modalCode) {
			this.errors = []
			this.signMethodsStore.showModal(modalCode)
		},
		onSignatureFileCreated() {
			this.errors = []
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
			const signer = this.signStore.document.signers.find(s => s.me) || {}
			const identify = signer.identifyMethods?.[0] || {}
			await this.signDocument({
				method: 'clickToSign',
			})
		},
		async signWithPassword() {
			await this.signDocument({
				method: 'password',
				token: this.signPassword,
			})
		},
		async signWithTokenCode(token) {
			const tokenMethods = ['sms', 'whatsapp', 'signal', 'telegram', 'xmpp']
			const activeMethod = tokenMethods.find(method =>
				Object.hasOwn(this.signMethodsStore.settings, method)
			) || 'sms'

			await this.signDocument({
				method: activeMethod,
				token,
			})
		},
		async signWithEmailToken() {
			await this.signDocument({
				method: this.signMethodsStore.settings.emailToken.identifyMethod,
				token: this.signMethodsStore.settings.emailToken.token,
			})
		},
		async signDocument(payload = {}) {
			this.loading = true
			this.errors = []
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
			const isAuthenticated = !!getCurrentUser()
			let url = ''
			if (isAuthenticated && this.signStore.document.id > 0) {
				url = generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{id}', { id: this.signStore.document.id })
			} else {
				url = generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}', { uuid: this.signRequestUuid })
			}

			url += '?async=true'

			await axios.post(url, payload)
				.then(({ data }) => {
					const responseData = data.ocs?.data
					if (responseData?.job?.status === 'SIGNING_IN_PROGRESS') {
						this.signMethodsStore.closeModal(payload.method)
						this.$emit('signing-started', {
							signRequestUuid: this.signRequestUuid,
							async: true,
						})
						return
					}
					if (responseData?.action === 3500) { // ACTION_SIGNED
						this.signMethodsStore.closeModal(payload.method)
						this.sidebarStore.hideSidebar()
						const signedPayload = {
							...responseData,
							signRequestUuid: this.signRequestUuid,
						}
						this.$emit('signed', signedPayload)
					}
				})
				.catch((err) => {
					const action = err.response?.data?.ocs?.data?.action
					if (action === 4000) {
						if (this.signMethodsStore.certificateEngine === 'none') {
							this.showModalAndResetErrors('uploadCertificate')
						} else {
							this.showModalAndResetErrors('createPassword')
						}
					}
					this.errors = err.response?.data?.ocs?.data?.errors ?? []
				})
			this.loading = false
		},
		confirmSignDocument() {
			this.errors = []
			if (this.needIdentificationDocuments) {
				this.showModalAndResetErrors('uploadDocuments')
				return
			}
			if (this.signMethodsStore.needEmailCode()) {
				this.showModalAndResetErrors('emailToken')
				return
			}
			if (this.needCreateSignature) {
				this.showModalAndResetErrors('createSignature')
				return
			}
			if (this.signMethodsStore.needTokenCode()) {
				this.showModalAndResetErrors('sms')
				return
			}
			if (this.signMethodsStore.needCertificate()) {
				this.showModalAndResetErrors('uploadCertificate')
				return
			}
			if (this.signMethodsStore.needCreatePassword()) {
				this.showModalAndResetErrors('createPassword')
				return
			}
			if (this.signMethodsStore.needSignWithPassword()) {
				this.showModalAndResetErrors('password')
				return
			}
			if (this.signMethodsStore.needClickToSign()) {
				this.showModalAndResetErrors('clickToSign')
				return
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
