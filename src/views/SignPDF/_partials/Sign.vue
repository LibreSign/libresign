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
			<div v-if="needCreateSignature" class="no-signature-warning">
				<p>
					{{ t('libresign', 'You do not have any signature defined.') }}
				</p>
				<NcButton :wide="true" :disabled="loading" variant="primary" @click="openModal('createSignature')">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCertificate()">
				<p>
					{{ t('libresign', 'You need to upload your certificate to sign the document.') }}
				</p>
				<NcButton :wide="true" :disabled="loading" variant="primary" @click="openModal('uploadCertificate')">
					{{ t('libresign', 'Upload certificate') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCreatePassword()">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton :wide="true" :disabled="loading" variant="primary" @click="openModal('createPassword')">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needIdentificationDocuments" class="no-identification-warning">
				<Documents :sign-request-uuid="signRequestUuid" />
			</div>
			<NcButton v-else-if="ableToSign" :wide="true" :disabled="loading" variant="primary"
				@click="confirmSignDocument">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('libresign', 'Sign the document.') }}
			</NcButton>
			<div v-else>
				<p>
					{{ t('libresign', 'Unable to sign.') }}
				</p>
			</div>
		</div>
		<NcDialog v-if="signMethodsStore.modal.clickToSign" :no-close="loading" :name="t('libresign', 'Sign document')"
			size="small" dialog-classes="libresign-dialog" @closing="signMethodsStore.closeModal('clickToSign')">
			<NcNoteCard v-for="(error, index) in signStore.errors" :key="index" :heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message" :use-markdown="true" />
			</NcNoteCard>

			<p class="confirmation-text">
				{{ t('libresign', 'Confirm that you want to sign this document.') }}
			</p>

			<template #actions>
				<NcButton :disabled="loading" @click="signMethodsStore.closeModal('clickToSign')">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary" :disabled="loading" @click="signWithClick">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Sign document') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-if="signMethodsStore.modal.password" :no-close="loading" :name="t('libresign', 'Sign document')"
			size="small" dialog-classes="libresign-dialog" @closing="onCloseConfirmPassword">
			<NcNoteCard v-for="(error, index) in signStore.errors" :key="index" :heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message" :use-markdown="true" />
			</NcNoteCard>

			<p class="confirmation-text">
				{{ t('libresign', 'Enter your signature password to sign the document.') }}
			</p>

			<form @submit.prevent="signWithPassword()">
				<NcPasswordField v-model="signPassword" :label="t('libresign', 'Signature password')" type="password" />
			</form>
			<a id="lost-password" @click="toggleManagePassword">{{ t('libresign', 'Forgot password?') }}</a>
			<ManagePassword v-if="showManagePassword" @certificate:uploaded="onSignatureFileCreated" />
			<template #actions>
				<NcButton :disabled="signPassword.length < 3 || loading" type="submit" variant="primary"
					@click="signWithPassword()">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Sign document') }}
				</NcButton>
			</template>
		</NcDialog>
		<Draw v-if="signMethodsStore.modal.createSignature" :draw-editor="true" :text-editor="true" :file-editor="true"
			:sign-request-uuid="signRequestUuid" type="signature" @save="saveSignature"
			@close="signMethodsStore.closeModal('createSignature')" />
		<CreatePassword @password:created="onSignatureFileCreated" />
		<UploadCertificate :useModal="true" :errors="signStore.errors" @certificate:uploaded="onSignatureFileCreated" />
		<ModalVerificationCode v-if="signMethodsStore.modal.token" mode="token"
			:phone-number="user.settings.phoneNumber" @change="signWithTokenCode"
			@update:phone="val => emit('update:phone', val)" @close="signMethodsStore.closeModal('token')" />
		<ModalVerificationCode v-if="signMethodsStore.modal.emailToken" mode="email" @change="signWithEmailToken"
			@close="signMethodsStore.closeModal('emailToken')" />
	</div>

	<PaymentModal v-if="showPaymentModal && paymentContext" :sign-uuid="paymentContext.signUuid"
		:sign-request-id="paymentContext.signRequestId" :document="signStore.document" :signer="currentSigner"
		:product-code="signStore.productCode || DEFAULT_SIGN_PRODUCT_CODE" @close="handlePaymentClose"
		@success="onPaymentSuccess" />
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import ModalVerificationCode, { type ModalVerificationChanged } from './ModalVerificationCode.vue'
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
import type { operations } from '../../../types/openapi/openapi'
import type {
	LibresignCapabilities,
	SignatureMethodsRecord,
	UserElementRecord,
	VisibleElementRecord,
} from '../../../types/index'
import { SigningRequirementValidator } from '../../../services/SigningRequirementValidator'
import { SignFlowHandler } from '../../../services/SignFlowHandler'
import { showError, showSuccess } from '../../../services/toast'
import {
	normalizeDocumentForVisibleElements,
	normalizeFileForVisibleElements,
} from '../../../services/signingDocumentAdapter'
import { FILE_STATUS } from '../../../constants.js'
import { getFileSigners, getVisibleElementsFromDocument, idsMatch, isCurrentUserSigner } from '../../../services/visibleElementsService'
import { usePaywall } from '@/payment/usePaywall'
import { notifyInfo, notifySuccess, notifyError } from '@/services/toast'
import PaymentModal from '@/components/Payments/PaymentModal.vue'
import { DEFAULT_SIGN_PRODUCT_CODE } from '@/constants/product'
import { consumeEntitlement } from '@/payment/entitlement'
import { usePaymentContextStore } from '@/store/paymentContext'
import { resolveUserId } from '@/utils/resolveUserId'

