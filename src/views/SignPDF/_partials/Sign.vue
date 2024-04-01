<template>
	<div class="document-sign">
		<div class="sign-elements">
			<figure v-for="element in elements" :key="`element-${element.documentElementId}`">
				<PreviewSignature :src="element.url" />
			</figure>
		</div>
		<div v-if="!loading" class="button-wrapper">
			<div v-if="ableToSign" class="button-wrapper">
				<NcButton :wide="true"
					:disabled="loading"
					type="primary"
					@click="confirmSignDocument">
					{{ t('libresign', 'Sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCreatePassword()">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					type="primary"
					@click="signMethodsStore.showModal('createPassword')">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needCreateSignature" class="no-signature-warning">
				<p>
					{{ t('libresign', 'You do not have any signature defined.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					type="primary"
					@click="signMethodsStore.showModal('createSignature')">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else>
				<p>
					{{ t('libresign', 'Unable to sign.') }}
				</p>
			</div>
		</div>
		<NcModal v-if="signMethodsStore.needClickToSign() && signMethodsStore.modal.clickToSign"
			:can-close="!loading"
			@close="signMethodsStore.closeModal('clickToSign')">
			<div class="modal__content">
				<h2 class="modal__header">
					{{ t('libresign', 'Confirm') }}
				</h2>
				{{ t('libresign', 'Confirm your signature') }}
				<div class="modal__button-row">
					<NcButton :disabled="loading"
						@click="signMethodsStore.closeModal('clickToSign')">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="loading"
						@click="signWithClick">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ t('libresign', 'Confirm') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<NcModal v-if="signMethodsStore.modal.password"
			:can-close="!loading"
			@close="signMethodsStore.closeModal('password')">
			<div class="modal__content">
				<h2 class="modal__header">
					{{ t('libresign', 'Confirm your signature') }}
				</h2>
				{{ t('libresign', 'Subscription password.') }}
				<NcPasswordField :value.sync="signPassword" type="password" />
				<div class="modal__button-row">
					<NcButton @click="signMethodsStore.closeModal('password')">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="signPassword.length < 3" @click="signWithPassword()">
						{{ t('libresign', 'Sign the document.') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<Draw v-if="signMethodsStore.modal.createSignature"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			type="signature"
			@save="saveSignature"
			@close="signMethodsStore.closeModal('createSignature')" />
		<NcModal v-if="signMethodsStore.modal.createPassword"
			@close="signMethodsStore.closeModal('createPassword')">
			<CreatePassword @password:created="signMethodsStore.setHasSignatureFile"
				@close="signMethodsStore.closeModal('createPassword')" />
		</NcModal>
		<SMSManager v-if="signMethodsStore.modal.sms"
			:phone-number="user?.account?.phoneNumber"
			:uuid="signStore.uuid"
			:file-id="signStore.document.fileId"
			@change="signWithSMSCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="signMethodsStore.closeModal('sms')" />

		<EmailManager v-if="signMethodsStore.modal.emailToken"
			:uuid="signStore.uuid"
			:file-id="signStore.document.fileId"
			@change="signWithEmailToken"
			@close="signMethodsStore.closeModal('emailToken')" />
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { onError } from '../../../helpers/errors.js'
import SMSManager from './ModalSMSManager.vue'
import EmailManager from './ModalEmailManager.vue'
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'
import Draw from '../../../Components/Draw/Draw.vue'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import CreatePassword from '../../../views/CreatePassword.vue'
import { useSignStore } from '../../../store/sign.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'
import { useSignatureElementsStore } from '../../../store/signatureElements.js'

export default {
	name: 'Sign',
	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
		NcPasswordField,
		CreatePassword,
		SMSManager,
		EmailManager,
		PreviewSignature,
		Draw,
	},
	props: {
		docType: {
			type: String,
			required: false,
			default: 'default',
		},
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		const signatureElementsStore = useSignatureElementsStore()
		return { signStore, signMethodsStore, signatureElementsStore }
	},
	data() {
		return {
			loading: true,
			user: {
				account: { uid: '', displayName: '' },
			},
			modalCreatePassword: false,
			modalSignWithPassword: false,
			signPassword: '',
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
			const element = visibleElements
				.map(el => ({
					documentElementId: el.elementId,
					profileFileId: this.signatureElementsStore.signs[el.type].file.fileId,
					url: this.signatureElementsStore.signs[el.type].file.url + '?_t=' + Date.now(),
				}))
			return element
		},
		hasSignatures() {
			return this.elements.length > 0
		},
		needCreateSignature() {
			return this.signStore.document?.visibleElements.length > 0
				&& !this.hasSignatures
		},
		ableToSign() {
			if (this.signMethodsStore.needCreatePassword()) {
				return false
			}
			if (this.needCreateSignature) {
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
			if (getCurrentUser()) {
				try {
					const { data } = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/me'))
					this.user = data
				} catch (err) {
				}
			}
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
			this.signDocument({
				method: 'clickToSign',
			})
		},
		async signWithPassword() {
			return this.signDocument({
				method: 'password',
				token: this.signPassword,
			})
		},
		async signWithSMSCode(token) {
			return this.signDocument({
				method: 'sms',
				token,
			})
		},
		async signWithEmailToken() {
			return this.signDocument({
				method: this.signMethodsStore.settings.emailToken.identifyMethod,
				token: this.signMethodsStore.settings.emailToken.token,
			})
		},
		async signDocument(payload = {}) {
			this.loading = true
			if (this.elements.length > 0) {
				payload.elements = this.elements
					.map(row => ({
						documentElementId: row.documentElementId,
						profileFileId: row.profileFileId,
					}))
			}
			try {
				let url = ''
				if (this.signStore.document.fileId > 0) {
					url = generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}', { fileId: this.signStore.document.fileId })
				} else {
					url = generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}', { uuid: this.signStore.uuid })
				}

				const { data } = await axios.post(url, payload)
				if (data?.action === 3500) { // ACTION_SIGNED
					this.$emit('signed', data)
				}
			} catch (err) {
				onError(err)
			}
			this.loading = false
		},
		confirmSignDocument() {
			if (this.signMethodsStore.needEmailCode()) {
				this.signMethodsStore.showModal('emailToken')
				return
			}
			if (this.needCreateSignature) {
				this.signMethodsStore.showModal('createSignature')
				return
			}
			if (this.signMethodsStore.needSmsCode()) {
				this.signMethodsStore.showModal('sms')
				return
			}
			if (this.signMethodsStore.needCreatePassword()) {
				this.signMethodsStore.showModal('password')
				return
			}
			if (this.signMethodsStore.needSignWithPassword()) {
				this.signMethodsStore.showModal('password')
				return
			}
			if (this.signMethodsStore.needClickToSign()) {
				this.signMethodsStore.showModal('clickToSign')
			}
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
