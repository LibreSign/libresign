<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="request-signature-tab">
		<NcNoteCard v-if="showDocMdpWarning" type="warning">
			{{ t('libresign', 'This document has been certified with no changes allowed. You cannot add more signers to this document.') }}
		</NcNoteCard>
		<NcNoteCard v-if="isOriginalFileDeleted" type="warning">
			{{ t('libresign', 'The original file was deleted. You can no longer add signers or open it.') }}
		</NcNoteCard>
		<NcNoteCard v-if="hasSignersWithDisabledMethods" type="warning">
			{{ t('libresign', 'Some signers use identification methods that have been disabled. Please remove or update them before requesting signatures.') }}
		</NcNoteCard>
		<NcButton v-if="filesStore.canAddSigner() && !isOriginalFileDeleted"
			:variant="hasSigners ? 'secondary' : 'primary'"
			@click="addSigner">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountPlus" :size="20" />
			</template>
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<NcCheckboxRadioSwitch v-if="showPreserveOrder && !isOriginalFileDeleted"
			v-model="preserveOrder"
			type="switch"
			@update:modelValue="onPreserveOrderChange">
			{{ t('libresign', 'Sign in order') }}
		</NcCheckboxRadioSwitch>
		<NcButton v-if="showViewOrderButton && !isOriginalFileDeleted"
			variant="tertiary"
			@click="showOrderDiagram = true">
			<template #icon>
				<NcIconSvgWrapper :path="mdiChartGantt" :size="20" />
			</template>
			{{ t('libresign', 'View signing order') }}
		</NcButton>
		<Signers :event="isOriginalFileDeleted ? '' : 'libresign:edit-signer'"
			@signing-order-changed="debouncedSave">
			<template #actions="{signer, closeActions}">
				<template v-if="!isOriginalFileDeleted">
					<NcActionInput v-if="canEditSigningOrder(signer)"
						:label="t('libresign', 'Signing order')"
						type="number"
						:value="signer.signingOrder || 1"
						@update:modelValue="updateSigningOrder(signer, $event)"
						@submit="confirmSigningOrder(signer); closeActions()"
						@blur="confirmSigningOrder(signer)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiOrderNumericAscending" :size="20" />
						</template>
					</NcActionInput>
					<NcActionButton v-if="canCustomizeMessage(signer)"
						:close-after-click="true"
						@click="customizeMessage(signer); closeActions()">
						<template #icon>
							<NcIconSvgWrapper :path="mdiMessageText" :size="20" />
						</template>
						{{ t('libresign', 'Customize message') }}
					</NcActionButton>
					<NcActionButton v-if="canDelete(signer)"
						aria-label="Delete"
						:close-after-click="true"
						@click="filesStore.deleteSigner(signer)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
						</template>
						{{ t('libresign', 'Delete') }}
					</NcActionButton>
					<NcActionButton v-if="canRequestSignature(signer)"
						:close-after-click="true"
						@click="requestSignatureForSigner(signer)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiSend" :size="20" />
						</template>
						{{ t('libresign', 'Request signature') }}
					</NcActionButton>
					<NcActionButton v-if="canSendReminder(signer)"
						:close-after-click="true"
						@click="sendNotify(signer)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiBell" :size="20" />
						</template>
						{{ t('libresign', 'Send reminder') }}
					</NcActionButton>
				</template>
			</template>
		</Signers>
		<NcFormBox v-if="isEnvelope" class="action-form-box">
			<NcButton
				wide
				variant="secondary"
				:disabled="hasLoading"
				@click="openManageFiles">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiFileMultiple" :size="20" />
				</template>
				{{ t('libresign', 'Manage files ({count})', { count: envelopeFilesCount }) }}
			</NcButton>
		</NcFormBox>
		<NcFormBox v-if="showSaveButton || showRequestButton" class="action-form-box">
			<NcButton v-if="showSaveButton"
				wide
				variant="secondary"
				:disabled="hasLoading"
				@click="save()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
					<NcIconSvgWrapper v-else-if="isSignElementsAvailable()" :path="mdiPencil" :size="20" />
				</template>
				{{ isSignElementsAvailable() ? t('libresign', 'Setup signature positions') : t('libresign', 'Save') }}
			</NcButton>
			<NcButton v-if="showRequestButton"
				wide
				:variant="filesStore.canSign() ? 'secondary' : 'primary'"
				:disabled="hasLoading"
				@click="request()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiSend" :size="20" />
				</template>
				{{ t('libresign', 'Request signatures') }}
			</NcButton>
		</NcFormBox>
		<SigningProgress
			v-if="showSigningProgress"
			:status="signingProgressStatus ?? FILE_STATUS.SIGNING_IN_PROGRESS"
			:status-text="signingProgressStatusText"
			:progress="signingProgressView ?? undefined"
			:is-loading="hasLoading" />
		<NcFormBox v-if="filesStore.canSign()" class="action-form-box">
			<NcButton
				wide
				variant="primary"
				:disabled="hasLoading"
				@click="sign()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiPencil" :size="20" />
				</template>
				{{ t('libresign', 'Sign document') }}
			</NcButton>
		</NcFormBox>
		<NcFormBox class="action-form-box">
			<NcButton v-if="filesStore.canValidate()"
				wide
				variant="secondary"
				@click="validationFile()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiInformation" :size="20" />
				</template>
				{{ t('libresign', 'Validation info') }}
			</NcButton>
			<NcButton v-if="!isEnvelope && !isOriginalFileDeleted"
				wide
				variant="secondary"
				@click="openFile()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFileDocument" :size="20" />
				</template>
				{{ t('libresign', 'Open file') }}
			</NcButton>
		</NcFormBox>
		<VisibleElements />
		<NcModal v-if="modalSrc"
			size="full"
			:name="fileName"
			:close-button-contained="false"
			:close-button-outside="true"
			@close="closeModal()">
			<iframe :src="modalSrc" class="iframe" />
		</NcModal>
		<NcDialog v-if="filesStore.identifyingSigner"
			id="request-signature-identify-signer"
			:size="size"
			:name="modalTitle"
			@closing="filesStore.disableIdentifySigner()">
			<NcAppSidebar :name="modalTitle"
				:active="activeTab"
				@update:active="onTabChange">
				<NcAppSidebarTab v-for="method in enabledMethods"
					:id="`tab-${method.name}`"
					:key="method.name"
					:name="method.friendly_name">
					<template #icon>
						<NcIconSvgWrapper :size="20"
							:svg="getSvgIcon(method.name)" />
					</template>
					<IdentifySigner :signer-to-edit="signerToEdit"
						:placeholder="method.friendly_name"
						:method="method.name"
						:methods="methods"
						:disabled="isSignerMethodDisabled" />
				</NcAppSidebarTab>
			</NcAppSidebar>
		</NcDialog>
		<NcDialog v-if="showConfirmRequest"
			:name="t('libresign', 'Confirm')"
			:message="t('libresign', 'Send signature request?')"
			@closing="showConfirmRequest = false">
			<template #actions>
				<NcButton @click="showConfirmRequest = false">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="hasLoading"
					@click="confirmRequest">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
						<NcIconSvgWrapper v-else :path="mdiSend" :size="20" />
					</template>
					{{ t('libresign', 'Send') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-if="showConfirmRequestSigner"
			:name="t('libresign', 'Confirm')"
			:message="t('libresign', 'Send signature request?')"
			@closing="showConfirmRequestSigner = false; selectedSigner = null">
			<template #actions>
				<NcButton @click="showConfirmRequestSigner = false; selectedSigner = null">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="hasLoading"
					@click="confirmRequestSigner">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
						<NcIconSvgWrapper v-else :path="mdiSend" :size="20" />
					</template>
					{{ t('libresign', 'Send') }}
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-if="showOrderDiagram"
			:name="t('libresign', 'Signing order diagram')"
			size="large"
			@closing="showOrderDiagram = false">
			<SigningOrderDiagram :signers="signingOrderDiagramSigners"
				:sender-name="currentUserDisplayName" />
			<template #actions>
				<NcButton @click="showOrderDiagram = false">
					{{ t('libresign', 'Close') }}
				</NcButton>
			</template>
		</NcDialog>
		<EnvelopeFilesList :open="showEnvelopeFilesDialog"
			@close="showEnvelopeFilesDialog = false" />
	</div>
</template>
<script setup lang="ts">

import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import debounce from 'debounce'

import {
	mdiAccountPlus,
	mdiBell,
	mdiChartGantt,
	mdiDelete,
	mdiFileDocument,
	mdiFileMultiple,
	mdiFilePlus,
	mdiInformation,
	mdiMessageText,
	mdiOrderNumericAscending,
	mdiPencil,
	mdiSend,
} from '@mdi/js'

import svgAccount from '@mdi/svg/svg/account.svg?raw'
import svgEmail from '@mdi/svg/svg/email.svg?raw'
import svgSms from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp from '@mdi/svg/svg/xmpp.svg?raw'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import type { Event as NextcloudEvent, EventHandler } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import EnvelopeFilesList from './EnvelopeFilesList.vue'
import IdentifySigner from '../Request/IdentifySigner.vue'
import Signers from '../Signers/Signers.vue'
import SigningOrderDiagram from '../SigningOrder/SigningOrderDiagram.vue'
import SigningProgress from '../RequestSigningProgress.vue'
import VisibleElements from '../Request/VisibleElements.vue'

import svgSignal from '../../../img/logo-signal-app.svg?raw'
import svgTelegram from '../../../img/logo-telegram-app.svg?raw'
import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../../constants.js'
import { openDocument } from '../../utils/viewer.js'
import router from '../../router/router'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import { useUserConfigStore } from '../../store/userconfig.js'
import { startLongPolling } from '../../services/longPolling'
import { useSigningOrder } from '../../composables/useSigningOrder.js'
import type { components, operations } from '../../types/openapi/openapi'
import type {
	FileRecord as RequestTabFile,
	IdentifyMethodRecord,
	IdentifyMethodSetting as IdentifyMethodConfig,
	LibresignCapabilities as RequestSignatureTabCapabilities,
	LoadedFileInfoState as LoadedDocumentState,
	SignerRecord as SignerRow,
	SignatureFlowMode,
	SignatureFlowValue,
} from '../../types/index'

type IdentifySignerMethod = Pick<IdentifyMethodRecord, 'method' | 'value'>
type IdentifySignerToEdit = {
	identify?: string
	signRequestId?: string
	displayName?: string
	description?: string
	identifyMethods?: IdentifySignerMethod[]
}
type SigningProgressView = {
	total: number
	signed: number
	signers?: Array<{
		id: string | number
		displayName: string
		signed: boolean
	}>
}
type SigningOrderDiagramSigner = {
	displayName?: string
	signed?: boolean
	signingOrder?: number
}
type PollingStatusData = {
	status: number
	statusText?: string
	progress?: components['schemas']['ProgressPayload']
}
type RequestSignatureErrorData = operations['request_signature-request']['responses'][422]['content']['application/json']['ocs']['data']
type UpdateSignatureErrorData = operations['request_signature-update-sign']['responses'][422]['content']['application/json']['ocs']['data']
type DeleteRequestSignatureErrorData =
	| operations['request_signature-delete-one-request-signature-using-file-id']['responses'][401]['content']['application/json']['ocs']['data']
	| operations['request_signature-delete-one-request-signature-using-file-id']['responses'][422]['content']['application/json']['ocs']['data']
type NotifySignerErrorData = operations['notify-signer']['responses'][401]['content']['application/json']['ocs']['data']
type NotifySignerSuccess = operations['notify-signer']['responses'][200]['content']['application/json']
type OcsErrorData = RequestSignatureErrorData | UpdateSignatureErrorData | DeleteRequestSignatureErrorData | NotifySignerErrorData

defineOptions({
	name: 'RequestSignatureTab',
})

const props = withDefaults(defineProps<{
	useModal?: boolean
}>(), {
	useModal: false,
})

const filesStore = useFilesStore()
const signStore = useSignStore()
const sidebarStore = useSidebarStore()
const userConfigStore = useUserConfigStore() as ReturnType<typeof useUserConfigStore> & {
	files_list_signer_identify_tab?: string
}
const { normalizeSigningOrders, recalculateSigningOrders } = useSigningOrder()
const capabilities = getCapabilities() as RequestSignatureTabCapabilities

const hasLoading = ref(false)
const signerToEdit = ref<IdentifySignerToEdit>({})
const modalSrc = ref('')
const documentData = ref<LoadedDocumentState>(loadState('libresign', 'file_info', {}) as LoadedDocumentState)
const methods = ref<IdentifyMethodConfig[]>(loadState('libresign', 'identify_methods', []) as IdentifyMethodConfig[])
const showConfirmRequest = ref(false)
const showConfirmRequestSigner = ref(false)
const selectedSigner = ref<SignerRow | null>(null)
const activeTab = ref('')
const preserveOrder = ref(false)
const showOrderDiagram = ref(false)
const showEnvelopeFilesDialog = ref(false)
const adminSignatureFlow = ref<SignatureFlowMode>(loadState('libresign', 'signature_flow', 'none') as SignatureFlowMode)
const signingProgress = ref<components['schemas']['ProgressPayload'] | null>(null)
const signingProgressStatus = ref<number | null>(null)
const signingProgressStatusText = ref('')
const stopPollingFunction = ref<null | (() => void)>(null)

const signatureFlow = computed(() => {
	const file = filesStore.getFile()
	let flow = file?.signatureFlow

	if (typeof flow === 'number') {
		const flowMap: Record<number, string> = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
		return flowMap[flow]
	}

	if (flow && flow !== 'none') {
		return flow
	}
	if (adminSignatureFlow.value && adminSignatureFlow.value !== 'none') {
		return adminSignatureFlow.value
	}
	return 'parallel'
})

const isAdminFlowForced = computed(() => adminSignatureFlow.value && adminSignatureFlow.value !== 'none')
const isOrderedNumeric = computed(() => signatureFlow.value === 'ordered_numeric')
const hasSigners = computed(() => filesStore.hasSigners(filesStore.getFile()))
const totalSigners = computed(() => filesStore.getFile()?.signers?.length || 0)
const isOriginalFileDeleted = computed(() => filesStore.isOriginalFileDeleted())
const showSigningOrderOptions = computed(() => !isOriginalFileDeleted.value && hasSigners.value && filesStore.canSave() && !isAdminFlowForced.value)
const showPreserveOrder = computed(() => !isOriginalFileDeleted.value && totalSigners.value > 1 && filesStore.canSave() && !isAdminFlowForced.value)
const showViewOrderButton = computed(() => !isOriginalFileDeleted.value && isOrderedNumeric.value && totalSigners.value > 1 && hasSigners.value)
const shouldShowOrderedOptions = computed(() => isOrderedNumeric.value && totalSigners.value > 1)
const currentUserDisplayName = computed(() => OC.getCurrentUser()?.displayName || '')
const showDocMdpWarning = computed(() => filesStore.isDocMdpNoChangesAllowed() && !filesStore.canAddSigner())
const fileName = computed(() => filesStore.getFile()?.name ?? '')
const isEnvelope = computed(() => filesStore.getFile()?.nodeType === 'envelope')
const envelopeFilesCount = computed(() => filesStore.getFile()?.filesCount || 0)
const size = computed(() => window.matchMedia('(max-width: 512px)').matches ? 'full' : 'normal')
const modalTitle = computed(() => Object.keys(signerToEdit.value).length > 0 ? t('libresign', 'Edit signer') : t('libresign', 'Add new signer'))
const showSigningProgress = computed(() => signingProgressStatus.value === FILE_STATUS.SIGNING_IN_PROGRESS)
const currentFile = computed<RequestTabFile | null>(() => (filesStore.getFile() as RequestTabFile | null) ?? null)
const signingProgressView = computed<SigningProgressView | null>(() => {
	if (!signingProgress.value) {
		return null
	}

	return {
		total: signingProgress.value.total,
		signed: signingProgress.value.signed,
		signers: signingProgress.value.signers?.map(signer => ({
			id: signer.id,
			displayName: signer.displayName,
			signed: signer.signed !== null,
		})),
	}
})
const signingOrderDiagramSigners = computed<SigningOrderDiagramSigner[]>(() => {
	const signers = filesStore.getFile()?.signers || []
	return signers.map((signer: SignerRow) => ({
		displayName: signer.displayName,
		signed: isSignerSigned(signer),
		signingOrder: signer.signingOrder,
	}))
})

function normalizeSignatureFlow(flow: unknown): SignatureFlowValue | null {
	if (flow === 'none' || flow === 'parallel' || flow === 'ordered_numeric' || flow === 0 || flow === 1 || flow === 2) {
		return flow
	}
	return null
}

function getSignerMethod(signer: { identifyMethods?: Array<Pick<IdentifyMethodRecord, 'method'>> }): string | undefined {
	return signer.identifyMethods?.[0]?.method
}

function toIdentifySignerToEdit(signer: SignerRow): IdentifySignerToEdit {
	return {
		identify: typeof signer.identify === 'string' ? signer.identify : undefined,
		signRequestId: signer.signRequestId !== undefined ? String(signer.signRequestId) : undefined,
		displayName: signer.displayName,
		description: signer.description ?? undefined,
		identifyMethods: signer.identifyMethods?.map(method => ({
			method: method.method,
			value: method.value ?? '',
		})),
	}
}

function getMethodConfig(methodName: string | undefined): IdentifyMethodConfig | undefined {
	if (!methodName) {
		return undefined
	}
	return methods.value.find(method => method.name === methodName)
}

function getOcsErrorData(error: unknown): OcsErrorData | null {
	if (typeof error !== 'object' || error === null || !('response' in error)) {
		return null
	}

	const response = error.response
	if (typeof response !== 'object' || response === null || !('data' in response)) {
		return null
	}

	const data = response.data
	if (typeof data !== 'object' || data === null || !('ocs' in data)) {
		return null
	}

	const ocs = data.ocs
	if (typeof ocs !== 'object' || ocs === null || !('data' in ocs)) {
		return null
	}

	return ocs.data as OcsErrorData
}

function showRequestError(error: unknown, fallbackMessage: string): void {
	const data = getOcsErrorData(error)
	if (!data) {
		showError(fallbackMessage)
		return
	}

	if ('message' in data && typeof data.message === 'string' && data.message.length > 0) {
		showError(data.message)
		return
	}

	if ('messages' in data && Array.isArray(data.messages) && data.messages.length > 0) {
		data.messages.forEach(currentMessage => showError(currentMessage.message))
		return
	}

	if ('errors' in data && Array.isArray(data.errors) && data.errors.length > 0) {
		data.errors.forEach(currentError => showError(currentError.message))
		return
	}

	showError(fallbackMessage)
}

function isSignerSigned(signer: Partial<SignerRow>) {
	if (Array.isArray(signer?.signed)) {
		return signer.signed.length > 0
	}
	return !!signer?.signed
}

const canEditSigningOrder = computed(() => (signer: Partial<SignerRow>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	const minSigners = isAdminFlowForced.value ? 1 : 2
	return isOrderedNumeric.value && totalSigners.value >= minSigners && filesStore.canSave() && !isSignerSigned(signer)
})

const canDelete = computed(() => (signer: Partial<SignerRow>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	return filesStore.canSave() && !isSignerSigned(signer)
})

function canSignerActInOrder(signer: Partial<SignerRow>) {
	const methodConfig = getMethodConfig(getSignerMethod(signer))
	if (methodConfig && !methodConfig.enabled) {
			return false
	}

	if (!isOrderedNumeric.value) {
		return true
	}

	const file = filesStore.getFile()
	const signerOrder = signer.signingOrder || 1
	const signers = Array.isArray(file?.signers) ? file.signers : []
	const hasPendingLowerOrder = signers.some((currentSigner: SignerRow) => {
		const otherOrder = currentSigner.signingOrder || 1
		return otherOrder < signerOrder && !isSignerSigned(currentSigner)
	})

	return !hasPendingLowerOrder
}

const canCustomizeMessage = computed(() => (signer: Partial<SignerRow>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	if (isSignerSigned(signer) || !signer.signRequestId || signer.me) {
		return false
	}

	const method = getSignerMethod(signer)
	if (method === 'account' && !signer.acceptsEmailNotifications) {
		return false
	}

	if (!canSignerActInOrder(signer)) {
		return false
	}

	return !!method
})

const canRequestSignature = computed(() => (signer: Partial<SignerRow>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	const file = filesStore.getFile()
	if (!filesStore.canRequestSign
		|| file?.status === FILE_STATUS.DRAFT
		|| isSignerSigned(signer)
		|| !signer.signRequestId
		|| signer.me
		|| signer.status !== 0) {
		return false
	}

	return canSignerActInOrder(signer)
})

const canSendReminder = computed(() => (signer: Partial<SignerRow>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	const file = filesStore.getFile()
	if (!filesStore.canRequestSign
		|| file?.status === FILE_STATUS.DRAFT
		|| isSignerSigned(signer)
		|| !signer.signRequestId
		|| signer.me
		|| signer.status !== 1) {
		return false
	}

	return canSignerActInOrder(signer)
})

const hasSignersWithDisabledMethods = computed(() => {
	const file = filesStore.getFile()
	if (!file?.signers) {
		return false
	}

	return file.signers.some((signer: SignerRow) => {
		if (isSignerSigned(signer)) {
			return false
		}
		const method = getSignerMethod(signer)
		if (!method) {
			return false
		}
		const methodConfig = getMethodConfig(method)
		return !methodConfig?.enabled
	})
})

function hasAnyDraftSigner(file: RequestTabFile | null | undefined) {
	const fileSigners = file?.signers
	const signers: SignerRow[] = Array.isArray(fileSigners) ? fileSigners : []
	return signers.some((signer: SignerRow) => signer.status === SIGN_REQUEST_STATUS.DRAFT)
}

function getCurrentSigningOrder(signersNotSigned: SignerRow[]) {
	return Math.min(...signersNotSigned.map(s => s.signingOrder || 1))
}

function hasOrderDraftSigners(file: RequestTabFile | null | undefined, order: number) {
	const fileSigners = file?.signers
	const signers: SignerRow[] = Array.isArray(fileSigners) ? fileSigners : []
	return signers.some((signer: SignerRow) => {
		const signerOrder = signer.signingOrder || 1
		return signerOrder === order && signer.status === SIGN_REQUEST_STATUS.DRAFT
	})
}

function hasSequentialDraftSigners(file: RequestTabFile | null | undefined) {
	const fileSigners = file?.signers
	const signers: SignerRow[] = Array.isArray(fileSigners) ? fileSigners : []
	const signersNotSigned = signers.filter((signer: SignerRow) => !isSignerSigned(signer))
	if (signersNotSigned.length === 0) {
		return false
	}

	const currentOrder = getCurrentSigningOrder(signersNotSigned)
	return hasOrderDraftSigners(file, currentOrder)
}

const hasDraftSigners = computed(() => {
	const file = filesStore.getFile() as RequestTabFile
	if (!file?.signers) {
		return false
	}

	return isOrderedNumeric.value ? hasSequentialDraftSigners(file) : hasAnyDraftSigner(file)
})

const showSaveButton = computed(() => {
	if (isOriginalFileDeleted.value || !filesStore.canSave() || !isSignElementsAvailable()) {
		return false
	}
	const file = filesStore.getFile()
	if (file.status === FILE_STATUS.PARTIAL_SIGNED || file.status === FILE_STATUS.SIGNED) {
		return false
	}
	if (hasSignersWithDisabledMethods.value) {
		return false
	}
	return true
})

const showRequestButton = computed(() => {
	if (isOriginalFileDeleted.value || !filesStore.canSave() || hasSignersWithDisabledMethods.value) {
		return false
	}
	return hasDraftSigners.value
})

const enabledMethods = computed(() => {
	if (Object.keys(signerToEdit.value).length > 0 && signerToEdit.value.identifyMethods?.length) {
		const signerMethod = getSignerMethod(signerToEdit.value)
		const signerMethodConfig = getMethodConfig(signerMethod)
		if (signerMethodConfig) {
			return [signerMethodConfig]
		}
	}
	return methods.value.filter(method => method.enabled)
})

const isSignerMethodDisabled = computed(() => {
	if (Object.keys(signerToEdit.value).length > 0 && signerToEdit.value.identifyMethods?.length) {
		const signerMethod = getSignerMethod(signerToEdit.value)
		const methodConfig = getMethodConfig(signerMethod)
		return !methodConfig?.enabled
	}
	return false
})

const disabledMethodName = computed(() => {
	if (isSignerMethodDisabled.value && signerToEdit.value.identifyMethods?.length) {
		const signerMethod = getSignerMethod(signerToEdit.value)
		const methodConfig = getMethodConfig(signerMethod)
		return methodConfig?.friendly_name || signerMethod
	}
	return ''
})

const debouncedSave = debounce(async () => {
	try {
		const file = filesStore.getFile()
		const signers = isOrderedNumeric.value ? file?.signers : null
		const signatureFlow = normalizeSignatureFlow(file?.signatureFlow)
		await filesStore.saveOrUpdateSignatureRequest({
			signers,
			signatureFlow,
		})
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to save signature request'))
	}
}, 1000)

const debouncedTabChange = debounce((tabId: string) => {
	userConfigStore.update('files_list_signer_identify_tab', tabId)
}, 500)

function onPreserveOrderChange(value: boolean) {
	preserveOrder.value = value
	const file = filesStore.getEditableFile()

	if (value) {
		if (file?.signers) {
			const orders = file.signers.map((signer: SignerRow) => signer.signingOrder || 0)
			const hasDuplicateOrders = orders.length !== new Set(orders).size
			file.signers.forEach((signer: SignerRow, index: number) => {
				if (!signer.signingOrder || hasDuplicateOrders) {
					signer.signingOrder = index + 1
				}
			})
		}
		if (file) {
			file.signatureFlow = 'ordered_numeric'
		}
	} else if (!isAdminFlowForced.value) {
		if (file?.signers) {
			file.signers.forEach((signer: SignerRow) => {
				if (!isSignerSigned(signer)) {
					signer.signingOrder = 1
				}
			})
		}
		if (file) {
			file.signatureFlow = 'parallel'
		}
	}

	debouncedSave()
}

function syncPreserveOrderWithFile() {
	const file = filesStore.getFile()
	if (!file) {
		preserveOrder.value = false
		return
	}

	const flow = file.signatureFlow
	const normalizedFlow = normalizeSignatureFlow(flow)
	preserveOrder.value = (normalizedFlow === 'ordered_numeric' || normalizedFlow === 2) && !isAdminFlowForced.value
}

function getSvgIcon(name: string) {
	const iconByMethod: Record<string, string> = {
		account: svgAccount,
		email: svgEmail,
		signal: svgSignal,
		sms: svgSms,
		telegram: svgTelegram,
		whatsapp: svgWhatsapp,
		xmpp: svgXmpp,
	}
	return iconByMethod[name] || svgAccount
}

function isSignElementsAvailable() {
	return capabilities.libresign.config['sign-elements']['is-available'] === true
}

function closeModal() {
	modalSrc.value = ''
	filesStore.flushSelectedFile()
}

function getValidationFileUuid() {
	const file = filesStore.getFile()
	if (file?.uuid) {
		return file.uuid
	}

	const signer = file?.signers?.find((row: SignerRow) => row.me) || file?.signers?.[0] || {}
	if (signer?.sign_uuid) {
		return signer.sign_uuid
	}

	const loadedUuid = loadState('libresign', 'sign_request_uuid', null)
	if (loadedUuid) {
		return loadedUuid
	}

	if (file?.id) {
		return file.id
	}

	return null
}

function validationFile() {
	const targetUuid = getValidationFileUuid()
	if (!targetUuid) {
		showError(t('libresign', 'Document not found'))
		return
	}

	if (props.useModal) {
		const absoluteUrl = generateUrl('/apps/libresign/p/validation/{uuid}', { uuid: targetUuid })
		const route = router.resolve({ name: 'ValidationFileExternal', params: { uuid: targetUuid } })
		modalSrc.value = route.href || absoluteUrl
		return
	}
	router.push({ name: 'ValidationFile', params: { uuid: targetUuid } })
	sidebarStore.hideSidebar()
}

function addSigner() {
	signerToEdit.value = {}
	activeTab.value = userConfigStore.files_list_signer_identify_tab || ''
	filesStore.enableIdentifySigner()
}

function editSigner(signer: SignerRow) {
	signerToEdit.value = toIdentifySignerToEdit(signer)
	const signerMethod = getSignerMethod(signer)
	if (signerMethod) {
		activeTab.value = `tab-${signerMethod}`
	}
	filesStore.enableIdentifySigner()
}

function customizeMessage(signer: SignerRow) {
	signerToEdit.value = toIdentifySignerToEdit(signer)
	filesStore.enableIdentifySigner()
}

function onTabChange(tabId: string) {
	if (activeTab.value !== tabId) {
		activeTab.value = tabId
		debouncedTabChange(tabId)
	}
}

function updateSigningOrder(signer: SignerRow, value: string) {
	const order = parseInt(value, 10)
	const file = filesStore.getFile() as RequestTabFile
	if (isNaN(order)) {
		return
	}

	const currentIndex = file.signers?.findIndex((currentSigner: SignerRow) => currentSigner.identify === signer.identify) ?? -1
	if (currentIndex === -1) {
		return
	}

	if (!file.signers) {
		return
	}

	const currentSigner = file.signers[currentIndex]
	if (!currentSigner) {
		return
	}

	currentSigner.signingOrder = order
	file.signers = [...file.signers].sort((left: SignerRow, right: SignerRow) => {
		const orderLeft = left.signingOrder || 999
		const orderRight = right.signingOrder || 999
		if (orderLeft === orderRight) {
			return 0
		}
		return orderLeft - orderRight
	})
}

function confirmSigningOrder(signer: SignerRow) {
	const file = filesStore.getFile() as RequestTabFile
	const currentIndex = file.signers?.findIndex((currentSigner: SignerRow) => currentSigner.identify === signer.identify) ?? -1
	if (currentIndex === -1) {
		return
	}
	if (!file.signers) {
		return
	}

	const currentSigner = file.signers[currentIndex]
	if (!currentSigner) {
		return
	}

	const order = currentSigner.signingOrder
	const oldOrder = signer.signingOrder
	if (order === undefined || oldOrder === undefined) {
		return
	}

	for (let index = 0; index < file.signers.length; index++) {
		if (index === currentIndex) continue
		const currentItem = file.signers[index]
		const currentItemOrder = currentItem?.signingOrder
		if (!currentItem || currentItemOrder === undefined) {
			continue
		}
		if (order < oldOrder) {
			if (currentItemOrder >= order && currentItemOrder < oldOrder) {
				currentItem.signingOrder = currentItemOrder + 1
			}
		} else if (order > oldOrder) {
			if (currentItemOrder > oldOrder && currentItemOrder <= order) {
				currentItem.signingOrder = currentItemOrder - 1
			}
		}
	}

	const sortedSigners = [...file.signers].sort((left: SignerRow, right: SignerRow) => {
		const orderLeft = left.signingOrder || 999
		const orderRight = right.signingOrder || 999
		return orderLeft - orderRight
	})

	if (sortedSigners.every(currentSigner => typeof currentSigner.signingOrder === 'number')) {
		normalizeSigningOrders(sortedSigners as Array<{ signingOrder: number }>)
	}
	file.signers = sortedSigners
	debouncedSave()
}

async function sendNotify(signer: SignerRow) {
	if (!signer.signRequestId) {
		showError(t('libresign', 'Signer request not found'))
		return
	}
	const file = filesStore.getFile() as RequestTabFile
	if (!file?.id) {
		showError(t('libresign', 'Document not found'))
		return
	}

	const body = {
		fileId: file.id,
		signRequestId: signer.signRequestId,
	}

	await axios.post(generateOcsUrl('/apps/libresign/api/v1/notify/signer'), body)
		.then(({ data }: { data: NotifySignerSuccess }) => {
			showSuccess(t('libresign', data.ocs.data.message))
		})
		.catch((error: unknown) => {
			showRequestError(error, t('libresign', 'Failed to send reminder'))
		})
}

async function requestSignatureForSigner(signer: SignerRow) {
	selectedSigner.value = signer
	showConfirmRequestSigner.value = true
}

async function confirmRequestSigner() {
	if (!selectedSigner.value) {
		return
	}

	hasLoading.value = true
	try {
		const selectedSignRequestId = selectedSigner.value.signRequestId
		if (!selectedSignRequestId) {
			showError(t('libresign', 'Signer request not found'))
			return
		}
		const file = filesStore.getFile() as RequestTabFile
		const signers = (file.signers || []).map((signer: SignerRow) => {
			if (signer.signRequestId === selectedSignRequestId) {
				return { ...signer, status: 1 }
			}
			return signer
		})
		await filesStore.saveOrUpdateSignatureRequest({ signers: signers as never, status: 1 })
		showSuccess(t('libresign', 'Signature requested'))
		showConfirmRequestSigner.value = false
		selectedSigner.value = null
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to request signature'))
	}
	hasLoading.value = false
}

async function sign() {
	const file = filesStore.getFile()
	if (file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		validationFile()
		return
	}

	const uuid = 'signUuid' in file ? file.signUuid : null
	if (props.useModal) {
		const absoluteUrl = generateUrl('/apps/libresign/p/sign/{uuid}/pdf', { uuid })
		const route = router.resolve({ name: 'SignPDFExternal', params: { uuid } })
		modalSrc.value = route.href || absoluteUrl
		return
	}
	signStore.setFileToSign(filesStore.getFile())
	router.push({ name: 'SignPDF', params: { uuid } })
}

async function save() {
	hasLoading.value = true
	try {
		await filesStore.saveOrUpdateSignatureRequest({})
		emit('libresign:show-visible-elements', new CustomEvent('libresign:show-visible-elements'))
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to save signature request'))
	}
	hasLoading.value = false
}

async function request() {
	showConfirmRequest.value = true
}

async function confirmRequest() {
	hasLoading.value = true
	try {
		const response = await filesStore.saveOrUpdateSignatureRequest({ status: 1 })
		showSuccess(t('libresign', response.message || 'Signature requested'))
		showConfirmRequest.value = false
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to request signatures'))
	}
	hasLoading.value = false
}

async function openManageFiles() {
	hasLoading.value = true
	const response = await filesStore.saveOrUpdateSignatureRequest({})
	hasLoading.value = false
	if (response && 'success' in response && response.success === false && response.message) {
		showError(response.message)
		return
	}
	showEnvelopeFilesDialog.value = true
}

function openFile() {
	const file = filesStore.getFile()
	const fileUrl = documentData.value?.files?.[0]?.file || (file?.uuid ? generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: file.uuid }) : null)
	if (!fileUrl) {
		showError(t('libresign', 'Document URL not found'))
		return
	}
	if (typeof file?.name !== 'string' || typeof file?.nodeId !== 'number') {
		showError(t('libresign', 'Document not found'))
		return
	}

	openDocument({
		fileUrl,
		filename: file.name,
		nodeId: file.nodeId,
	})
}