type OpenApiAccountMe = operations['account-me']['responses'][200]['content']['application/json']['ocs']['data']
type LibreSignAccountMe = Omit<OpenApiAccountMe, 'settings'> & {
	settings: OpenApiAccountMe['settings'] & {
		phoneNumber: string
	}
}
type LibreSignUserElement = UserElementRecord
type LibreSignVisibleElement = VisibleElementRecord
type OcsResponseData<T> = {
	ocs: {
		data: T
	}
}

defineOptions({
	name: 'Sign',
	methods: {
		// Backward-compatibility shim for legacy tests that invoke Options API methods directly.
		async submitSignature(this: SubmitSignatureCompatContext, methodConfig: SignatureMethodConfig = {}) {
			this.loading = true
			this.signStore.clearSigningErrors()

			try {
				const payload: SubmitSignaturePayload = {
					method: methodConfig.method,
				}

				if (methodConfig.token) {
					payload.token = methodConfig.token
				}

				if (this.elements?.length > 0) {
					if (this.canCreateSignature) {
						payload.elements = this.elements.flatMap((row) => typeof row.elementId === 'number'
							? [{
								documentElementId: row.elementId,
								profileNodeId: row.type ? this.signatureElementsStore.signs[row.type]?.file.nodeId : undefined,
							}]
							: [])
					} else {
						payload.elements = this.elements.flatMap((row) => typeof row.elementId === 'number'
							? [{
								documentElementId: row.elementId,
							}]
							: [])
					}
				}

				const result = await this.signStore.submitSignature(payload, this.signRequestUuid, {
					documentId: this.signStore.document.id,
				})

				if (result.status === 'signingInProgress') {
					this.actionHandler.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
					this.$emit('signing-started', {
						signRequestUuid: this.signRequestUuid,
						async: true,
					})
				} else if (result.status === 'signed') {
					this.actionHandler.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
					this.sidebarStore.hideSidebar()
					this.$emit('signed', {
						...result.data,
						signRequestUuid: this.signRequestUuid,
					})
				}
			} catch (error: unknown) {
				const signError = typeof error === 'object' && error !== null ? error as SignSubmissionError : {}
				if (signError.type === 'missingCertification') {
					const modalCode = this.signMethodsStore.certificateEngine === 'none'
						? 'uploadCertificate'
						: 'createPassword'
					this.actionHandler.showModal(modalCode)
				}

				this.signStore.setSigningErrors(signError.errors || [])
			} finally {
				this.loading = false
			}
		},
	},
})

type UserInfo = LibreSignAccountMe

