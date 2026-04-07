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
			<NcNoteCard v-for="(error, index) in signStore.errors"
				:key="index"
				:heading="error.title || ''"
				type="error">
				<NcRichText :text="error.message"
					:use-markdown="true" />
			</NcNoteCard>
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
			<div v-else-if="hasBlockingSignError" class="sign-blocked-warning">
				<p>
					<!-- TRANSLATORS Shown after a non-retriable certificate validation failure. "Signing is blocked" means the signer cannot continue now and must resolve the certificate issue first. -->
					{{ t('libresign', 'Signing is blocked until the certificate validation issue is resolved.') }}
				</p>
				<NcButton :wide="true"
					:disabled="loading"
					@click="clearBlockingSignError">
					{{ t('libresign', 'Try signing again') }}
				</NcButton>
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
import { NON_RETRIABLE_SIGN_ERROR_CODE, shouldCloseCurrentModalOnSignError } from './signErrorUtils'
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
import { getSigningRouteUuid } from '../../../utils/signRequestUuid.ts'
import type { operations } from '../../../types/openapi/openapi'
import type {
	LibresignCapabilities,
	SignatureMethodsRecord,
	UserElementRecord,
	VisibleElementRecord,
} from '../../../types/index'
import { SigningRequirementValidator } from '../../../services/SigningRequirementValidator'
import { SignFlowHandler } from '../../../services/SignFlowHandler'
import {
	normalizeDocumentForVisibleElements,
} from '../../../services/signingDocumentAdapter'
import { FILE_STATUS } from '../../../constants.js'
import {
	getCurrentUserSignRequestIds,
	hasVisibleElementsForCurrentUser,
	getVisibleElementsFromDocument,
	idsMatch,
} from '../../../services/visibleElementsService'

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
				const basePayload: SubmitSignaturePayload = {
					method: methodConfig.method,
				}

				if (methodConfig.token) {
					basePayload.token = methodConfig.token
				}

				const myEnvelopeSigners = this.signStore.document?.nodeType === 'envelope'
					? (this.signStore.document?.signers ?? [])
						.filter((s): s is NonNullable<typeof s> & { signRequestId: number; sign_request_uuid: string } =>
							s.me === true && typeof s.sign_request_uuid === 'string')
					: []

				if (myEnvelopeSigners.length > 0) {
					let signingInProgressResult: { result: SignResult; fallbackUuid: string } | null = null
					let signedResult: { result: SignResult; fallbackUuid: string } | null = null

					for (const signer of myEnvelopeSigners) {
						const filePayload: SubmitSignaturePayload = { ...basePayload }
						const fileElements = (this.elements ?? []).filter((el) => el.signRequestId === signer.signRequestId)
						if (fileElements.length > 0) {
							if (this.canCreateSignature) {
								filePayload.elements = fileElements.flatMap((row) => typeof row.elementId === 'number'
									? [{
									documentElementId: row.elementId,
									profileNodeId: row.type ? this.signatureElementsStore.signs[row.type]?.file.nodeId : undefined,
									}]
									: [])
							} else {
								filePayload.elements = fileElements.flatMap((row) => typeof row.elementId === 'number'
									? [{
									documentElementId: row.elementId,
									}]
									: [])
							}
						}

						lastResult = await this.signStore.submitSignature(filePayload, signer.sign_request_uuid, {
							documentId: this.signStore.document.id,
						})

						if (lastResult.status === 'signingInProgress') {
							anySigningInProgress = true
						}
					}

					if (signedResult) {
						const signRequestUuid = typeof signedResult.result.data.file?.uuid === 'string'
							&& signedResult.result.data.file.uuid.length > 0
							? signedResult.result.data.file.uuid
							: signedResult.fallbackUuid
						this.actionHandler.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
						this.sidebarStore.hideSidebar()
						this.$emit('signed', {
							...signedResult.result.data,
							signRequestUuid,
						})
					} else if (signingInProgressResult) {
						const signRequestUuid = typeof signingInProgressResult.result.data.job?.file?.uuid === 'string'
							&& signingInProgressResult.result.data.job.file.uuid.length > 0
							? signingInProgressResult.result.data.job.file.uuid
							: signingInProgressResult.fallbackUuid
						this.actionHandler.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
						this.$emit('signing-started', {
							signRequestUuid,
							async: true,
						})
					}
				} else {
					const payload: SubmitSignaturePayload = { ...basePayload }

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
						const signRequestUuid = typeof result.data.file?.uuid === 'string'
							&& result.data.file.uuid.length > 0
							? result.data.file.uuid
							: this.signRequestUuid
						this.actionHandler.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
						this.sidebarStore.hideSidebar()
						this.$emit('signed', {
							...result.data,
							signRequestUuid,
						})
					}
				}
			} catch (error: unknown) {
				const signError = typeof error === 'object' && error !== null ? error as SignSubmissionError : {}
				if (signError.type === 'missingCertification') {
					const modalCode = this.signMethodsStore.certificateEngine === 'none'
						? 'uploadCertificate'
						: 'createPassword'
					this.actionHandler.showModal(modalCode)
				}

				if (shouldCloseCurrentModalOnSignError(methodConfig, signError)) {
					this.actionHandler.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
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

type SignResult = {
	status: 'signingInProgress' | 'signed' | 'unknown'
	data: SignResultData
}

type SignResultData = {
	action?: number
	file?: {
		uuid?: string
	}
	job?: {
		status?: string
		file?: {
			uuid?: string
		}
	}
	[key: string]: unknown
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

function getNavigationUuidFromSignResultData(
	data: SignResultData | null | undefined,
	fallbackUuid: string,
): string {
	if (typeof data?.file?.uuid === 'string' && data.file.uuid.length > 0) {
		return data.file.uuid
	}

	if (typeof data?.job?.file?.uuid === 'string' && data.job.file.uuid.length > 0) {
		return data.job.file.uuid
	}

	return fallbackUuid
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
const currentUserSignRequestIds = computed(() => new Set(getCurrentUserSignRequestIds(visibleElementsDocument.value)))

const elements = computed(() => {
	const signRequestIds = currentUserSignRequestIds.value
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
	return hasVisibleElementsForCurrentUser(visibleElementsDocument.value)
})
const needIdentificationDocuments = computed(() => identificationDocumentStore.showDocumentsComponent())
const canCreateSignature = computed(() => {
	const capabilities = getCapabilities() as LibresignCapabilities
	return capabilities.libresign?.config['sign-elements']['can-create-signature'] === true
})
const ableToSign = computed(() => signStore.ableToSign)
const hasBlockingSignError = computed(() => signStore.errors.some((error) => Number(error?.code) === NON_RETRIABLE_SIGN_ERROR_CODE))
const signRequestUuid = computed(() => {
	const fallbackUuid = loadState('libresign', 'sign_request_uuid', '')
	return String(getSigningRouteUuid(signStore.document, fallbackUuid) || '')
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

function clearBlockingSignError() {
	signStore.clearSigningErrors()
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
		const basePayload: SubmitSignaturePayload = {
			method: methodConfig.method,
		}

		if (methodConfig.token) {
			basePayload.token = methodConfig.token
		}

		const myEnvelopeSigners = signStore.document?.nodeType === 'envelope'
			? (signStore.document?.signers ?? [])
				.filter((s): s is NonNullable<typeof s> & { signRequestId: number; sign_request_uuid: string } =>
					s.me === true && typeof s.sign_request_uuid === 'string')
			: []

		if (myEnvelopeSigners.length > 0) {
			let signingInProgressResult: { result: SignResult; fallbackUuid: string } | null = null
			let signedResult: { result: SignResult; fallbackUuid: string } | null = null

			for (const signer of myEnvelopeSigners) {
				const filePayload: SubmitSignaturePayload = { ...basePayload }
				const fileElements = elements.value.filter((el) => el.signRequestId === signer.signRequestId)
				if (fileElements.length > 0) {
					if (canCreateSignature.value) {
						filePayload.elements = fileElements.flatMap((row) => typeof row.elementId === 'number'
							? [{
							documentElementId: row.elementId,
							profileNodeId: row.type ? signatureElementsStore.signs[row.type]?.file.nodeId : undefined,
							}]
							: [])
					} else {
						filePayload.elements = fileElements.flatMap((row) => typeof row.elementId === 'number'
							? [{
							documentElementId: row.elementId,
							}]
							: [])
					}
				}

				lastResult = await signStore.submitSignature(filePayload, signer.sign_request_uuid, {
					documentId: signStore.document.id,
				})

				if (lastResult.status === 'signingInProgress') {
					anySigningInProgress = true
				}
			}

			ensureServices()
			if (signedResult) {
				actionHandler!.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
				sidebarStore.hideSidebar()
				emit('signed', {
					...signedResult.result.data,
					signRequestUuid: getNavigationUuidFromSignResultData(
						signedResult.result.data,
						signedResult.fallbackUuid,
					),
				})
			} else if (signingInProgressResult) {
				actionHandler!.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
				emit('signing-started', {
					signRequestUuid: getNavigationUuidFromSignResultData(
						signingInProgressResult.result.data,
						signingInProgressResult.fallbackUuid,
					),
					async: true,
				})
			}
		} else {
			const payload: SubmitSignaturePayload = { ...basePayload }

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
					signRequestUuid: getNavigationUuidFromSignResultData(result.data, signRequestUuid.value),
				})
			}
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

		if (shouldCloseCurrentModalOnSignError(methodConfig, signError)) {
			actionHandler!.closeModal(methodConfig.modalCode || methodConfig.method || 'token')
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

.sign-blocked-warning {
	margin-top: 1em;
	display: flex;
	flex-direction: column;
	gap: 8px;
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
