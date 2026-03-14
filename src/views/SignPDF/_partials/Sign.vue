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
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="openModal('createSignature')">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCertificate()">
				<p>
					{{ t('libresign', 'You need to upload your certificate to sign the document.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="openModal('uploadCertificate')">
					{{ t('libresign', 'Upload certificate') }}
				</NcButton>
			</div>
			<div v-else-if="signMethodsStore.needCreatePassword()">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					variant="primary"
					@click="openModal('createPassword')">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needIdentificationDocuments" class="no-identification-warning">
				<Documents :sign-request-uuid="signRequestUuid" />
			</div>
			<NcButton v-else-if="ableToSign"
				:wide="true"
				:disabled="loading"
				variant="primary"
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
		<NcDialog v-if="signMethodsStore.modal.clickToSign"
			:no-close="loading"
			:name="t('libresign', 'Sign document')"
			size="small"
			dialog-classes="libresign-dialog"
			@closing="signMethodsStore.closeModal('clickToSign')">
			<NcNoteCard v-for="(error, index) in signStore.errors"
				:key="index"
				:heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message"
					:use-markdown="true" />
			</NcNoteCard>

			<p class="confirmation-text">
				{{ t('libresign', 'Confirm that you want to sign this document.') }}
			</p>

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
					{{ t('libresign', 'Sign document') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-if="signMethodsStore.modal.password"
			:no-close="loading"
			:name="t('libresign', 'Sign document')"
			size="small"
			dialog-classes="libresign-dialog"
			@closing="onCloseConfirmPassword">
			<NcNoteCard v-for="(error, index) in signStore.errors"
				:key="index"
				:heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message"
					:use-markdown="true" />
			</NcNoteCard>

			<p class="confirmation-text">
				{{ t('libresign', 'Enter your signature password to sign the document.') }}
			</p>

			<form @submit.prevent="signWithPassword()">
				<NcPasswordField v-model="signPassword"
					:label="t('libresign', 'Signature password')"
					type="password" />
			</form>
			<a id="lost-password" @click="toggleManagePassword">{{ t('libresign', 'Forgot password?') }}</a>
			<ManagePassword v-if="showManagePassword"
				@certificate:uploaded="onSignatureFileCreated" />
			<template #actions>
				<NcButton :disabled="signPassword.length < 3 || loading"
					type="submit"
					variant="primary"
					@click="signWithPassword()">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Sign document') }}
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
		<CreatePassword @password:created="onSignatureFileCreated" />
		<UploadCertificate
			:useModal="true"
			:errors="signStore.errors"
			@certificate:uploaded="onSignatureFileCreated" />
		<ModalVerificationCode v-if="signMethodsStore.modal.token"
			mode="token"
			:phone-number="user.settings.phoneNumber"
			@change="signWithTokenCode"
			@update:phone="val => emit('update:phone', val)"
			@close="signMethodsStore.closeModal('token')" />
		<ModalVerificationCode v-if="signMethodsStore.modal.emailToken"
			mode="email"
			@change="signWithEmailToken"
			@close="signMethodsStore.closeModal('emailToken')" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import ModalVerificationCode from './ModalVerificationCode.vue'
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
import { FILE_STATUS } from '../../../constants.js'
import { getFileSigners, getVisibleElementsFromDocument, idsMatch, isCurrentUserSigner } from '../../../services/visibleElementsService'

type VisibleElementsFileInput = Parameters<typeof getFileSigners>[0]
type VisibleElementsDocumentInput = Parameters<typeof getVisibleElementsFromDocument>[0]
type ServiceVisibleElement = VisibleElementRecord

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

function isSignSubmissionError(error: unknown): error is SignSubmissionError {
	return typeof error === 'object' && error !== null
}

function normalizeSignVisibleElement(element: unknown): ServiceVisibleElement | null {
	const candidate = typeof element === 'object' && element !== null ? element as Record<string, unknown> : null
	if (!candidate) {
		return null
	}
	const coordinates = typeof candidate.coordinates === 'object' && candidate.coordinates !== null
		? candidate.coordinates as Record<string, unknown>
		: null
	const type = typeof candidate.type === 'string' ? candidate.type : null
	const elementId = candidate.elementId === undefined ? undefined : Number(candidate.elementId)
	const signRequestId = candidate.signRequestId === undefined ? undefined : Number(candidate.signRequestId)
	const fileId = candidate.fileId === undefined ? undefined : Number(candidate.fileId)
	if (!coordinates || !type || elementId === undefined || signRequestId === undefined || fileId === undefined) {
		return null
	}
	if (![elementId, signRequestId, fileId].every(Number.isFinite)) {
		return null
	}

	return {
		elementId,
		signRequestId,
		fileId,
		type,
		coordinates,
	}
}

function normalizeSignFile(file: SignDocumentFile): VisibleElementsFileInput {
	return {
		...file,
		metadata: file.metadata ?? undefined,
		signers: Array.isArray(file.signers) ? file.signers : [],
		visibleElements: Array.isArray(file.visibleElements)
			? file.visibleElements
				.map(normalizeSignVisibleElement)
				.filter((element): element is ServiceVisibleElement => element !== null)
			: [],
	}
}

function getVisibleElementsDocument(document: SignDocument): VisibleElementsDocumentInput {
	return {
		id: document.id,
		uuid: document.uuid,
		name: document.name,
		status: document.status,
		statusText: document.statusText,
		settings: document.settings,
		signers: Array.isArray(document.signers) ? document.signers : [],
		visibleElements: Array.isArray(document.visibleElements)
			? document.visibleElements
				.map(normalizeSignVisibleElement)
				.filter((element): element is ServiceVisibleElement => element !== null)
			: [],
		files: Array.isArray(document.files)
			? document.files.map(normalizeSignFile)
			: [],
	}
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
const visibleElementsDocument = computed(() => getVisibleElementsDocument(currentDocument.value))

const elements = computed(() => {
	const document = currentDocument.value
	const signer = document?.signers?.find((row: SignDocumentSigner) => row.me)

	const signRequestIds = new Set<string>()
	if (signer?.signRequestId !== undefined) {
		signRequestIds.add(String(signer.signRequestId))
	}

	if (Array.isArray(document?.files)) {
		document.files
			.map(normalizeSignFile)
			.flatMap((file) => getFileSigners(file))
			.filter((row) => isCurrentUserSigner(row) && row.signRequestId !== undefined)
			.forEach((row) => signRequestIds.add(String(row.signRequestId)))
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
			return hasSignature && signRequestIds.has(String(row.signRequestId))
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
	return visibleElements.some((row) => String(row.signRequestId) === String(signer.signRequestId))
})
const needIdentificationDocuments = computed(() => identificationDocumentStore.showDocumentsComponent())
const canCreateSignature = computed(() => {
	const capabilities = getCapabilities() as LibresignCapabilities
	return capabilities.libresign?.config['sign-elements']['can-create-signature'] === true
})
const ableToSign = computed(() => signStore.ableToSign)
const signRequestUuid = computed(() => {
	const doc = signStore.document
	const signer = doc.signers?.find((row) => row.me) ?? doc.signers?.[0]
	const fromDoc = doc.signRequestUuid || doc.sign_request_uuid || doc.signUuid || doc.sign_uuid
	const fromSigner = signer?.sign_uuid
	const isApprover = doc.settings?.isApprover
	const fromFile = isApprover ? doc.uuid : null
	return String(fromDoc || fromSigner || fromFile || loadState('libresign', 'sign_request_uuid', '') || '')
})

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
	})
}

async function signWithEmailToken() {
	const identifyMethod = getSignatureMethodSetting(signMethodsStore.settings, 'emailToken')?.identifyMethod
	if (!identifyMethod) {
		throw new Error('No identify method found for email token')
	}
	await submitSignature({
		method: identifyMethod,
		modalCode: 'emailToken',
		token: signMethodsStore.settings.emailToken?.token,
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

function confirmSignDocument() {
	ensureServices()
	signStore.clearSigningErrors()

	const unmetRequirement = requirementValidator!.getFirstUnmetRequirement({
		errors: signStore.errors,
		hasSignatures: hasSignatures.value,
		canCreateSignature: canCreateSignature.value,
	})

	const result = actionHandler!.handleAction('sign', { unmetRequirement: unmetRequirement || undefined })

	if (result === 'ready') {
		proceedWithSigning()
	}
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
	.libresign-dialog .modal-wrapper--small > .modal-container {
		width: fit-content !important;
		height: unset !important;
		max-height: 90% !important;
		position: relative !important;
		top: unset !important;
		border-radius: var(--border-radius-large) !important;
	}

	/* Apply same rule to NcDialog's default wrapper class */
	.dialog__modal .modal-wrapper--small > .modal-container {
		width: fit-content !important;
		height: unset !important;
		max-height: 90% !important;
		position: relative !important;
		top: unset !important;
		border-radius: var(--border-radius-large) !important;
	}
}
</style>
