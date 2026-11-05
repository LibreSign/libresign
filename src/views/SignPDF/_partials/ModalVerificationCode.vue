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
				{{ emailInstructionText }}
			</p>
			<div v-if="signMethodsStore.blurredEmail().length > 0" class="email">
				{{ signMethodsStore.blurredEmail() }}
			</div>
			<NcTextField v-model="sendTo"
				autofocus
				:disabled="loading"
				:label="emailFieldLabel"
				:placeholder="emailFieldLabel"
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
				{{ tokenInstructionText }}
			</p>
			<NcTextField v-model="newPhoneNumber"
				autofocus
				:disabled="loading"
				name="cellphone"
				:label="contactFieldLabel"
				:placeholder="contactFieldPlaceholder"
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
				autofocus
				:disabled="loading"
				:label="verificationCodeFieldLabel"
				:placeholder="verificationCodeFieldLabel"
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
					<!-- TRANSLATORS Success message shown after the signer's identity has been confirmed via a numeric one-time password (OTP) delivered through email, SMS, WhatsApp, Telegram, Signal, or XMPP. "identity" here means the system confirmed the signer is who they claim to be. -->
					{{ t('libresign', 'Your identity has been verified.') }}
				</p>
				<p class="signature-ready">
					{{ signatureReadyText }}
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
				<!-- TRANSLATORS Button sends a one-time code to the signer's or approver's confirmed contact method. -->
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
					<!-- TRANSLATORS Button label. Sends a new numeric one-time password (OTP) to the signer via email, SMS, WhatsApp, Telegram, Signal, or XMPP. "code" here means a short numeric verification code, not source code. -->
					{{ t('libresign', 'Request new code') }}
				</NcButton>
				<NcButton :disabled="!canSendCode"
					type="submit"
					variant="primary"
					@click="sendCode">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					<!-- TRANSLATORS Button label. Submits the numeric one-time password (OTP) typed by the signer to complete verification. The code was delivered via email, SMS, WhatsApp, Telegram, Signal, or XMPP. "code" here means a short numeric verification code, not source code. -->
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
				{{ signActionLabel }}
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
import { useSignatureElementsStore } from '../../../store/signatureElements.js'
import { useSignMethodsStore } from '../../../store/signMethods.js'
import { getSigningRouteUuid } from '../../../utils/signRequestUuid.ts'
import { validateEmail } from '../../../utils/validators.js'

defineOptions({
	name: 'ModalVerificationCode',
})

const props = withDefaults(defineProps<{
	mode: Mode
	phoneNumber?: string
	purpose?: Purpose
	approvalContext?: boolean
}>(), {
	phoneNumber: '',
	purpose: 'sign',
	approvalContext: false,
})

const emit = defineEmits<{
	(e: 'change', token: string): void
	(e: 'close'): void
	(e: 'unlocked'): void
	(e: 'update:phone', value: string): void
}>()

const sanitizePhoneNumber = (val: string) => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

type Mode = 'email' | 'token'

// savedSignature: the code only unlocks the saved signature of an email signer
type Purpose = 'sign' | 'savedSignature'

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
		uuid?: string | null
		signers?: Array<{ me?: boolean; sign_request_uuid?: string }>
		settings?: { isApprover?: boolean } | null
	}
	errors?: Array<{ message?: string }>
	buildRequestCodeUrl: (signRequestUuid: string) => string
}

