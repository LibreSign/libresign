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
				<!-- TRANSLATORS: Instruction shown when the signer must verify via a messaging channel. "contact information" here means a phone number used for SMS, WhatsApp, Telegram, Signal, or XMPP. -->
				{{ t('libresign', 'To sign this document, we must verify your identity. Enter your contact information to receive a verification code.') }}
			</p>
			<!-- TRANSLATORS: Label and placeholder for the phone number input field used to receive a verification code via SMS, WhatsApp, Telegram, Signal, or XMPP. "Contact information" here means a phone number, not a generic address book entry. -->
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
			<!-- TRANSLATORS: Label and placeholder for the input field where the signer types the numeric one-time password (OTP) delivered via email, SMS, WhatsApp, Telegram, Signal, or XMPP. "code" here means a short numeric verification code, not source code. -->
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
					<!-- TRANSLATORS: Success message shown after the signer's identity has been confirmed via a numeric one-time password (OTP) delivered through email, SMS, WhatsApp, Telegram, Signal, or XMPP. "identity" here means the system confirmed the signer is who they claim to be. -->
					{{ t('libresign', 'Your identity has been verified.') }}
				</p>
				<p class="signature-ready">
					<!-- TRANSLATORS: Follow-up message shown right after identity verification succeeds, inviting the signer to proceed with signing the document. -->
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
					<!-- TRANSLATORS: Button label. Sends a new numeric one-time password (OTP) to the signer via email, SMS, WhatsApp, Telegram, Signal, or XMPP. "code" here means a short numeric verification code, not source code. -->
					{{ t('libresign', 'Request new code') }}
				</NcButton>
				<NcButton :disabled="!canSendCode"
					type="submit"
					variant="primary"
					@click="sendCode">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					<!-- TRANSLATORS: Button label. Submits the numeric one-time password (OTP) typed by the signer to complete verification. The code was delivered via email, SMS, WhatsApp, Telegram, Signal, or XMPP. "code" here means a short numeric verification code, not source code. -->
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import {
	mdiEmail,
	mdiFormTextboxPassword,
} from '@mdi/js'
import { computed, nextTick, ref, watch } from 'vue'

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

const sanitizePhoneNumber = (val: string) => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

type Mode = 'email' | 'token'

type TokenMethod = 'smsToken' | 'whatsappToken' | 'signalToken' | 'telegramToken' | 'xmppToken'

type EmailTokenSettings = {
	hasConfirmCode?: boolean
	hashOfEmail?: string
	blurredEmail?: string
	identifyMethod?: string
	token?: string
}

type SignMethodSetting = {
	identifyMethod?: string
	needCode?: boolean
	token?: string
}

type SignMethodsSettings = Record<string, SignMethodSetting | undefined> & {
	emailToken?: EmailTokenSettings
}

type SignMethodsStore = {
	settings: SignMethodsSettings
	modal: Record<string, boolean>
	blurredEmail: () => string
	setEmailToken: (token: string) => void
	setHasEmailConfirmCode: (value: boolean) => void
	closeModal: (type: string) => void
}

type SignStore = {
	document: {
		fileId?: number
		signers?: Array<{ me?: boolean; sign_uuid?: string }>
	}
	errors?: Array<{ message?: string }>
}

type RequestCodeError = {
	response?: {
		data?: {
			ocs?: {
				data?: {
					message?: string
				}
			}
			message?: string
		}
	}
	message?: string
}

defineOptions({
	name: 'ModalVerificationCode',
})

const props = withDefaults(defineProps<{
	mode: Mode
	phoneNumber?: string
}>(), {
	phoneNumber: '',
})

const emit = defineEmits<{
	(e: 'change', token: string): void
	(e: 'close'): void
	(e: 'update:phone', value: string): void
}>()

const signStore = useSignStore() as SignStore
const signMethodsStore = useSignMethodsStore() as SignMethodsStore

const loading = ref(false)
const tokenLength = ref(loadState('libresign', 'token_length', 6))
const token = ref('')
const identityVerified = ref(false)
const sendTo = ref('')
const errorMessage = ref('')
const newPhoneNumber = ref(props.phoneNumber || '')
const tokenRequested = ref(false)

const step1Active = computed(() => {
	if (props.mode === 'email') {
		return !signMethodsStore.settings.emailToken?.hasConfirmCode
	}
	return !tokenRequested.value
})

const step2Active = computed(() => !step1Active.value && !identityVerified.value)

const dialogTitle = computed(() => {
	if (step1Active.value) {
		return props.mode === 'email'
			? t('libresign', 'Email verification')
			: t('libresign', 'Identity verification')
	}
	if (!identityVerified.value) {
		return t('libresign', 'Code validation')
	}
	return t('libresign', 'Signature confirmation')
})

const progressText = computed(() => {
	if (step1Active.value) {
		return props.mode === 'email'
			? t('libresign', 'Step 1 of 3 - Email verification')
			: t('libresign', 'Step 1 of 3 - Identity verification')
	}
	if (!identityVerified.value) {
		return t('libresign', 'Step 2 of 3 - Code validation')
	}
	return t('libresign', 'Step 3 of 3 - Signature confirmation')
})

