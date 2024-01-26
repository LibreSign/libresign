<template>
	<div class="document-sign">
		<div class="sign-elements">
			<figure v-for="element in elements" :key="`element-${element.documentElementId}`">
				<PreviewSignature :src="element.url" />
			</figure>
		</div>
		<div v-if="ableToSign" class="button-wrapper">
			<NcButton :wide="true"
				:disabled="loading"
				type="primary"
				@click="confirmSignDocument">
				{{ t('libresign', 'Sign the document.') }}
			</NcButton>
		</div>
		<div v-else-if="!loading" class="button-wrapper">
			<div v-if="needCreatePassword">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					type="primary"
					@click="callPassword">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needSignature" class="no-signature-warning">
				<p>
					{{ t('libresign', 'You do not have any signature defined.') }}
				</p>

				<NcButton :wide="true"
					:disabled="loading"
					type="primary"
					@click="callCreateSignature">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else>
				<p>
					{{ t('libresign', 'Unable to sign.') }}
				</p>
			</div>
		</div>
		<NcModal v-if="signatureMethods.clickToSign.modal"
			:can-close="!loading"
			@close="onModalClose('clicKToSign')">
			<div class="modal__content">
				<h2 class="modal__header">
					{{ t('libresign', 'Confirm') }}
				</h2>
				{{ t('libresign', 'Confirm your signature') }}
				<div class="modal__button-row">
					<NcButton @click="signatureMethods.clickToSign.modal = false">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						@click="signWithClick">
						{{ t('libresign', 'Confirm') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<NcModal v-if="modalSignWithPassword"
			:can-close="!loading"
			@close="modalSignWithPassword = false">
			<div class="modal__content">
				<h2 class="modal__header">
					{{ t('libresign', 'Confirm your signature') }}
				</h2>
				{{ t('libresign', 'Subscription password.') }}
				<NcPasswordField :value.sync="signPassword" type="password" />
				<div class="modal__button-row">
					<NcButton @click="modalSignWithPassword = false">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="signPassword.length < 3" @click="signWithPassword()">
						{{ t('libresign', 'Sign the document.') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<Draw v-if="signatureMethods.createSignature.modal"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			@save="saveSignature"
			@close="onModalClose('createSignature')" />
		<NcModal v-if="modalCreatePassword" @close="modalCreatePassword = false">
			<CreatePassword @password:created="onPasswordCreate"
				@close="modalCreatePassword = false" />
		</NcModal>
		<SMSManager v-if="signatureMethods.sms.modal"
			:phone-number="user?.account?.phoneNumber"
			:uuid="uuid"
			:file-id="document.fileId"
			:confirm-code="needSmsCode"
			@change="signWithSMSCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="onModalClose('sms')" />

		<EmailManager v-if="signatureMethods.email.modal"
			:email="blurredEmail"
			:uuid="uuid"
			:file-id="document.fileId"
			:confirm-code="signatureMethods.email.necessary"
			@change="signWithEmailToken"
			@close="onModalClose('email')" />
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess } from '@nextcloud/dialogs'
import { onError } from '../../../helpers/errors.js'
import SMSManager from './ModalSMSManager.vue'
import EmailManager from './ModalEmailManager.vue'
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'
import Draw from '../../../Components/Draw/Draw.vue'
import { loadState } from '@nextcloud/initial-state'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import CreatePassword from '../../../views/CreatePassword.vue'

export default {
	name: 'Sign',
	components: {
		NcModal,
		NcButton,
		NcPasswordField,
		CreatePassword,
		SMSManager,
		EmailManager,
		PreviewSignature,
		Draw,
	},
	props: {
		uuid: {
			type: String,
			required: true,
		},
		document: {
			type: Object,
			required: true,
		},
		docType: {
			type: String,
			required: false,
			default: 'default',
		},
	},
	data() {
		const signatureMethods = loadState('libresign', 'signature_methods')
		for (const methodId of Object.keys(signatureMethods)) {
			signatureMethods[methodId].modal = false
		}
		signatureMethods.createSignature = { modal: false }
		signatureMethods.sms = { modal: false }
		signatureMethods.clickToSign.necessary = signatureMethods.clickToSign.enabled
		return {
			loading: true,
			user: {
				account: { uid: '', displayName: '' },
			},
			modalCreatePassword: false,
			modalSignWithPassword: false,
			signPassword: '',
			blurredEmail: loadState('libresign', 'blurred_email', ''),
			userSignatures: loadState('libresign', 'user_signatures'),
			hasSignatureFile: loadState('libresign', 'config', {})?.hasSignatureFile,
			signatureMethods,
		}
	},
	computed: {
		elements() {
			const signature = this.userSignatures.find(row => {
				return row.type === 'signature'
			}) ?? {}
			if (Object.keys(signature).length === 0) {
				return []
			}

			const signer = this.document?.signers.find(row => row.me) || {}

			if (!signer.signRequestId) {
				return []
			}

			const visibleElements = (this.document?.visibleElements || [])
				.filter(row => row.signRequestId === signer.signRequestId)

			const url = signature.file.url
			const element = visibleElements
				.map(el => ({
					documentElementId: el.elementId,
					profileElementId: signature.id,
					url: `${url}&_t=${Date.now()}`,
				}))
			return element
		},
		hasSignatures() {
			return this.userSignatures.length > 0
		},
		needCreatePassword() {
			return this.signatureMethods.password.enabled
				&& !this.hasSignatureFile
		},
		needSignature() {
			return this.document?.visibleElements.length > 0
				&& !this.hasSignatures
		},
		needEmailCode() {
			return this.signatureMethods.email.enabled
				&& this.signatureMethods.email.validateCode
		},
		needSmsCode() {
			return (this.signatureMethods.sms?.enabled
				&& this.signatureMethods.sms?.validateCode)
				?? false
		},
		ableToSign() {
			if (this.needCreatePassword) {
				return false
			}
			if (this.needSignature) {
				return false
			}
			return true
		},
	},
	mounted() {
		this.loading = true

		Promise.all([
			this.loadUser(),
		])
			.catch(console.warn)
			.then(() => {
				this.loading = false
			})
	},
	methods: {
		async loadUser() {
			try {
				const { data } = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/me'))
				this.user = data
			} catch (err) {
			}
		},
		async saveSignature(value) {
			try {
				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'), {
					elements: [
						{
							file: {
								base64: value,
							},
							type: 'signature',
						},
					],
				})
				this.userSignatures = response.data.elements
				showSuccess(response.data.message)
			} catch (err) {
				onError(err)
			}
			this.signatureMethods.createSignature.modal = false
		},
		async signWithClick() {
			return this.signDocument({
				method: 'clickToSign',
			})
		},
		async signWithPassword() {
			return this.signDocument({
				method: 'password',
				identifyValue: this.signPassword,
			})
		},
		async signWithSMSCode(token) {
			return this.signDocument({
				method: 'sms',
				token,
			})
		},
		async signWithEmailToken(token) {
			return this.signDocument({
				method: 'email',
				token,
			})
		},
		async signDocument(payload = {}) {
			this.loading = true
			if (this.elements.length > 0) {
				payload.elements = this.elements
					.map(row => ({
						documentElementId: row.documentElementId,
						profileElementId: row.profileElementId,
					}))
			}
			try {
				let url = ''
				if (this.document.fileId > 0) {
					url = generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}', { fileId: this.document.fileId })
				} else {
					url = generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}', { uuid: this.uuid })
				}

				const { data } = await axios.post(url, payload)
				if (data?.action === 350) { // ACTION_SIGNED
					this.$emit('signed', data)
				}
			} catch (err) {
				onError(err)
			}
			this.loading = false
		},
		onPasswordCreate(hasSignatureFile) {
			this.hasSignatureFile = hasSignatureFile
		},
		callPassword() {
			this.modalCreatePassword = true
		},
		callCreateSignature() {
			this.signatureMethods.createSignature.modal = true
		},
		confirmSignDocument() {
			if (this.needEmailCode) {
				this.signatureMethods.email.modal = true
				return
			}
			if (this.needSignature) {
				this.signatureMethods.createSignature.modal = true
				return
			}
			if (this.needSmsCode) {
				this.signatureMethods.sms.modal = true
			}
			if (this.signatureMethods.password.enabled && !this.needCreatePassword) {
				this.modalSignWithPassword = true
			}
		},
		onModalClose(methodId) {
			this.signatureMethods[methodId].modal = false
		},
	},
}
</script>

<style lang="scss" scoped>
.no-signature-warning {
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