type SignatureElementsStore = {
	error: string | { message?: string } | null
	useSavedSignature: (token: string) => Promise<boolean>
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

// TRANSLATORS Label of the email address field used for account verification.
const emailFieldLabel = t('libresign', 'Email')
// TRANSLATORS Label for the phone number used to receive an SMS or messaging verification code.
const contactFieldLabel = t('libresign', 'Contact information')
// TRANSLATORS Placeholder requesting the phone number for a verification code, not a postal address.
const contactFieldPlaceholder = t('libresign', 'Enter your contact information')
// TRANSLATORS Label of the input field for a numeric one-time verification code.
const verificationCodeFieldLabel = t('libresign', 'Enter your code')

const isApprovalContext = computed(() => props.approvalContext && props.purpose === 'sign')
const emailInstructionText = computed(() => {
	if (isApprovalContext.value) {
		// TRANSLATORS Email verification instructions for an approver; use their own Nextcloud account address, not the external signer's.
		return t('libresign', 'Confirm the email address associated with your own account. We will send a verification code to verify you as the approver.')
	}
	// TRANSLATORS Email verification instructions for an ordinary signer who received a signature request.
	return t('libresign', 'To verify your identity, enter the same email address where you received the signature request. We will send a verification code to this address.')
})
const tokenInstructionText = computed(() => {
	if (isApprovalContext.value) {
		// TRANSLATORS Phone-based verification for the approver's own account before recording document approval.
		return t('libresign', 'To sign and approve this document, verify your own account using a confirmation code sent to your contact information.')
	}
	// TRANSLATORS Phone-based verification for an ordinary signer before signing their document.
	return t('libresign', 'To sign this document, we must verify your identity. Enter your contact information to receive a verification code.')
})
const signatureReadyText = computed(() => {
	if (isApprovalContext.value) {
		// TRANSLATORS After the approver's verification code is accepted, invites them to sign the submitted document to record approval.
		return t('libresign', 'You can now sign to record your approval of the document.')
	}
	// TRANSLATORS After an ordinary signer's verification code is accepted, invites them to sign their document.
	return t('libresign', 'You can now sign the document.')
})
const signActionLabel = computed(() => {
	if (isApprovalContext.value) {
		// TRANSLATORS Final action for an approved reviewer to sign the submitted document to record approval.
		return t('libresign', 'Sign and approve document')
	}
	// TRANSLATORS Final action for an ordinary signer after entering their verification code.
	return t('libresign', 'Sign document')
})

const signStore = useSignStore() as SignStore
const signMethodsStore = useSignMethodsStore() as SignMethodsStore

const loading = ref(false)
const tokenLength = ref(loadState('libresign', 'token_length', 6))
// Reuse a code already validated to unlock the saved signature, so the signer types it once
const token = ref(props.mode === 'email' ? (signMethodsStore.settings.emailToken?.token ?? '') : '')
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
		if (props.mode === 'email') {
			// TRANSLATORS Dialog title for verifying the signer's or approver's email address with a code.
			return t('libresign', 'Email verification')
		}
		// TRANSLATORS Dialog title for verifying a signer's or approver's phone contact with a code.
		return t('libresign', 'Identity verification')
	}
	if (!identityVerified.value) {
		// TRANSLATORS Dialog title while the signer or approver enters a received verification code.
		return t('libresign', 'Code validation')
	}
	// TRANSLATORS Dialog title after a verification code has been accepted and the user can proceed to sign.
	return t('libresign', 'Signature confirmation')
})

const progressText = computed(() => {
	if (props.purpose === 'savedSignature') {
		if (step1Active.value) {
			// TRANSLATORS First of two steps when an email signer requests a code to reuse their saved signature.
			return t('libresign', 'Step 1 of 2 - Email verification')
		}
		// TRANSLATORS Second of two steps when an email signer enters the code to reuse their saved signature.
		return t('libresign', 'Step 2 of 2 - Code validation')
	}
	if (step1Active.value) {
		if (props.mode === 'email') {
			// TRANSLATORS First of three signing steps: the signer's or approver's email address is verified.
			return t('libresign', 'Step 1 of 3 - Email verification')
		}
		// TRANSLATORS First of three signing steps: the signer's or approver's phone contact is verified.
		return t('libresign', 'Step 1 of 3 - Identity verification')
	}
	if (!identityVerified.value) {
		// TRANSLATORS Second of three signing steps: enter the one-time code received by email or messaging.
		return t('libresign', 'Step 2 of 3 - Code validation')
	}
	// TRANSLATORS Third of three signing steps: confirm the digital signature or document approval.
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
		// TRANSLATORS {contact} is the masked destination email address that received the six-digit one-time code.
		return t('libresign', 'A verification code has been sent to: {contact}. Check your email and enter the 6-digit verification code.', { contact })
	}
	// TRANSLATORS {contact} is the phone number receiving the one-time verification code.
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
	// TRANSLATORS Error when the entered email address does not match the email expected for verification.
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
			// Same route/context decision as signing (Sign.vue).
			const signRequestUuid = getSigningRouteUuid(signStore.document)
			if (!signRequestUuid) {
				// TRANSLATORS Error when a submitted document cannot be found while requesting a verification code.
				throw new Error(t('libresign', 'Document not found'))
			}
			await axios.post(signStore.buildRequestCodeUrl(signRequestUuid), params)
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
		// TRANSLATORS Error if the verification code cannot be sent to the email or phone contact.
		showError(msg || t('libresign', 'Unable to send verification code.'))
	} finally {
		loading.value = false
	}
}

async function sendCode() {
	if (!canSendCode.value) {
		return
	}
	if (props.purpose === 'savedSignature') {
		await useSavedSignature()
		return
	}
	identityVerified.value = true
}

async function useSavedSignature() {
	const signatureElementsStore = useSignatureElementsStore() as unknown as SignatureElementsStore
	loading.value = true
	const unlocked = await signatureElementsStore.useSavedSignature(token.value)
	loading.value = false
	if (unlocked) {
		emit('unlocked')
		return
	}
	const error = signatureElementsStore.error
	const message = typeof error === 'object' ? error?.message : ''
	// TRANSLATORS Error shown when the code typed to reuse a saved signature is not accepted.
	showError(message || t('libresign', 'Invalid code.'))
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
	isApprovalContext,
	emailInstructionText,
	tokenInstructionText,
	signatureReadyText,
	signActionLabel,
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
	useSavedSignature,
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
