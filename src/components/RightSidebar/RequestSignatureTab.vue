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
			:progress="signingProgress ?? undefined"
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
			<SigningOrderDiagram :signers="filesStore.getFile()?.signers || []"
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
import type { NextcloudCapabilities } from '../../types/capabilities'

defineOptions({
	name: 'RequestSignatureTab',
})

type SigningProgressData = {
	total: number
	signed: number
	files?: Array<{
		uuid: string
		name: string
		signedCount: number
		totalSigners: number
		isSigned: boolean
	}>
	signers?: Array<{
		id: string | number
		displayName: string
		signed: boolean
	}>
}

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
const capabilities = getCapabilities() as NextcloudCapabilities

const hasLoading = ref(false)
const signerToEdit = ref<Record<string, any>>({})
const modalSrc = ref('')
const documentData = ref<Record<string, any>>(loadState('libresign', 'file_info', {}))
const methods = ref<any[]>(loadState('libresign', 'identify_methods', []))
const showConfirmRequest = ref(false)
const showConfirmRequestSigner = ref(false)
const selectedSigner = ref<any | null>(null)
const activeTab = ref('')
const preserveOrder = ref(false)
const showOrderDiagram = ref(false)
const showEnvelopeFilesDialog = ref(false)
const adminSignatureFlow = ref(loadState('libresign', 'signature_flow', 'none'))
const signingProgress = ref<SigningProgressData | null>(null)
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
const currentFile = computed(() => filesStore.getFile())

function isSignerSigned(signer: any) {
	if (Array.isArray(signer?.signed)) {
		return signer.signed.length > 0
	}
	return !!signer?.signed
}

const canEditSigningOrder = computed(() => (signer: any) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	const minSigners = isAdminFlowForced.value ? 1 : 2
	return isOrderedNumeric.value && totalSigners.value >= minSigners && filesStore.canSave() && !isSignerSigned(signer)
})

const canDelete = computed(() => (signer: any) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	return filesStore.canSave() && !isSignerSigned(signer)
})

function canSignerActInOrder(signer: any) {
	const method = signer.identifyMethods?.[0]?.method
	if (method) {
		const methodConfig = methods.value.find(m => m.name === method)
		if (!methodConfig?.enabled) {
			return false
		}
	}

	if (!isOrderedNumeric.value) {
		return true
	}

	const file = filesStore.getFile()
	const signerOrder = signer.signingOrder || 1
	const signers = Array.isArray(file?.signers) ? file.signers : []
	const hasPendingLowerOrder = signers.some((currentSigner: any) => {
		const otherOrder = currentSigner.signingOrder || 1
		return otherOrder < signerOrder && !isSignerSigned(currentSigner)
	})

	return !hasPendingLowerOrder
}

const canCustomizeMessage = computed(() => (signer: any) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	if (isSignerSigned(signer) || !signer.signRequestId || signer.me) {
		return false
	}

	const method = signer.identifyMethods?.[0]?.method
	if (method === 'account' && !signer.acceptsEmailNotifications) {
		return false
	}

	if (!canSignerActInOrder(signer)) {
		return false
	}

	return !!method
})