type SignatureMethodConfig = {
	method?: string
	modalCode?: string
	token?: string
	productCode?: string | null
}

type SignError = {
	title?: string
	message: string
	code?: number
}

type TokenMethodKey = 'smsToken' | 'whatsappToken' | 'signalToken' | 'telegramToken' | 'xmppToken'

type SignatureMethodSetting = {
	identifyMethod?: string
	token?: string
	needCode?: boolean
	hasSignatureFile?: boolean
	label?: string
	hashOfIdentifier?: string
	blurredEmail?: string
	hasConfirmCode?: boolean
}

type SignMethodKey = keyof SignatureMethodsRecord | TokenMethodKey

type SignMethodsSettings = Partial<Record<SignMethodKey, SignatureMethodSetting>>

type SignatureProfile = LibreSignUserElement

type SignDocument = NonNullable<ReturnType<typeof useSignStore>['document']>
type SignDocumentFile = NonNullable<SignDocument['files']>[number]
type SignDocumentSigner = NonNullable<SignDocument['signers']>[number]

type SignResult = {
	status: 'signingInProgress' | 'signed' | 'unknown'
	data: Record<string, unknown>
}

type SubmitSignaturePayload = {
	method?: string
	token?: string
	productCode?: string | null
	elements?: Array<{
		documentElementId: number
		profileNodeId?: number
	}>
}

type SignSubmissionError = {
	type?: string
	errors?: SignError[]
}

type SignStoreContract = ReturnType<typeof useSignStore> & {
	document: SignDocument
	errors: SignError[]
	submitSignature: (
		payload: SubmitSignaturePayload,
		signRequestUuid?: string,
		options?: { documentId?: number },
	) => Promise<SignResult>
	setSigningErrors: (errors: SignError[]) => void
}

type SignMethodsStoreContract = ReturnType<typeof useSignMethodsStore> & {
	settings: SignMethodsSettings
	certificateEngine: string
}

type SignatureElementsStoreContract = ReturnType<typeof useSignatureElementsStore> & {
	signs: Record<string, SignatureProfile>
	signRequestUuid: string
	success: string
	error: string
}

type SidebarStoreContract = ReturnType<typeof useSidebarStore>

type IdentificationDocumentStoreContract = ReturnType<typeof useIdentificationDocumentStore>

type SubmitSignatureCompatContext = {
	loading: boolean
	signStore: SignStoreContract
	canCreateSignature: boolean
	elements: LibreSignVisibleElement[]
	signatureElementsStore: SignatureElementsStoreContract
	signRequestUuid: string
	actionHandler: SignFlowHandler
	sidebarStore: SidebarStoreContract
	signMethodsStore: SignMethodsStoreContract
	$emit: (event: string, payload: unknown) => void
}

type PaymentContext = {
	signUuid: string
	signRequestId: number
}

function isSignSubmissionError(error: unknown): error is SignSubmissionError {
	return typeof error === 'object' && error !== null
}

function getSignatureMethodSetting(
	settings: SignMethodsSettings,
	method: SignMethodKey,
): SignatureMethodSetting | undefined {
	return settings[method]
}

const emit = defineEmits<{
	(e: 'update:phone', value: string): void
	(e: 'signing-started', payload: { signRequestUuid: string; async: boolean }): void
	(e: 'signed', payload: Record<string, unknown> & { signRequestUuid: string }): void
}>()

const signStore = useSignStore() as SignStoreContract
const signMethodsStore = useSignMethodsStore() as SignMethodsStoreContract
const signatureElementsStore = useSignatureElementsStore() as SignatureElementsStoreContract
const sidebarStore = useSidebarStore() as SidebarStoreContract
const identificationDocumentStore = useIdentificationDocumentStore() as IdentificationDocumentStoreContract
const paymentContextStore = usePaymentContextStore()