function startSigningProgressPolling() {
	const file = filesStore.getFile()
	if (!file?.id) {
		return
	}

	signingProgressStatus.value = file.status ?? null
	signingProgressStatusText.value = file.statusText || ''
	signingProgress.value = null

	stopPollingFunction.value = startLongPolling(
		file.id,
		file.status ?? 0,
		(data: PollingStatusData) => {
			signingProgressStatus.value = data.status
			signingProgressStatusText.value = data.statusText || ''
			signingProgress.value = data.progress || null

			const currentFile = filesStore.getEditableFile()
			if (currentFile) {
				currentFile.status = data.status
				currentFile.statusText = data.statusText || currentFile.statusText
			}
		},
		() => !filesStore.getFile() || filesStore.getFile().id !== file.id,
		(error: unknown) => {
			console.error('Error during signing progress polling:', error)
			showError(t('libresign', 'Error monitoring signing progress'))
		},
	)
}

function stopSigningProgressPolling() {
	if (stopPollingFunction.value) {
		stopPollingFunction.value()
		stopPollingFunction.value = null
	}
	signingProgress.value = null
	signingProgressStatus.value = null
	signingProgressStatusText.value = ''
}

watch(() => filesStore.selectedFileId, (newFileId) => {
	if (newFileId) {
		syncPreserveOrderWithFile()
	}
}, { immediate: true })