const canRequestSignature = computed(() => (signer: any) => {
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

const canSendReminder = computed(() => (signer: any) => {
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

	return file.signers.some((signer: any) => {
		if (isSignerSigned(signer)) {
			return false
		}
		const method = signer.identifyMethods?.[0]?.method
		if (!method) {
			return false
		}
		const methodConfig = methods.value.find(m => m.name === method)
		return !methodConfig?.enabled
	})
})

function hasAnyDraftSigner(file: any) {
	const signers = Array.isArray(file?.signers) ? file.signers : []
	return signers.some((signer: any) => signer.status === SIGN_REQUEST_STATUS.DRAFT)
}

function getCurrentSigningOrder(signersNotSigned: any[]) {
	return Math.min(...signersNotSigned.map(s => s.signingOrder || 1))
}

function hasOrderDraftSigners(file: any, order: number) {
	const signers = Array.isArray(file?.signers) ? file.signers : []
	return signers.some((signer: any) => {
		const signerOrder = signer.signingOrder || 1
		return signerOrder === order && signer.status === SIGN_REQUEST_STATUS.DRAFT
	})
}

function hasSequentialDraftSigners(file: any) {
	const signers = Array.isArray(file?.signers) ? file.signers : []
	const signersNotSigned = signers.filter((signer: any) => !isSignerSigned(signer))
	if (signersNotSigned.length === 0) {
		return false
	}

	const currentOrder = getCurrentSigningOrder(signersNotSigned)
	return hasOrderDraftSigners(file, currentOrder)
}

const hasDraftSigners = computed(() => {
	const file = filesStore.getFile()
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
		const signerMethod = signerToEdit.value.identifyMethods[0].method
		const signerMethodConfig = methods.value.find(m => m.name === signerMethod)
		if (signerMethodConfig) {
			return [signerMethodConfig]
		}
	}
	return methods.value.filter(method => method.enabled)
})

const isSignerMethodDisabled = computed(() => {
	if (Object.keys(signerToEdit.value).length > 0 && signerToEdit.value.identifyMethods?.length) {
		const signerMethod = signerToEdit.value.identifyMethods[0].method
		const methodConfig = methods.value.find(m => m.name === signerMethod)
		return !methodConfig?.enabled
	}
	return false
})

const disabledMethodName = computed(() => {
	if (isSignerMethodDisabled.value && signerToEdit.value.identifyMethods?.length) {
		const signerMethod = signerToEdit.value.identifyMethods[0].method
		const methodConfig = methods.value.find(m => m.name === signerMethod)
		return methodConfig?.friendly_name || signerMethod
	}
	return ''
})

const debouncedSave = debounce(async () => {
	try {
		const file = filesStore.getFile()
		const signers = isOrderedNumeric.value ? file?.signers : null
		await filesStore.saveOrUpdateSignatureRequest({
			signers,
			signatureFlow: file?.signatureFlow,
		})
	} catch (error: any) {
		if (error.response?.data?.ocs?.data?.message) {
			showError(error.response.data.ocs.data.message)
		} else if (error.response?.data?.ocs?.data?.errors) {
			error.response.data.ocs.data.errors.forEach((currentError: any) => showError(currentError.message))
		}
	}
}, 1000)

const debouncedTabChange = debounce((tabId: string) => {
	userConfigStore.update('files_list_signer_identify_tab', tabId)
}, 500)

function onPreserveOrderChange(value: boolean) {
	preserveOrder.value = value
	const file = filesStore.getFile()

	if (value) {
		if (file?.signers) {
			const orders = file.signers.map((signer: any) => signer.signingOrder || 0)
			const hasDuplicateOrders = orders.length !== new Set(orders).size
			file.signers.forEach((signer: any, index: number) => {
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
			file.signers.forEach((signer: any) => {
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
	preserveOrder.value = (flow === 'ordered_numeric' || flow === 2) && !isAdminFlowForced.value
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

	const signer = file?.signers?.find((row: any) => row.me) || file?.signers?.[0] || {}
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

function editSigner(signer: any) {
	signerToEdit.value = signer
	if (signer.identifyMethods?.length) {
		const signerMethod = signer.identifyMethods[0].method
		activeTab.value = `tab-${signerMethod}`
	}
	filesStore.enableIdentifySigner()
}

function customizeMessage(signer: any) {
	signerToEdit.value = signer
	filesStore.enableIdentifySigner()
}

function onTabChange(tabId: string) {
	if (activeTab.value !== tabId) {
		activeTab.value = tabId
		debouncedTabChange(tabId)
	}
}

function updateSigningOrder(signer: any, value: string) {
	const order = parseInt(value, 10)
	const file = filesStore.getFile()
	if (isNaN(order)) {
		return
	}

	const currentIndex = file.signers.findIndex((currentSigner: any) => currentSigner.identify === signer.identify)
	if (currentIndex === -1) {
		return
	}

	file.signers[currentIndex].signingOrder = order
	file.signers = [...file.signers].sort((left: any, right: any) => {
		const orderLeft = left.signingOrder || 999
		const orderRight = right.signingOrder || 999
		if (orderLeft === orderRight) {
			return 0
		}
		return orderLeft - orderRight
	})
}

function confirmSigningOrder(signer: any) {
	const file = filesStore.getFile()
	const currentIndex = file.signers.findIndex((currentSigner: any) => currentSigner.identify === signer.identify)
	if (currentIndex === -1) {
		return
	}

	const order = file.signers[currentIndex].signingOrder
	const oldOrder = signer.signingOrder

	for (let index = 0; index < file.signers.length; index++) {
		if (index === currentIndex) continue
		const currentItemOrder = file.signers[index].signingOrder
		if (order < oldOrder) {
			if (currentItemOrder >= order && currentItemOrder < oldOrder) {
				file.signers[index].signingOrder = currentItemOrder + 1
			}
		} else if (order > oldOrder) {
			if (currentItemOrder > oldOrder && currentItemOrder <= order) {
				file.signers[index].signingOrder = currentItemOrder - 1
			}
		}
	}

	const sortedSigners = [...file.signers].sort((left: any, right: any) => {
		const orderLeft = left.signingOrder || 999
		const orderRight = right.signingOrder || 999
		return orderLeft - orderRight
	})

	normalizeSigningOrders(sortedSigners)
	file.signers = sortedSigners
	debouncedSave()
}

async function sendNotify(signer: any) {
	const body = {
		fileId: filesStore.getFile().id,
		signRequestId: signer.signRequestId,
	}

	await axios.post(generateOcsUrl('/apps/libresign/api/v1/notify/signer'), body)
		.then(({ data }) => {
			showSuccess(t('libresign', data.ocs.data.message))
		})
		.catch(({ response }) => {
			showError(response.data.ocs.data.message)
		})
}

async function requestSignatureForSigner(signer: any) {
	selectedSigner.value = signer
	showConfirmRequestSigner.value = true
}

async function confirmRequestSigner() {
	if (!selectedSigner.value) {
		return
	}

	hasLoading.value = true
	try {
		const file = filesStore.getFile()
		const signers = file.signers.map((signer: any) => {
			if (signer.signRequestId === selectedSigner.value.signRequestId) {
				return { ...signer, status: 1 }
			}
			return signer
		})
		await filesStore.saveOrUpdateSignatureRequest({ signers, status: 1 })
		showSuccess(t('libresign', 'Signature requested'))
		showConfirmRequestSigner.value = false
		selectedSigner.value = null
	} catch (error: any) {
		if (error.response?.data?.ocs?.data?.message) {
			showError(error.response.data.ocs.data.message)
		} else if (error.response?.data?.ocs?.data?.errors) {
			error.response.data.ocs.data.errors.forEach((currentError: any) => showError(currentError.message))
		}
	}
	hasLoading.value = false
}

async function sign() {
	const file = filesStore.getFile()
	if (file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		validationFile()
		return
	}

	const uuid = file.signUuid
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
	} catch (error: any) {
		if (error.response?.data?.ocs?.data?.message) {
			showError(error.response.data.ocs.data.message)
		} else if (error.response?.data?.ocs?.data?.errors) {
			error.response.data.ocs.data.errors.forEach((currentError: any) => showError(currentError.message))
		}
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
		showSuccess(t('libresign', response.message))
		showConfirmRequest.value = false
	} catch (error: any) {
		if (error.response?.data?.ocs?.data?.message) {
			showError(error.response.data.ocs.data.message)
		} else if (error.response?.data?.ocs?.data?.errors) {
			error.response.data.ocs.data.errors.forEach((currentError: any) => showError(currentError.message))
		}
	}
	hasLoading.value = false
}

async function openManageFiles() {
	hasLoading.value = true
	const response = await filesStore.saveOrUpdateSignatureRequest({})
	hasLoading.value = false
	if (response?.success === false && response?.message) {
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

	signingProgressStatus.value = file.status
	signingProgressStatusText.value = file.statusText || ''
	signingProgress.value = null

	stopPollingFunction.value = startLongPolling(
		file.id,
		file.status,
		(data: any) => {
			signingProgressStatus.value = data.status
			signingProgressStatusText.value = data.statusText
			signingProgress.value = data.progress

			const currentFile = filesStore.getFile()
			if (currentFile) {
				currentFile.status = data.status
				currentFile.statusText = data.statusText
			}
		},
		() => !filesStore.getFile() || filesStore.getFile().id !== file.id,
		(error: any) => {
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
	editSigner((event as CustomEvent<any>).detail)
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