const loading = ref(true)
const user = ref<UserInfo>({
	account: { uid: '', emailAddress: '', displayName: '' },
	settings: { canRequestSign: false, hasSignatureFile: false, phoneNumber: '' },
})
const signPassword = ref('')
const showManagePassword = ref(false)
const isModal = window.self !== window.top
let unwatchPendingAction: null | (() => void) = null
let requirementValidator: SigningRequirementValidator | null = null
let actionHandler: SignFlowHandler | null = null
const currentDocument = computed<SignDocument>(() => signStore.document)
const visibleElementsDocument = computed(() => normalizeDocumentForVisibleElements(currentDocument.value))
const isProcessingPayment = ref(false)
const isPreparingSignFlow = ref(false)

const elements = computed(() => {
	const document = currentDocument.value
	const signer = document?.signers?.find((row: SignDocumentSigner) => row.me)

	const signRequestIds = new Set<number>()
	if (signer?.signRequestId !== undefined) {
		signRequestIds.add(signer.signRequestId)
	}

	if (Array.isArray(document?.files)) {
		document.files
			.map(normalizeFileForVisibleElements)
			.flatMap((file) => getFileSigners(file))
			.filter((row): row is ReturnType<typeof getFileSigners>[number] & { me: true; signRequestId: number } => isCurrentUserSigner(row) && row.signRequestId !== undefined)
			.forEach((row) => signRequestIds.add(row.signRequestId))
	}

	if (signRequestIds.size === 0) {
		return []
	}

	return getVisibleElementsFromDocument(visibleElementsDocument.value)
		.filter((row) => {
			// Access signatureElementsStore.signs[row.type] directly to ensure reactivity
			if (!row.type || row.signRequestId === undefined) {
				return false
			}
			const signatureData = signatureElementsStore.signs[row.type]
			const hasSignature = Boolean(signatureData?.createdAt)
			return hasSignature && signRequestIds.has(row.signRequestId)
		})
})

const hasSignatures = computed(() => elements.value.length > 0)
const needCreateSignature = computed(() => {
	if (!canCreateSignature.value || hasSignatures.value) {
		return false
	}
	const document = currentDocument.value
	const signer = document?.signers?.find((row: SignDocumentSigner) => row.me)
	if (signer?.signRequestId === undefined) {
		return false
	}
	const visibleElements = visibleElementsDocument.value.visibleElements || []
	return visibleElements.some((row) => row.signRequestId === signer.signRequestId)
})
const needIdentificationDocuments = computed(() => identificationDocumentStore.showDocumentsComponent())
const canCreateSignature = computed(() => {
	const capabilities = getCapabilities() as LibresignCapabilities
	return capabilities.libresign?.config['sign-elements']['can-create-signature'] === true
})
const ableToSign = computed(() => signStore.ableToSign)
const signRequestUuid = computed(() => {
	const doc = signStore.document
	const signer = doc?.signers?.find((row) => row.me) ?? doc?.signers?.[0]
	const fromDoc = doc?.signRequestUuid || doc?.sign_request_uuid || doc?.signUuid || doc?.sign_uuid
	const fromSigner = signer?.sign_uuid
	const isApprover = doc?.settings?.isApprover
	const fromFile = isApprover ? doc?.uuid : null
	return String(fromDoc || fromSigner || fromFile || loadState('libresign', 'sign_request_uuid', '') || '')
})
const currentSigner = computed(() => {
	const signers = signStore.document.signers || []
	const currentSigner = signers.find(s => s.me) || signers[0] || null
	console.log('[Sign] Current signer:', currentSigner);
	return currentSigner
})

const paymentContext = computed<PaymentContext | null>(() => {
	const signer = currentSigner.value

	if (
		!signer ||
		signer.sign_uuid == null ||
		signer.signRequestId == null
	) {
		return null
	}

	return {
		signUuid: signer.sign_uuid,
		signRequestId: signer.signRequestId,
	}
})

async function consumeEntitlementAfterSign() {
	try {
		await consumeEntitlement()
		paymentContextStore.clear()
	} catch (err) {
		// DO NOT BLOCK UX
		console.error('[Entitlement] failed silently', err)
	}
}

/**
 * Show/hide payment modal ref
 */
const showPaymentModal = ref(false)
const route = useRoute()
const router = useRouter()

function openModal(modalCode: string) {
	ensureServices()
	actionHandler?.showModal(modalCode)
}