const handleEditSigner = ((event: NextcloudEvent) => {
	editSigner((event as CustomEvent<SignerRow>).detail)
}) as EventHandler<NextcloudEvent>

watch(() => currentFile.value?.status, (newStatus) => {
	if (newStatus === FILE_STATUS.SIGNING_IN_PROGRESS) {
		startSigningProgressPolling()
	} else if (stopPollingFunction.value) {
		stopSigningProgressPolling()
	}
})

onMounted(() => {
	subscribe('libresign:edit-signer', handleEditSigner)
	filesStore.disableIdentifySigner()
	activeTab.value = userConfigStore.files_list_signer_identify_tab || ''
	syncPreserveOrderWithFile()
})

onBeforeUnmount(() => {
	unsubscribe('libresign:edit-signer', handleEditSigner)
	if (stopPollingFunction.value) {
		stopSigningProgressPolling()
	}
})

defineExpose({
	hasLoading,
	signerToEdit,
	modalSrc,
	document: documentData,
	methods,
	showConfirmRequest,
	showConfirmRequestSigner,
	selectedSigner,
	activeTab,
	preserveOrder,
	showOrderDiagram,
	showEnvelopeFilesDialog,
	adminSignatureFlow,
	debouncedSave,
	debouncedTabChange,
	signingProgress,
	signingProgressStatus,
	signingProgressStatusText,
	stopPollingFunction,
	signatureFlow,
	isAdminFlowForced,
	isOrderedNumeric,
	showSigningOrderOptions,
	showPreserveOrder,
	showViewOrderButton,
	shouldShowOrderedOptions,
	currentUserDisplayName,
	showDocMdpWarning,
	isOriginalFileDeleted,
	canEditSigningOrder,
	canDelete,
	canCustomizeMessage,
	canRequestSignature,
	canSendReminder,
	hasSignersWithDisabledMethods,
	showSaveButton,
	showRequestButton,
	hasDraftSigners,
	hasSigners,
	totalSigners,
	fileName,
	isEnvelope,
	envelopeFilesCount,
	size,
	modalTitle,
	enabledMethods,
	isSignerMethodDisabled,
	disabledMethodName,
	showSigningProgress,
	isSignerSigned,
	onPreserveOrderChange,
	syncPreserveOrderWithFile,
	getSvgIcon,
	canSignerActInOrder,
	hasAnyDraftSigner,
	hasSequentialDraftSigners,
	getCurrentSigningOrder,
	hasOrderDraftSigners,
	isSignElementsAvailable,
	closeModal,
	getValidationFileUuid,
	validationFile,
	addSigner,
	editSigner,
	customizeMessage,
	onTabChange,
	updateSigningOrder,
	confirmSigningOrder,
	sendNotify,
	requestSignatureForSigner,
	confirmRequestSigner,
	sign,
	save,
	request,
	confirmRequest,
	openManageFiles,
	openFile,
	startSigningProgressPolling,
	stopSigningProgressPolling,
	recalculateSigningOrders,
	normalizeSigningOrders,
})
</script>
<style lang="scss" scoped>

:deep(.checkbox-radio-switch) {
	margin: 8px 0;
}

.action-form-box {
	margin-top: 6px;
}

.iframe {
	width: 100%;
	height: 100%;
}

#request-signature-identify-signer {
	:deep(.app-sidebar-header) {
		display: none;
	}
	:deep(aside) {
		border-left: unset;
	}
	:deep(.app-sidebar__close) {
		display: none;
	}
	:deep(.app-sidebar__tab) {
		box-sizing: border-box;
	}
	@media (min-width: 513px) {
		:deep(#app-sidebar-vue) {
			width: unset;
		}
	}
}
</style>
