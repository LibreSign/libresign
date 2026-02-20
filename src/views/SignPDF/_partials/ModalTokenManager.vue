<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="signMethodsStore.modal.token"
		:name="dialogTitle"
		@closing="signMethodsStore.closeModal('token')">
		<!-- Progress indicator -->
		<div class="progress-indicator">
			{{ progressText }}
		</div>

		<!-- Contact input step (Step 1) -->
		<div v-if="!tokenRequested" class="step-content">
			<p class="step-explanation">
				{{ t('libresign', 'To sign this document, we must verify your identity. Enter your contact information to receive a verification code.') }}
			</p>
			<div class="store-number">
				<NcTextField v-model="newPhoneNumber"
					:disabled="loading"
					name="cellphone"
					:label="t('libresign', 'Contact information')"
					:placeholder="t('libresign', 'Enter your contact information')"
					type="tel"
					@change="sanitizeNumber" />
			</div>
		</div>

		<!-- Code validation step (Step 2) -->
		<div v-if="tokenRequested && !identityVerified" class="step-content">
			<p class="step-explanation">
				{{ codeExplanationText }}
			</p>
			<div class="contact-display">
				{{ newPhoneNumber }}
			</div>
			<NcTextField v-model="token"
				:disabled="loading"
				:label="t('libresign', 'Enter your code')"
				:placeholder="t('libresign', 'Enter your code')"
				name="code"
				type="text"
				@keyup.enter="sendCode" />
		</div>

		<!-- Signature confirmation step (Step 3) -->
		<div v-else-if="identityVerified" class="step-content">
			<div class="verification-success">
				<p class="verification-message">
					{{ t('libresign', 'Your identity has been verified.') }}
				</p>
				<p class="signature-ready">
					{{ t('libresign', 'You can now sign the document.') }}
				</p>
			</div>
		</div>
		<template #actions>
			<!-- Step 1: Phone number verification -->
			<NcButton v-if="!tokenRequested"
				:disabled="loading || newPhoneNumber.length < 10"
				variant="primary"
				@click="requestCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Send verification code') }}
			</NcButton>

			<!-- Step 2: Code validation -->
			<template v-else-if="!identityVerified">
				<NcButton :disabled="loading"
					@click="requestNewCode">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Request new code') }}
				</NcButton>
				<NcButton :disabled="loading || token.length < 3"
					variant="primary"
					@click="sendCode">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Validate code') }}
				</NcButton>
			</template>

			<!-- Step 3: Signature confirmation -->
			<NcButton v-else
				:disabled="loading"
				type="submit"
				variant="primary"
				@click="signDocument">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Sign document') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { settingsService } from '../../../services/settingsService'
import { useSignStore } from '../../../store/sign.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalTokenManager',
	components: {
		NcDialog,
		NcTextField,
		NcButton,
		NcLoadingIcon,
	},
	props: {
		phoneNumber: {
			type: String,
			required: false,
			default: '',
		},
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		return { signStore, signMethodsStore }
	},
	data() {
		return {
			token: '',
			newPhoneNumber: this.phoneNumber || '',
			tokenRequested: false,
			loading: false,
			identityVerified: false,
		}
	},
	computed: {
		dialogTitle() {
			if (!this.tokenRequested) {
				return t('libresign', 'Identity verification')
			}
			if (!this.identityVerified) {
				return t('libresign', 'Code validation')
			}
			return t('libresign', 'Signature confirmation')
		},
		progressText() {
			if (!this.tokenRequested) {
				return t('libresign', 'Step 1 of 3 - Identity verification')
			}
			if (!this.identityVerified) {
				return t('libresign', 'Step 2 of 3 - Code validation')
			}
			return t('libresign', 'Step 3 of 3 - Signature confirmation')
		},
		codeExplanationText() {
			const contact = this.newPhoneNumber
			return t('libresign', 'A verification code has been sent to: {contact}. Please enter the code below.', { contact })
		},
		activeTokenMethod() {
			const tokenMethods = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
			return tokenMethods.find(method =>
				Object.hasOwn(this.signMethodsStore.settings, method)
			)
		},
		activeIdentifyMethod() {
			const signatureMethodData = this.signMethodsStore.settings[this.activeTokenMethod]
			return signatureMethodData.identifyMethod
		},
	},
	methods: {
		t,
		requestNewCode() {
			this.tokenRequested = false
			this.token = ''
			this.identityVerified = false
		},
		async saveNumber() {
			this.loading = true
			this.sanitizeNumber()

			await this.$nextTick()

			try {
				await confirmPassword()
				const { data: { phone }, success } = await settingsService.saveUserNumber(this.newPhoneNumber)

				this.newPhoneNumber = phone
				this.$emit('update:phone', phone)

				if (!success) {
					showError(t('libresign', 'Review the entered number.'))
					return
				}
				showSuccess(t('libresign', 'Phone stored.'))
			} catch (err) {
				showError(err.response.data.ocs.data.message)
			} finally {
				this.loading = false
			}
		},
		async requestCode() {
			this.loading = true
			this.tokenRequested = false

			await this.$nextTick()

			try {
				const params = {
					identifyMethod: this.activeIdentifyMethod,
					signMethod: this.activeTokenMethod,
				}

				if (this.signStore.document.fileId) {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
							fileId: this.signStore.document.fileId,
						}),
						params
					)
					showSuccess(data.ocs.data.message)
				} else {
				const signer = this.signStore.document.signers.find(row => row.me) || {}
				const { data } = await axios.post(
					generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', {
						uuid: signer.sign_uuid,
					}),
					params
				)
					showSuccess(data.ocs.data.message)
				}
				this.tokenRequested = true
			} catch (err) {
				const errorMessage = err.response?.data?.ocs?.data?.message || err.response?.data?.message || err.message

				if (errorMessage && errorMessage.includes('Invalid configuration')) {
					const method = this.activeTokenMethod.charAt(0).toUpperCase() + this.activeTokenMethod.slice(1)
					showError(t('libresign', '{method} is not configured. Please contact your administrator.', { method }))
				} else {
					showError(errorMessage)
				}
			} finally {
				this.loading = false
			}
		},
		sendCode() {
			this.identityVerified = true
		},
		signDocument() {
			this.$emit('change', this.token)

			this.$nextTick(() => {
				this.close()
			})
		},
		close() {
			this.$emit('close')
		},
		sanitizeNumber() {
			this.newPhoneNumber = sanitizeNumber(this.newPhoneNumber)
		},
	},
}
</script>

<style lang="scss" scoped>
.progress-indicator {
	font-weight: bold;
	color: var(--color-primary-element);
	text-align: center;
	margin-bottom: 16px;
	padding: 8px;
	background-color: var(--color-primary-element-light);
	border-radius: var(--border-radius-large);
}

.step-content {
	.step-explanation {
		margin-bottom: 16px;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}
}

.contact-display {
	font-family: monospace;
	text-align: center;
	font-weight: bold;
	margin: 12px 0;
	padding: 8px;
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.code-request, .store-number {
	width: 100%;
	display: flex;
	flex-direction: column;
}

.code-request input {
	font-size: 1.3em;
}

.verification-success {
	text-align: center;
	padding: 20px 0;

	.verification-message {
		font-size: 1.1em;
		font-weight: 600;
		color: var(--color-text);
		margin-bottom: 12px;
	}

	.signature-ready {
		font-size: 1em;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}
}
</style>
