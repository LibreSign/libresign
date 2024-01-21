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
			<div v-if="needPassword && !hasSignatureFile">
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
			<div v-else-if="needSignature && !hasSignatures" class="no-signature-warning">
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
		<NcModal v-if="modals['click-to-sign']" @close="modals['click-to-sign'] = false">
			<div class="modal__content">
				<h2 class="modal__header">
					{{ t('libresign', 'Confirm') }}
				</h2>
				{{ t('libresign', 'Confirm your signature') }}
				<div class="modal__button-row">
					<NcButton @click="modals['click-to-sign'] = false">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						@click="signDocument">
						{{ t('libresign', 'Confirm') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<Draw v-if="modals.createSignature"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			@save="saveSignature"
			@close="onModalClose('createSignature')" />
		<PasswordManager v-if="modals.password"
			v-bind="{ hasSignatureFile }"
			@change="signWithPassword"
			@create="onPasswordCreate"
			@close="onModalClose('password')" />

		<SMSManager v-if="modals.sms"
			:phone-number="user?.account?.phoneNumber"
			:uuid="uuid"
			:file-id="document.fileId"
			@change="signWithCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="onModalClose('sms')" />

		<EmailManager v-if="modals.email"
			:email="blurredEmail"
			:uuid="uuid"
			:file-id="document.fileId"
			@change="signWithCode"
			@close="onModalClose('email')" />
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { onError } from '../../../helpers/errors.js'
import PasswordManager from './ModalPasswordManager.vue'
import SMSManager from './ModalSMSManager.vue'
import EmailManager from './ModalEmailManager.vue'
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'
import Draw from '../../../Components/Draw/Draw.vue'
import { loadState } from '@nextcloud/initial-state'

const SIGN_METHODS = Object.freeze({
	PASSWORD: 'PasswordManager',
	EMAIL: 'EmailManager',
	SMS: 'SMSManager',
})

export default {
	name: 'Sign',
	SIGN_METHODS,
	components: {
		NcModal,
		NcButton,
		PasswordManager,
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
		return {
			loading: true,
			modals: {
				password: false,
				email: false,
				sms: false,
				createSignature: false,
				'click-to-sign': false,
			},
			user: {
				account: { uid: '', displayName: '' },
			},
			blurredEmail: loadState('libresign', 'blurred_email', ''),
			userSignatures: loadState('libresign', 'user_signatures'),
			createPassword: false,
			hasSignatureFile: loadState('libresign', 'config', {})?.hasSignatureFile,
			signatureMethod: loadState('libresign', 'signature_method'),
		}
	},
	computed: {
		signer() {
			return this.document?.signers.find(row => row.me) || {}
		},
		visibleElements() {
			const { signRequestId } = this.signer

			if (!signRequestId) {
				return []
			}

			return (this.document?.visibleElements || [])
				.filter(row => row.signRequestId === this.signer.signRequestId)
		},
		elements() {
			const signature = this.userSignatures.find(row => {
				return row.type === 'signature'
			}) ?? {}
			if (Object.keys(signature).length === 0) {
				return []
			}
			const url = signature.file.url

			const element = this.visibleElements
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
		needSignature() {
			return this.document?.visibleElements.length > 0
		},
		needPassword() {
			return this.signatureMethod.id === 'password'
		},
		ableToSign() {
			if (this.needPassword && !this.hasSignatureFile) {
				return false
			}

			if (this.needSignature && !this.hasSignatures) {
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
			this.modals.createSignature = false
		},
		async signWithPassword(password) {
			return this.signDocument({ password })
		},
		async signWithCode(code) {
			return this.signDocument({ code })
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
				if (this.uuid.length) {
					url = generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}', { uuid: this.uuid })
				} else {
					url = generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}', { fileId: this.document.fileId })
				}

				const { data } = await axios.post(url, payload)
				this.$emit('signed', data)
			} catch (err) {
				onError(err)
			}
			this.loading = false
		},
		onPasswordCreate(hasSignatureFile) {
			this.hasSignatureFile = hasSignatureFile
		},
		callPassword() {
			this.modals.password = true
		},
		callCreateSignature() {
			this.modals.createSignature = true
		},
		confirmSignDocument() {
			if (this.modals[this.signatureMethod.id] === undefined) {
				showError(t('libresign', '{signatureMethod} is not a valid sign method', {
					signatureMethod: this.signatureMethod.label,
				}))
				return
			}

			this.modals[this.signatureMethod.id] = true
		},
		onModalClose(modal) {
			this.modals[modal] = false
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