function initializeServices() {
	requirementValidator = new SigningRequirementValidator(
		signStore,
		signMethodsStore,
		identificationDocumentStore,
	)

	actionHandler = new SignFlowHandler(signMethodsStore)
}

function ensureServices() {
	if (!requirementValidator || !actionHandler) {
		initializeServices()
	}
}

async function loadUser() {
	if (getCurrentUser()) {
		try {
			const { data } = await axios.get<OcsResponseData<UserInfo>>(generateOcsUrl('/apps/libresign/api/v1/account/me'))
			user.value = data.ocs.data
		} catch {
		}
	}
}

function toggleManagePassword() {
	showManagePassword.value = !showManagePassword.value
}

function onCloseConfirmPassword() {
	showManagePassword.value = false
	signMethodsStore.closeModal('password')
}

function resetSignMethodsState() {
	if (typeof signMethodsStore?.$reset === 'function') {
		signMethodsStore.$reset()
	} else {
		Object.keys(signMethodsStore.modal || {}).forEach((key) => {
			signMethodsStore.closeModal(key)
		})
		signMethodsStore.settings = {}
	}
	signStore.clearSigningErrors()
	showManagePassword.value = false
	signPassword.value = ''
}

function onSignatureFileCreated() {
	signStore.clearSigningErrors()
	showManagePassword.value = false
}

function saveSignature() {
	if (signatureElementsStore.success.length) {
		showSuccess(signatureElementsStore.success)
	} else if (signatureElementsStore.error.length) {
		showError(signatureElementsStore.error)
	}
	signMethodsStore.closeModal('createSignature')
}

function handlePaymentClose() {
	showPaymentModal.value = false

	// Reset payment processing flag to allow signing actions again
	isProcessingPayment.value = false
}

async function signWithClick() {
	await submitSignature({ method: 'clickToSign' })
}

async function signWithPassword() {
	await submitSignature({
		method: 'password',
		token: signPassword.value,
	})
}

async function signWithTokenCode(token: string) {
	const tokenMethods: TokenMethodKey[] = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
	const activeMethod = tokenMethods.find((method) =>
		Object.hasOwn(signMethodsStore.settings, method),
	)

	if (!activeMethod) {
		throw new Error('No active token method found')
	}

	const signatureMethodData = getSignatureMethodSetting(signMethodsStore.settings, activeMethod)
	if (!signatureMethodData) {
		throw new Error('No active token method settings found')
	}
	const identifyMethod = signatureMethodData.identifyMethod
	if (!identifyMethod) {
		throw new Error('No identify method found for active token method')
	}

	await submitSignature({
		method: identifyMethod,
		modalCode: 'token',
		token,
		// added for consistency, especially in paywall scenarios where productCode is crucial during submission
		productCode: signStore.productCode,
	})
}

async function signWithEmailToken(token: string) {
	const emailTokenSettings = signMethodsStore.settings.emailToken

	console.log('Email token settings:', emailTokenSettings)

	if (!emailTokenSettings) {
		throw new Error('Email token config missing')
	}

	// STORE TOKEN (important for consistency)
	emailTokenSettings.token = token

	console.log('Updated email token settings with token:', emailTokenSettings)

	const identifyMethod = emailTokenSettings.identifyMethod

	if (!identifyMethod) {
		throw new Error('No identify method found for email token')
	}

	await submitSignature({
		method: identifyMethod,
		modalCode: 'emailToken',
		token,
		// Added productCode here to ensure it's available during submission, especially for paywall scenarios
		productCode: signStore.productCode,
	})
}

