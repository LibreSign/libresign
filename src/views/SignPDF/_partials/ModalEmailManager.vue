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

		<!-- Email verification step (Step 1) -->
		<div v-if="!signMethodsStore.settings.emailToken.hasConfirmCode" class="step-content">
			<p class="step-explanation">
				{{ t('libresign', 'To sign this document, we must verify your identity. Enter your email address to receive a verification code.') }}
			</p>
			<div v-if="signMethodsStore.blurredEmail().length > 0" class="email">
				{{ signMethodsStore.blurredEmail() }}
			</div>
			<NcTextField v-model="sendTo"
				:disabled="loading"
				:label="t('libresign', 'Email')"
				:placeholder="t('libresign', 'Email')"
				:helper-text="errorMessage"
				:error="errorMessage.length > 0"
				@keyup.enter="requestCode"
				@input="onChangeEmail">
				<NcIconSvgWrapper :path="mdiEmail" :size="20" />
			</NcTextField>
		</div>

		<!-- Code validation step (Step 2) -->
		<div v-if="signMethodsStore.settings.emailToken.hasConfirmCode && !identityVerified" class="step-content">
			<p class="step-explanation">
				{{ codeExplanationText }}
			</p>
			<div class="email-display">
				{{ displayEmail }}
			</div>
			<NcTextField v-model="token"
				maxlength="6"
				:disabled="loading"
				:label="t('libresign', 'Enter your code')"
				:placeholder="t('libresign', 'Enter your code')"
				:helper-text="errorMessage"
				:error="errorMessage.length > 0"
				name="code"
				type="text"
				@keyup.enter="sendCode">
				<NcIconSvgWrapper :path="mdiFormTextboxPassword" :size="20" />
			</NcTextField>
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
			<!-- Step 1: Email verification -->
			<NcButton v-if="!signMethodsStore.settings.emailToken.hasConfirmCode"
				:disabled="loading || !canRequestCode"
				type="submit"
				variant="primary"
				@click="requestCode">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Send verification code') }}
			</NcButton>

			<!-- Step 2: Code validation -->
			<template v-else-if="!identityVerified">
				<NcButton :disabled="loading && !canRequestCode"
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
import {
	mdiEmail,
	mdiFormTextboxPassword,
} from '@mdi/js'

import md5 from 'blueimp-md5'


import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
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

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalEmailManager',
	components: {
		NcDialog,
		NcTextField,
		NcButton,
		NcLoadingIcon,
		NcIconSvgWrapper,
	},
	setup() {
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		return {
			signStore,
			signMethodsStore,
			mdiFormTextboxPassword,
			mdiEmail,
		}
	},
	data: () => ({
		loading: false,
		tokenLength: loadState('libresign', 'token_length', 6),
		errorMessage: '',
		token: '',
		sendTo: '',
		identityVerified: false,
	}),
	computed: {
		dialogTitle() {
			if (!this.signMethodsStore.settings.emailToken.hasConfirmCode) {
				return t('libresign', 'Email verification')
			}
			if (!this.identityVerified) {
				return t('libresign', 'Code validation')
			}
			return t('libresign', 'Signature confirmation')
		},
		progressText() {
			if (!this.signMethodsStore.settings.emailToken.hasConfirmCode) {
				return t('libresign', 'Step 1 of 3 - Email verification')
			}
			if (!this.identityVerified) {
				return t('libresign', 'Step 2 of 3 - Code validation')
			}
			return t('libresign', 'Step 3 of 3 - Signature confirmation')
		},
		codeExplanationText() {
			const email = this.displayEmail
			return t('libresign', 'A verification code has been sent to: {email}. Please enter the 6-digit code below.', { email })
		},
		displayEmail() {
			return this.signMethodsStore.blurredEmail() || this.sendTo
		},
		canRequestCode() {
			if (validateEmail(this.sendTo)) {
				if (md5(this.sendTo.toLowerCase()) !== this.signMethodsStore.settings.emailToken.hashOfEmail) {
					return false
				}
				return true
			}
			return false
		},
		canSendCode() {
			return this.signMethodsStore.settings.emailToken.hasConfirmCode
				&& !this.loading
				&& this.token.length === this.tokenLength
		},
	},
	watch: {
		token(token) {
			this.signMethodsStore.setEmailToken(token)
		},
	},
	methods: {
		t,
		onChangeEmail() {
			if (!validateEmail(this.sendTo) || md5(this.sendTo) !== this.signMethodsStore.settings.emailToken.hashOfEmail) {
				this.errorMessage = t('libresign', 'Invalid email')
				return
			}

			this.errorMessage = ''
		},
		requestNewCode() {
			this.signMethodsStore.setHasEmailConfirmCode(false)
			this.signMethodsStore.setEmailToken('')
			this.identityVerified = false
		},
		async requestCode() {
			this.loading = true
			this.signMethodsStore.setHasEmailConfirmCode(false)

			await this.$nextTick()
			if (!this.canRequestCode) {
				this.onChangeEmail()
				this.loading = false
				return
			}

			try {
				if (this.signStore.document.fileId) {
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
							fileId: this.signStore.document.fileId,
						}),
						{
							identify: this.sendTo,
							identifyMethod: this.signMethodsStore.settings.emailToken.identifyMethod,
							signMethod: 'emailToken',
		},
					)
					showSuccess(data.ocs.data.message)
				} else {
					const signer = this.signStore.document.signers.find(row => row.me) || {}
					const { data } = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', {
							uuid: signer.sign_uuid,
						}),
						{
							identify: this.sendTo,
							identifyMethod: this.signMethodsStore.settings.emailToken.identifyMethod,
							signMethod: 'emailToken',
		},
					)
					showSuccess(data.ocs.data.message)
				}
				this.signMethodsStore.setHasEmailConfirmCode(true)
			} catch (err) {
				showError(err.response.data.ocs.data.message)
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
			this.$emit('change')
			this.close()
		},
		close() {
			this.$emit('close')
		},
		sanitizeNumber() {
			this.phoneNumber = sanitizeNumber(this.phoneNumber)
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

.email-display {
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
