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
			<div v-else-if="signMethodsStore.needCreatePassword()">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
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
					variant="primary"
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
		<NcDialog v-if="signMethodsStore.modal.clickToSign"
			:no-close="loading"
			:name="t('libresign', 'Confirm')"
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
			<ManagePassword v-if="showManagePassword" />
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
		<CreatePassword @password:created="signMethodsStore.setHasSignatureFile" />
		<SMSManager v-if="signMethodsStore.modal.sms"
			:phone-number="user?.account?.phoneNumber"
			@change="signWithSMSCode"
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import EmailManager from './ModalEmailManager.vue'
import SMSManager from './ModalSMSManager.vue'
import Draw from '../../../Components/Draw/Draw.vue'
import Signatures from '../../../views/Account/partials/Signatures.vue'
import CreatePassword from '../../../views/CreatePassword.vue'
import ManagePassword from '../../Account/partials/ManagePassword.vue'

import { useSidebarStore } from '../../../store/sidebar.js'
import { useSignStore } from '../../../store/sign.js'
import { useSignatureElementsStore } from '../../../store/signatureElements.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'

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
		SMSManager,
		EmailManager,
		Signatures,
		Draw,
		ManagePassword,
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		const signatureElementsStore = useSignatureElementsStore()
		const sidebarStore = useSidebarStore()
		return { signStore, signMethodsStore, signatureElementsStore, sidebarStore }
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
			const signer = this.signStore.document?.signers.find(row => row.me) || {}

			if (!signer.signRequestId) {
				return []
			}

			const visibleElements = (signer.visibleElements || [])
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
			return !!signer.signRequestId
				&& signer.visibleElements.length > 0
				&& !this.hasSignatures
				&& this.canCreateSignature
		},
		canCreateSignature() {
			return getCapabilities()?.libresign?.config?.['sign-elements']?.['can-create-signature'] === true
		},
		ableToSign() {
			if (this.signMethodsStore.needCreatePassword()) {
				return false
			}
			if (this.needCreateSignature) {
				return false
			}
			if (this.signStore.errors.length > 0) {
				return false
			}
			return true
		},
		signRequestUuid() {
			const signer = this.signStore.document.signers.find(row => row.me) || {}
			return signer.sign_uuid
		},
	},
	mounted() {
		this.loading = true
		this.signatureElementsStore.signRequestUuid = this.signRequestUuid
		this.signatureElementsStore.loadSignatures()

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
		saveSignature() {
			if (this.signatureElementsStore.success.length) {
				showSuccess(this.signatureElementsStore.success)
			} else if (this.signatureElementsStore.error.length) {
				showError(this.signatureElementsStore.error)
			}
			this.signMethodsStore.closeModal('createSignature')
		},
		async signWithClick() {
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
		async signWithSMSCode(token) {
			await this.signDocument({
				method: 'sms',
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
			let url = ''
			if (this.signStore.document.fileId > 0) {
				url = generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{nodeId}', { fileId: this.signStore.document.nodeId })
			} else {
				url = generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}', { uuid: this.signRequestUuid })
			}

			await axios.post(url, payload)
				.then(({ data }) => {
					if (data.ocs.data.action === 3500) { // ACTION_SIGNED
						this.signMethodsStore.closeModal(payload.method)
						this.sidebarStore.hideSidebar()
						this.$emit('signed', data.ocs.data)
					}
				})
				.catch((err) => {
					this.errors = err.response?.data?.ocs?.data?.errors
				})
			this.loading = false
		},
		confirmSignDocument() {
			this.errors = []
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
				this.signMethodsStore.showModal('createPassword')
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