let submitSignature = async (methodConfig: SignatureMethodConfig = {}) => {
	loading.value = true
	signStore.clearSigningErrors()

	try {
		const payload: SubmitSignaturePayload = {
			method: methodConfig.method,
		}

		if (methodConfig.token) {
			payload.token = methodConfig.token
		}

		if (methodConfig.productCode) {
			payload.productCode = methodConfig.productCode
		}

		if (elements.value.length > 0) {
			if (canCreateSignature.value) {
				payload.elements = elements.value.flatMap((row) => typeof row.elementId === 'number'
					? [{
						documentElementId: row.elementId,
						profileNodeId: row.type ? signatureElementsStore.signs[row.type]?.file.nodeId : undefined,
					}]
					: [])
			} else {
				payload.elements = elements.value.flatMap((row) => typeof row.elementId === 'number'
					? [{
						documentElementId: row.elementId,
					}]
					: [])
			}
		}

		console.log('Submitting signature with payload:', payload, 'and signRequestUuid:', signRequestUuid.value)
		const result = await signStore.submitSignature(
			payload,
			signRequestUuid.value,
			{
				documentId: signStore.document.id,
			},
		)

		ensureServices()
		if (result.status === 'signingInProgress') {
			actionHandler!.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
			emit('signing-started', {
				signRequestUuid: signRequestUuid.value,
				async: true,
			})
		} else if (result.status === 'signed') {
			actionHandler!.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
			sidebarStore.hideSidebar()
			emit('signed', {
				...result.data,
				signRequestUuid: signRequestUuid.value,
			})

			// After a successful signing, check if we need to consume an entitlement (in paywall scenarios)
			await consumeEntitlementAfterSign()
		}
	} catch (error: unknown) {
		const signError = isSignSubmissionError(error) ? error : {}
		ensureServices()
		if (signError.type === 'missingCertification') {
			const modalCode = signMethodsStore.certificateEngine === 'none'
				? 'uploadCertificate'
				: 'createPassword'
			actionHandler!.showModal(modalCode)
		}

		signStore.setSigningErrors(signError.errors || [])
	} finally {
		loading.value = false
	}
}

async function confirmSignDocument() {
	// prevent double-trigger / race conditions
	if (isProcessingPayment.value) return
	isProcessingPayment.value = true

	const signer = currentSigner.value

	if (!signer) {
		console.warn('[PaymentContext] no signer')
		isProcessingPayment.value = false
		return
	}

	const { sign_uuid, signRequestId } = signer;

	if (!sign_uuid || !signRequestId) {
		console.warn('[PaymentContext] no data for payment context')
		isProcessingPayment.value = false
		return
	}

	const resolvedUserId = await resolveUserId(user.value)

	if (!resolvedUserId) {
		notifyError({
			message: 'Unable to identify user. Please refresh and try again.',
			important: true,
		})
		isProcessingPayment.value = false
		return
	}

	// 1. Set product
	if (!signStore.productCode) {
		signStore.productCode = DEFAULT_SIGN_PRODUCT_CODE
	}

	paymentContextStore.setContext({
		userId: resolvedUserId,
		signUuid: sign_uuid,
		signRequestId: signRequestId,
		productCode: signStore.productCode,
	})

	const paywall = usePaywall()

	// 2. Check entitlement FIRST
	const { allowed } = await paywall.checkEntitlement(signStore.productCode)

	// 3. BLOCK signing if not allowed
	if (!allowed) {
		triggerPaymentFlow()
		return
	}

	// reset flag if allowed
	isProcessingPayment.value = false

	ensureServices()
	signStore.clearSigningErrors()

	console.log(
		`User is entitled to sign. Proceeding with signing action for product code: ${signStore.productCode}`
	)

	const unmetRequirement = requirementValidator!.getFirstUnmetRequirement({
		errors: signStore.errors,
		hasSignatures: hasSignatures.value,
		canCreateSignature: canCreateSignature.value,
	})

	const result = actionHandler!.handleAction('sign', {
		unmetRequirement: unmetRequirement || undefined,
	})

	if (result === 'ready') {
		proceedWithSigning()
	}
}

function triggerPaymentFlow() {

	// set productCode here
	signStore.productCode =
		signStore.productCode || DEFAULT_SIGN_PRODUCT_CODE
	isProcessingPayment.value = false
	showPaymentModal.value = true
}

function onPaymentSuccess() {
	showPaymentModal.value = false

	isProcessingPayment.value = false // IMPORTANT RESET

	notifySuccess({
		message: 'Payment successful',
	})

	// retry signing
	confirmSignDocument()
}

