<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog size="normal"
		:no-close="loading"
		:name="dialogTitle"
		@closing="close">
		<!-- Progress indicator -->
		<div class="progress-indicator">
			{{ progressText }}
		</div>

		<!-- Step 1: Email mode -->
		<div v-if="mode === 'email' && step1Active" class="step-content">
			<p class="step-explanation">
				{{ t('libresign', 'To verify your identity, enter the same email address where you received the signature request. We will send a verification code to this address.') }}
			</p>
			<div v-if="signMethodsStore.blurredEmail().length > 0" class="email">
				{{ signMethodsStore.blurredEmail() }}
			</div>
			<NcTextField v-model="sendTo"
				:disabled="loading"
				:label="t('libresign', 'Email')"
				:placeholder="t('libresign', 'Email')"
				:helper-text="emailIsValid ? '' : errorMessage"
				:error="!emailIsValid && errorMessage.length > 0"
				:success="emailIsValid"
				@keyup.enter="requestCode"
				@input="onChangeEmail">
				<NcIconSvgWrapper :path="mdiEmail" :size="20" />
			</NcTextField>
		</div>

		<!-- Step 1: Token mode -->
		<div v-else-if="mode === 'token' && step1Active" class="step-content">
			<p class="step-explanation">
				{{ t('libresign', 'To sign this document, we must verify your identity. Enter your contact information to receive a verification code.') }}
			</p>
			<NcTextField v-model="newPhoneNumber"
				:disabled="loading"
				name="cellphone"
				:label="t('libresign', 'Contact information')"
				:placeholder="t('libresign', 'Enter your contact information')"
				type="tel"
				@change="sanitizeNumber" />
		</div>

		<!-- Step 2: Code validation (common) -->
		<div v-if="step2Active" class="step-content">
			<p class="step-explanation">
				{{ codeExplanationText }}
			</p>
			<div class="contact-display">
				{{ displayContact }}
			</div>
			<NcTextField v-model="token"
				:disabled="loading"
				:label="t('libresign', 'Enter your code')"
				:placeholder="t('libresign', 'Enter your code')"
				name="code"
				type="text"
				@keyup.enter="sendCode">
				<NcIconSvgWrapper v-if="mode === 'email'" :path="mdiFormTextboxPassword" :size="20" />
			</NcTextField>
		</div>

		<!-- Step 3: Signature confirmation (common) -->
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
			<!-- Step 1 action (common button, mode-specific disabled logic) -->
			<NcButton v-if="step1Active"
				:disabled="loading || !canRequestCode"
				type="submit"
				variant="primary"
				@click="requestCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Send verification code') }}
			</NcButton>

			<!-- Step 2 actions (common) -->
			<template v-else-if="!identityVerified">
				<NcButton :disabled="loading"
					type="submit"
					@click="requestNewCode">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Request new code') }}
				</NcButton>
				<NcButton :disabled="!canSendCode"
					type="submit"
					variant="primary"
					@click="sendCode">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Validate code') }}
				</NcButton>
			</template>

			<!-- Step 3 action (common) -->
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
	</NcDialog>Step 1
</template>

<script>
import { t } from '@nextcloud/l10n'
import {
	mdiEmail,
	mdiFormTextboxPassword,
} from '@mdi/js'

import md5 from 'blueimp-md5'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { useSignStore } from '../../../store/sign.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'
import { validateEmail } from '../../../utils/validators.js'

const sanitizePhoneNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalVerificationCode',
	emits: ['change', 'close', 'update:phone'],
	components: {
		NcDialog,
		NcTextField,
		NcButton,
		NcLoadingIcon,
		NcIconSvgWrapper,
	},
	props: {
		mode: {
			type: String,
			required: true,
			validator: (val) => ['email', 'token'].includes(val),
		},
		phoneNumber: {
			type: String,
			default: '',
		},
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		return {
			signStore,
			signMethodsStore,
			mdiEmail,
			mdiFormTextboxPassword,
		}
	},
	data() {
		return {
			loading: false,
			tokenLength: loadState('libresign', 'token_length', 6),
			token: '',
			identityVerified: false,
			// email-specific
			sendTo: '',
			errorMessage: '',
			// token-specific
			newPhoneNumber: this.phoneNumber || '',
			tokenRequested: false,
		}
	},
	computed: {
		step1Active() {
			if (this.mode === 'email') {
				return !this.signMethodsStore.settings.emailToken?.hasConfirmCode
			}
			return !this.tokenRequested
		},
		step2Active() {
			return !this.step1Active && !this.identityVerified
		},
		dialogTitle() {
			if (this.step1Active) {
				return this.mode === 'email'
					? t('libresign', 'Email verification')
					: t('libresign', 'Identity verification')
			}
			if (!this.identityVerified) {
				return t('libresign', 'Code validation')
			}
			return t('libresign', 'Signature confirmation')
		},
		progressText() {
			if (this.step1Active) {
				return this.mode === 'email'
					? t('libresign', 'Step 1 of 3 - Email verification')
					: t('libresign', 'Step 1 of 3 - Identity verification')
			}
			if (!this.identityVerified) {
				return t('libresign', 'Step 2 of 3 - Code validation')
			}
			return t('libresign', 'Step 3 of 3 - Signature confirmation')
		},
		codeExplanationText() {
			const contact = this.displayContact
			if (this.mode === 'email') {
				return t('libresign', 'A verification code has been sent to: {contact}. Check your email and enter the 6-digit verification code.', { contact })
			}
			return t('libresign', 'A verification code has been sent to: {contact}. Please enter the code to continue.', { contact })
		},
		displayContact() {
			if (this.mode === 'email') {
				return this.signMethodsStore.blurredEmail() || this.sendTo
			}
			return this.newPhoneNumber
		},
		canRequestCode() {
			if (this.mode === 'email') {
				return this.emailIsValid
			}
			return this.newPhoneNumber.length > 0
		},
		canSendCode() {
			return !this.loading && this.token.length === this.tokenLength
		},
		// --- email-specific ---
		emailIsValid() {
			if (!validateEmail(this.sendTo)) {
				return false
			}
			return md5(this.sendTo.toLowerCase()) === this.signMethodsStore.settings.emailToken?.hashOfEmail
		},
		// --- token-specific ---
		activeTokenMethod() {
			const tokenMethods = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
			return tokenMethods.find(method =>
				Object.hasOwn(this.signMethodsStore.settings, method)
			)
		},
		activeIdentifyMethod() {
			return this.signMethodsStore.settings[this.activeTokenMethod]?.identifyMethod
		},
	},
	watch: {
		token(token) {
			if (this.mode === 'email') {
				this.signMethodsStore.setEmailToken(token)
			}
		},
		'signStore.errors'(errors) {
			if (errors && errors.length > 0 && this.loading) {
				this.loading = false
			}
		},
	},
	methods: {
		t,
		// --- email-specific ---
		onChangeEmail() {
			if (this.sendTo.length === 0) {
				this.errorMessage = ''
				return
			}
			this.errorMessage = this.emailIsValid ? '' : t('libresign', 'Invalid email')
		},
		// --- token-specific ---
		sanitizeNumber() {
			this.newPhoneNumber = sanitizePhoneNumber(this.newPhoneNumber)
		},
		// --- common ---
		requestNewCode() {
			if (this.mode === 'email') {
				this.signMethodsStore.setHasEmailConfirmCode(false)
				this.signMethodsStore.setEmailToken('')
			} else {
				this.tokenRequested = false
				this.token = ''
			}
			this.identityVerified = false
		},
		async requestCode() {
			this.loading = true
			if (this.mode === 'email') {
				this.signMethodsStore.setHasEmailConfirmCode(false)
			} else {
				this.tokenRequested = false
			}

			await this.$nextTick()

			if (!this.canRequestCode) {
				if (this.mode === 'email') {
					this.onChangeEmail()
				}
				this.loading = false
				return
			}

			try {
				const params = this.mode === 'email'
					? {
						identify: this.sendTo,
						identifyMethod: this.signMethodsStore.settings.emailToken.identifyMethod,
						signMethod: 'emailToken',
					}
					: {
						identifyMethod: this.activeIdentifyMethod,
						signMethod: this.activeTokenMethod,
					}

				if (this.signStore.document.fileId) {
					await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
							fileId: this.signStore.document.fileId,
						}),
						params,
					)
				} else {
					const signer = this.signStore.document.signers.find(row => row.me) || {}
					await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', {
							uuid: signer.sign_uuid,
						}),
						params,
					)
				}

				if (this.mode === 'email') {
					this.signMethodsStore.setHasEmailConfirmCode(true)
					this.errorMessage = ''
				} else {
					this.tokenRequested = true
				}
			} catch (err) {
				const msg = err.response?.data?.ocs?.data?.message || err.response?.data?.message || err.message
				if (this.mode === 'token' && msg?.includes('Invalid configuration')) {
					const method = this.activeTokenMethod.charAt(0).toUpperCase() + this.activeTokenMethod.slice(1)
					showError(t('libresign', '{method} is not configured. Please contact your administrator.', { method }))
				} else {
					showError(msg)
				}
			} finally {
				this.loading = false
			}
		},
		sendCode() {
			if (!this.canSendCode) {
				return
			}
			this.identityVerified = true
		},
		signDocument() {
			this.loading = true
			this.$emit('change', this.token)
		},
		close() {
			if (this.mode === 'token') {
				this.signMethodsStore.closeModal('token')
			}
			this.$emit('close')
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

.email {
	font-family: monospace;
	text-align: center;
	margin-bottom: 12px;
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