const displayContact = computed(() => {
	if (props.mode === 'email') {
		return signMethodsStore.blurredEmail() || sendTo.value
	}
	return newPhoneNumber.value
})

const codeExplanationText = computed(() => {
	const contact = displayContact.value
	if (props.mode === 'email') {
		return t('libresign', 'A verification code has been sent to: {contact}. Check your email and enter the 6-digit verification code.', { contact })
	}
	return t('libresign', 'A verification code has been sent to: {contact}. Please enter the code to continue.', { contact })
})

const emailIsValid = computed(() => {
	if (!validateEmail(sendTo.value)) {
		return false
	}
	return md5(sendTo.value.toLowerCase()) === signMethodsStore.settings.emailToken?.hashOfEmail
})

const canRequestCode = computed(() => {
	if (props.mode === 'email') {
		return emailIsValid.value
	}
	return newPhoneNumber.value.length > 0
})

const canSendCode = computed(() => !loading.value && token.value.length === tokenLength.value)

const activeTokenMethod = computed<TokenMethod | undefined>(() => {
	const tokenMethods: TokenMethod[] = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
	return tokenMethods.find((method) => Object.hasOwn(signMethodsStore.settings, method))
})

const activeIdentifyMethod = computed(() => signMethodsStore.settings[activeTokenMethod.value ?? '']?.identifyMethod)

watch(token, (newToken) => {
	if (props.mode === 'email') {
		signMethodsStore.setEmailToken(newToken)
	}
})

watch(() => signStore.errors, (errors) => {
	if (errors && errors.length > 0 && loading.value) {
		loading.value = false
	}
}, { deep: true })

function onChangeEmail() {
	if (sendTo.value.length === 0) {
		errorMessage.value = ''
		return
	}
	errorMessage.value = emailIsValid.value ? '' : t('libresign', 'Invalid email')
}

function sanitizeNumber() {
	newPhoneNumber.value = sanitizePhoneNumber(newPhoneNumber.value)
	emit('update:phone', newPhoneNumber.value)
}

function requestNewCode() {
	if (props.mode === 'email') {
		signMethodsStore.setHasEmailConfirmCode(false)
		signMethodsStore.setEmailToken('')
	} else {
		tokenRequested.value = false
		token.value = ''
	}
	identityVerified.value = false
}

async function requestCode() {
	loading.value = true
	if (props.mode === 'email') {
		signMethodsStore.setHasEmailConfirmCode(false)
	} else {
		tokenRequested.value = false
	}

	await nextTick()

	if (!canRequestCode.value) {
		if (props.mode === 'email') {
			onChangeEmail()
		}
		loading.value = false
		return
	}

	try {
		const params = props.mode === 'email'
			? {
				identify: sendTo.value,
				identifyMethod: signMethodsStore.settings.emailToken?.identifyMethod,
				signMethod: 'emailToken',
			}
			: {
				identifyMethod: activeIdentifyMethod.value,
				signMethod: activeTokenMethod.value,
			}

		if (signStore.document.fileId) {
			await axios.post(
				generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}/code', {
					fileId: signStore.document.fileId,
				}),
				params,
			)
		} else {
			const signer = signStore.document.signers?.find((row) => row.me) || {}
			await axios.post(
				generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}/code', {
					uuid: signer.sign_uuid,
				}),
				params,
			)
		}

		if (props.mode === 'email') {
			signMethodsStore.setHasEmailConfirmCode(true)
			errorMessage.value = ''
		} else {
			tokenRequested.value = true
		}
	} catch (error) {
		const err = error as RequestCodeError
		const msg = err.response?.data?.ocs?.data?.message || err.response?.data?.message || err.message
		if (props.mode === 'token' && msg?.includes('Invalid configuration') && activeTokenMethod.value) {
			const method = activeTokenMethod.value.charAt(0).toUpperCase() + activeTokenMethod.value.slice(1)
			showError(t('libresign', '{method} is not configured. Please contact your administrator.', { method }))
		} else {
			showError(msg || t('libresign', 'Unable to send verification code.'))
		}
	} finally {
		loading.value = false
	}
}

function sendCode() {
	if (!canSendCode.value) {
		return
	}
	identityVerified.value = true
}

function signDocument() {
	loading.value = true
	emit('change', token.value)
}

function close() {
	if (props.mode === 'token') {
		signMethodsStore.closeModal('token')
	}
	emit('close')
}

defineExpose({
	signStore,
	signMethodsStore,
	mdiEmail,
	mdiFormTextboxPassword,
	loading,
	tokenLength,
	token,
	identityVerified,
	sendTo,
	errorMessage,
	newPhoneNumber,
	tokenRequested,
	step1Active,
	step2Active,
	dialogTitle,
	progressText,
	codeExplanationText,
	displayContact,
	canRequestCode,
	canSendCode,
	emailIsValid,
	activeTokenMethod,
	activeIdentifyMethod,
	onChangeEmail,
	sanitizeNumber,
	requestNewCode,
	requestCode,
	sendCode,
	signDocument,
	close,
})
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