function proceedWithSigning() {
	ensureServices()
	if (signMethodsStore.needClickToSign()) {
		actionHandler!.showModal('clickToSign')
	} else if (signMethodsStore.needSignWithPassword()) {
		actionHandler!.showModal('password')
	} else if (signMethodsStore.needTokenCode()) {
		actionHandler!.showModal('token')
	}
}

function executeSigningAction(action: string) {
	ensureServices()
	signStore.clearSigningErrors()

	const unmetRequirement = requirementValidator!.getFirstUnmetRequirement({
		errors: signStore.errors,
		hasSignatures: hasSignatures.value,
		canCreateSignature: canCreateSignature.value,
	})

	const config = unmetRequirement ? { unmetRequirement } : { unmetRequirement: undefined }
	const result = actionHandler!.handleAction(action, config)

	if (result === 'ready') {
		proceedWithSigning()
	}
}

onMounted(async () => {
	loading.value = true
	signatureElementsStore.signRequestUuid = signRequestUuid.value
	signatureElementsStore.loadSignatures()
	paymentContextStore.hydrate()

	initializeServices()

	unwatchPendingAction = watch(
		() => signStore.pendingAction,
		(newAction) => {
			if (newAction) {
				executeSigningAction(newAction)
				signStore.clearPendingAction()
			}
		},
	)

	if (signStore.pendingAction) {
		await nextTick()
		executeSigningAction(signStore.pendingAction)
		signStore.clearPendingAction()
	}

	await Promise.all([
		loadUser(),
	])

	// Retry after payment redirect
	if (route.query.retrySign === 'true') {
		await nextTick()

		notifyInfo({ message: 'Payment successful. Proceeding with signing...', important: true })

		if (!paymentContextStore.isReady()) {
			await nextTick() // ensure signer is ready
			console.warn('[PaymentContext] rebuilding after redirect')

			const signer = currentSigner.value
			const resolvedUserId = await resolveUserId(user.value)

			if (signer && resolvedUserId && signer.sign_uuid && signer.signRequestId) {
				paymentContextStore.setContext({
					userId: resolvedUserId,
					signUuid: signer.sign_uuid,
					signRequestId: signer.signRequestId,
					productCode: signStore.productCode || DEFAULT_SIGN_PRODUCT_CODE,
				})
			}
		}

		// Guard
		if (!signStore.pendingAction) {
			await confirmSignDocument()
		}

		// Clean query
		router.replace({ query: {} })
	}

	// handle payment failure
	if (route.query.paymentFailed === 'true') {
		notifyError({ message: 'Payment failed. Please try again.', important: true })

		router.replace({ query: {} })
	}

	loading.value = false
	if (signStore.document?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		emit('signing-started', {
			signRequestUuid: signRequestUuid.value,
			async: true,
		})
	}

})

watch(signRequestUuid, (newUuid, oldUuid) => {
	if (newUuid && oldUuid && newUuid !== oldUuid) {
		Object.keys(signMethodsStore.modal).forEach((key) => {
			signMethodsStore.closeModal(key)
		})
		signStore.clearSigningErrors()
		showManagePassword.value = false
		signPassword.value = ''
	}
})

onBeforeUnmount(() => {
	resetSignMethodsState()
	if (unwatchPendingAction) {
		unwatchPendingAction()
	}
})

defineExpose({
	elements,
	hasSignatures,
	needCreateSignature,
	canCreateSignature,
	submitSignature,
	signWithTokenCode,
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

.step-explanation {
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.confirmation-text {
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
	text-align: center;
}

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
	.libresign-dialog .modal-wrapper--small>.modal-container {
		width: fit-content !important;
		height: unset !important;
		max-height: 90% !important;
		position: relative !important;
		top: unset !important;
		border-radius: var(--border-radius-large) !important;
	}

	/* Apply same rule to NcDialog's default wrapper class */
	.dialog__modal .modal-wrapper--small>.modal-container {
		width: fit-content !important;
		height: unset !important;
		max-height: 90% !important;
		position: relative !important;
		top: unset !important;
		border-radius: var(--border-radius-large) !important;
	}
}
</style>
