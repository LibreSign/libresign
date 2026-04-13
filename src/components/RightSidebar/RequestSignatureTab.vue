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
		<NcNoteCard v-if="shouldLoadDetail && isLoadingFileDetail" type="info">
			{{ t('libresign', 'Loading signer details...') }}
		</NcNoteCard>
		<NcNoteCard v-if="showSignatureFlowPreferenceClearedNotice" type="info">
			{{ t('libresign', 'A previous signing order preference was removed because it is no longer compatible with higher-level policy.') }}
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
		<NcCheckboxRadioSwitch v-if="showRememberSignatureFlow && !isOriginalFileDeleted"
			v-model="rememberSignatureFlow"
			type="switch"
			@update:modelValue="onRememberSignatureFlowChange">
			{{ t('libresign', 'Use this as my default signing order') }}
		</NcCheckboxRadioSwitch>
		<div v-if="showFooterTemplateSelector && !isOriginalFileDeleted" class="request-signature-footer-template-selector">
			<label for="request-signature-footer-template-source" class="request-signature-footer-template-selector__label">
				{{ t('libresign', 'Footer template for this request') }}
			</label>
			<select id="request-signature-footer-template-source"
				v-model="selectedFooterTemplateSource"
				class="request-signature-footer-template-selector__input"
				@change="onFooterTemplateSourceChange">
				<option v-for="option in footerTemplateSourceOptions"
					:key="option.value"
					:value="option.value">
					{{ option.label }}
				</option>
			</select>
		</div>
		<NcCheckboxRadioSwitch v-if="showRememberFooterTemplate && !isOriginalFileDeleted"
			v-model="rememberFooterTemplate"
			type="switch"
			@update:modelValue="onRememberFooterTemplateChange">
			{{ t('libresign', 'Use this as my default footer template') }}
		</NcCheckboxRadioSwitch>
		<NcButton v-if="showViewOrderButton && !isOriginalFileDeleted"
			variant="tertiary"
			@click="showOrderDiagram = true">
			<template #icon>
				<NcIconSvgWrapper :path="mdiChartGantt" :size="20" />
			</template>
			{{ t('libresign', 'View signing order') }}
		</NcButton>
		<Signers v-if="!shouldLoadDetail || isCurrentFileDetailed"
			:event="isOriginalFileDeleted ? '' : 'libresign:edit-signer'"
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
import { getSignRequestStatusText } from '../../utils/getSignRequestStatusText.ts'
import { getSigningRouteUuid, getValidationRouteUuid } from '../../utils/signRequestUuid.ts'
import { openDocument } from '../../utils/viewer.js'
import router from '../../router/router'
import { useFilesStore } from '../../store/files.js'
import { usePoliciesStore } from '../../store/policies'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import { useUserConfigStore } from '../../store/userconfig.js'
import { startLongPolling } from '../../services/longPolling'
import { useSigningOrder } from '../../composables/useSigningOrder.js'
import {
	normalizeSignatureFooterPolicyConfig,
	serializeSignatureFooterPolicyConfig,
	type SignatureFooterPolicyConfig,
} from '../../views/Settings/PolicyWorkbench/settings/signature-footer/model'
import type { components, operations } from '../../types/openapi/openapi'
import type {
	EffectivePolicyValue,
	IdentifyMethodRecord,
	IdentifyMethodSetting as IdentifyMethodConfig,
	LibresignCapabilities as RequestSignatureTabCapabilities,
	SignatureFlowValue,
} from '../../types/index'

type FilesStoreContract = ReturnType<typeof useFilesStore>
type EditableRequestFile = ReturnType<FilesStoreContract['getEditableFile']>
type EditableRequestSigner = NonNullable<NonNullable<EditableRequestFile['signers']>[number]>

type LoadedDocumentState = EditableRequestFile

type IdentifySignerMethod = Pick<IdentifyMethodRecord, 'method' | 'value'>
type IdentifySignerToEdit = {
	localKey?: string
	displayName?: string
	description?: string
	identifyMethods?: IdentifySignerMethod[]
}
type ResolvedSignatureFlowMode = 'none' | 'parallel' | 'ordered_numeric'
type SigningOrderDiagramSigner = {
	displayName?: string
	signed?: boolean
	signingOrder?: number
}
type FooterTemplateSource = 'effective' | 'inherited'
type FooterTemplateSourceOption = {
	value: FooterTemplateSource
	label: string
	policyValue: string
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
const policiesStore = usePoliciesStore()
const signStore = useSignStore()
const sidebarStore = useSidebarStore()
const userConfigStore = useUserConfigStore() as ReturnType<typeof useUserConfigStore> & {
	files_list_signer_identify_tab?: string
}
const { normalizeSigningOrders, recalculateSigningOrders } = useSigningOrder()
const capabilities = getCapabilities() as RequestSignatureTabCapabilities
const EMPTY_DOCUMENT_STATE: LoadedDocumentState = {}
const EMPTY_IDENTIFY_METHODS: IdentifyMethodConfig[] = []

const hasLoading = ref(false)
const isLoadingFileDetail = ref(false)
const signerToEdit = ref<IdentifySignerToEdit>({})
const modalSrc = ref('')
const documentData = ref<LoadedDocumentState>(loadState<LoadedDocumentState>('libresign', 'file_info', EMPTY_DOCUMENT_STATE))
const methods = ref<IdentifyMethodConfig[]>(loadState<IdentifyMethodConfig[]>('libresign', 'identify_methods', EMPTY_IDENTIFY_METHODS))
const showConfirmRequest = ref(false)
const showConfirmRequestSigner = ref(false)
const selectedSigner = ref<EditableRequestSigner | null>(null)
const activeTab = ref('')
const preserveOrder = ref(false)
const rememberSignatureFlow = ref(false)
const rememberFooterTemplate = ref(false)
const selectedFooterTemplateSource = ref<FooterTemplateSource>('effective')
const showOrderDiagram = ref(false)
const showEnvelopeFilesDialog = ref(false)
const signingProgress = ref<components['schemas']['ProgressPayload'] | null>(null)
const signingProgressStatus = ref<number | null>(null)
const signingProgressStatusText = ref('')
const stopPollingFunction = ref<null | (() => void)>(null)

const signatureFlowPolicy = computed(() => policiesStore.getPolicy('signature_flow'))
const footerPolicy = computed(() => policiesStore.getPolicy('add_footer'))
const canChooseSigningOrderAtRequestLevel = computed(() => policiesStore.canUseRequestOverride('signature_flow'))
const canChooseFooterTemplateAtRequestLevel = computed(() => policiesStore.canUseRequestOverride('add_footer'))
const isAdminFlowForced = computed(() => !canChooseSigningOrderAtRequestLevel.value)

const signatureFlow = computed(() => {
	const file = filesStore.getFile()
	const resolvedPolicy = toSignatureFlowMode(signatureFlowPolicy.value?.effectiveValue)
	const fileFlow = file?.signatureFlow
	const resolvedFileFlow = toSignatureFlowMode(fileFlow)

	if (!canChooseSigningOrderAtRequestLevel.value && resolvedPolicy && resolvedPolicy !== 'none') {
		return resolvedPolicy
	}

	if (typeof fileFlow === 'number' && fileFlow !== 0 && resolvedFileFlow) {
		return resolvedFileFlow
	}

	if (resolvedFileFlow && resolvedFileFlow !== 'none') {
		return resolvedFileFlow
	}

	if (resolvedPolicy && resolvedPolicy !== 'none') {
		return resolvedPolicy
	}

	if (fileFlow === 0) {
		return 'none'
	}

	return 'parallel'
})

const canSaveSignatureFlowPreference = computed(() => signatureFlowPolicy.value?.canSaveAsUserDefault ?? false)
const canSaveFooterPreference = computed(() => footerPolicy.value?.canSaveAsUserDefault ?? false)
const isOrderedNumeric = computed(() => signatureFlow.value === 'ordered_numeric')
const hasSigners = computed(() => filesStore.hasSigners(filesStore.getFile()))
const totalSigners = computed(() => Number(filesStore.getFile()?.signersCount || filesStore.getFile()?.signers?.length || 0))
const isOriginalFileDeleted = computed(() => filesStore.isOriginalFileDeleted())
const currentFile = computed<EditableRequestFile | null>(() => (filesStore.getFile() as EditableRequestFile | null) ?? null)
const isCurrentFileDetailed = computed(() => currentFile.value?.detailsLoaded === true)
const shouldLoadDetail = computed(() => totalSigners.value > 0)
const showSigningOrderOptions = computed(() => !isOriginalFileDeleted.value && isCurrentFileDetailed.value && hasSigners.value && filesStore.canSave() && canChooseSigningOrderAtRequestLevel.value)
const showPreserveOrder = computed(() => !isOriginalFileDeleted.value && isCurrentFileDetailed.value && totalSigners.value > 1 && filesStore.canSave() && canChooseSigningOrderAtRequestLevel.value)
const showRememberSignatureFlow = computed(() => showPreserveOrder.value && canSaveSignatureFlowPreference.value)
const footerTemplateSourceOptions = computed<FooterTemplateSourceOption[]>(() => {
	const options: FooterTemplateSourceOption[] = []
	const policy = footerPolicy.value
	if (!policy) {
		return options
	}

	const inheritedValue = (policy as unknown as { inheritedValue?: unknown }).inheritedValue
	const effectiveConfig = normalizeSignatureFooterPolicyConfig(policy.effectiveValue)
	const inheritedConfig = normalizeSignatureFooterPolicyConfig((inheritedValue ?? policy.effectiveValue) as EffectivePolicyValue)
	const hasEffectiveTemplate = hasCustomFooterTemplate(effectiveConfig)
	const hasInheritedTemplate = hasCustomFooterTemplate(inheritedConfig)

	if (hasEffectiveTemplate) {
		options.push({
			value: 'effective',
			label: t('libresign', 'Use effective template'),
			policyValue: serializeFooterPolicyConfigForRequest(effectiveConfig),
		})
	}

	if (hasInheritedTemplate) {
		const serializedInherited = serializeFooterPolicyConfigForRequest(inheritedConfig)
		if (!options.some(option => option.policyValue === serializedInherited)) {
			options.push({
				value: 'inherited',
				label: t('libresign', 'Use inherited template'),
				policyValue: serializedInherited,
			})
		}
	}

	return options
})
const showFooterTemplateSelector = computed(() => {
	return !isOriginalFileDeleted.value
		&& isCurrentFileDetailed.value
		&& filesStore.canSave()
		&& canChooseFooterTemplateAtRequestLevel.value
		&& footerTemplateSourceOptions.value.length > 1
})
const showRememberFooterTemplate = computed(() => showFooterTemplateSelector.value && canSaveFooterPreference.value)
const showViewOrderButton = computed(() => !isOriginalFileDeleted.value && isCurrentFileDetailed.value && isOrderedNumeric.value && totalSigners.value > 1 && hasSigners.value)
const shouldShowOrderedOptions = computed(() => isOrderedNumeric.value && totalSigners.value > 1)
const showSignatureFlowPreferenceClearedNotice = computed(() => signatureFlowPolicy.value?.preferenceWasCleared ?? false)
const currentUserDisplayName = computed(() => OC.getCurrentUser()?.displayName || '')
const showDocMdpWarning = computed(() => filesStore.isDocMdpNoChangesAllowed() && !filesStore.canAddSigner())
const fileName = computed(() => filesStore.getSelectedFileView()?.name ?? '')
const isEnvelope = computed(() => filesStore.getFile()?.nodeType === 'envelope')
const envelopeFilesCount = computed(() => filesStore.getFile()?.filesCount || 0)
const size = computed(() => window.matchMedia('(max-width: 512px)').matches ? 'full' : 'normal')
const modalTitle = computed(() => Object.keys(signerToEdit.value).length > 0 ? t('libresign', 'Edit signer') : t('libresign', 'Add new signer'))
const showSigningProgress = computed(() => signingProgressStatus.value === FILE_STATUS.SIGNING_IN_PROGRESS)
const signingOrderDiagramSigners = computed<SigningOrderDiagramSigner[]>(() => {
	const signers = filesStore.getFile()?.signers || []
	return signers.map((signer: EditableRequestSigner) => ({
		displayName: signer.displayName,
		signed: isSignerSigned(signer),
		signingOrder: signer.signingOrder,
	}))
})

function normalizeSignatureFlow(flow: unknown): SignatureFlowValue | null {
	if (flow && typeof flow === 'object' && 'flow' in (flow as Record<string, unknown>)) {
		const nestedFlow = (flow as { flow?: unknown }).flow
		return normalizeSignatureFlow(nestedFlow)
	}

	if (flow === 'none' || flow === 'parallel' || flow === 'ordered_numeric' || flow === 0 || flow === 1 || flow === 2) {
		return flow
	}
	return null
}

function toSignatureFlowMode(flow: unknown): ResolvedSignatureFlowMode | null {
	const normalizedFlow = normalizeSignatureFlow(flow)
	if (normalizedFlow === 0) {
		return 'none'
	}

	if (normalizedFlow === 1) {
		return 'parallel'
	}

	if (normalizedFlow === 2) {
		return 'ordered_numeric'
	}

	if (normalizedFlow === 'none' || normalizedFlow === 'parallel' || normalizedFlow === 'ordered_numeric') {
		return normalizedFlow
	}

	return null
}

function getResolvedSignatureFlowForSave(): SignatureFlowValue {
	const flow = signatureFlow.value
	if (flow === 'ordered_numeric') {
		return 'ordered_numeric'
	}

	if (flow === 'parallel') {
		return 'parallel'
	}

	return 'parallel'
}

function getSignatureFlowPayloadForSave(): SignatureFlowValue | null {
	if (!canChooseSigningOrderAtRequestLevel.value) {
		return null
	}

	return getResolvedSignatureFlowForSave()
}

function hasCustomFooterTemplate(config: SignatureFooterPolicyConfig): boolean {
	return config.enabled && config.customizeFooterTemplate && config.footerTemplate.trim() !== ''
}

function serializeFooterPolicyConfigForRequest(config: SignatureFooterPolicyConfig): string {
	const serialized = serializeSignatureFooterPolicyConfig(config)
	return typeof serialized === 'string' ? serialized : ''
}

function getFooterPolicyPayloadForSave(): string | null {
	if (!canChooseFooterTemplateAtRequestLevel.value) {
		return null
	}

	const selectedOption = footerTemplateSourceOptions.value.find(option => option.value === selectedFooterTemplateSource.value)
	return selectedOption?.policyValue ?? null
}

async function saveFooterTemplatePreference(footerPolicyValue: string): Promise<void> {
	try {
		await policiesStore.saveUserPreference('add_footer', footerPolicyValue)
		syncRememberFooterTemplateWithPolicy()
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to save footer template preference'))
		rememberFooterTemplate.value = false
	}
}

async function onRememberFooterTemplateChange(value: boolean): Promise<void> {
	const previousValue = rememberFooterTemplate.value
	rememberFooterTemplate.value = value
	if (!canSaveFooterPreference.value) {
		return
	}

	try {
		if (value) {
			const footerPolicyValue = getFooterPolicyPayloadForSave()
			if (footerPolicyValue) {
				await saveFooterTemplatePreference(footerPolicyValue)
			}
			return
		}

		await policiesStore.clearUserPreference('add_footer')
		syncRememberFooterTemplateWithPolicy()
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to clear footer template preference'))
		rememberFooterTemplate.value = previousValue
	}
}

function onFooterTemplateSourceChange(): void {
	if (rememberFooterTemplate.value && canSaveFooterPreference.value) {
		const footerPolicyValue = getFooterPolicyPayloadForSave()
		if (footerPolicyValue) {
			void saveFooterTemplatePreference(footerPolicyValue)
		}
	}

	debouncedSave()
}

function getSignerMethod(signer: { identifyMethods?: Array<Pick<IdentifyMethodRecord, 'method'>> }): string | undefined {
	return signer.identifyMethods?.[0]?.method
}

function toIdentifySignerToEdit(signer: EditableRequestSigner): IdentifySignerToEdit {
	const identifyMethods = signer.identifyMethods?.map((method: IdentifyMethodRecord) => ({
		method: method.method,
		value: method.value ?? '',
	}))

	return {
		localKey: signer.localKey,
		displayName: signer.displayName,
		description: signer.description ?? undefined,
		...(identifyMethods?.length ? { identifyMethods } : {}),
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

function isSignerSigned(signer: Partial<EditableRequestSigner>) {
	if (Array.isArray(signer?.signed)) {
		return signer.signed.length > 0
	}
	return !!signer?.signed
}

const canEditSigningOrder = computed(() => (signer: Partial<EditableRequestSigner>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	const minSigners = isAdminFlowForced.value ? 1 : 2
	return isOrderedNumeric.value && totalSigners.value >= minSigners && filesStore.canSave() && !isSignerSigned(signer)
})

const canDelete = computed(() => (signer: Partial<EditableRequestSigner>) => {
	if (isOriginalFileDeleted.value) {
		return false
	}
	return filesStore.canSave() && !isSignerSigned(signer)
})

function canSignerActInOrder(signer: Partial<EditableRequestSigner>) {
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
	const hasPendingLowerOrder = signers.some((currentSigner: EditableRequestSigner) => {
		const otherOrder = currentSigner.signingOrder || 1
		return otherOrder < signerOrder && !isSignerSigned(currentSigner)
	})

	return !hasPendingLowerOrder
}

const canCustomizeMessage = computed(() => (signer: Partial<EditableRequestSigner>) => {
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

const canRequestSignature = computed(() => (signer: Partial<EditableRequestSigner>) => {
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

const canSendReminder = computed(() => (signer: Partial<EditableRequestSigner>) => {
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

	return file.signers.some((signer: EditableRequestSigner) => {
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

function hasAnyDraftSigner(file: EditableRequestFile | null | undefined) {
	const fileSigners = file?.signers
	const signers: EditableRequestSigner[] = Array.isArray(fileSigners) ? fileSigners : []
	return signers.some((signer: EditableRequestSigner) => signer.status === SIGN_REQUEST_STATUS.DRAFT)
}

function getCurrentSigningOrder(signersNotSigned: EditableRequestSigner[]) {
	return Math.min(...signersNotSigned.map(s => s.signingOrder || 1))
}

function hasOrderDraftSigners(file: EditableRequestFile | null | undefined, order: number) {
	const fileSigners = file?.signers
	const signers: EditableRequestSigner[] = Array.isArray(fileSigners) ? fileSigners : []
	return signers.some((signer: EditableRequestSigner) => {
		const signerOrder = signer.signingOrder || 1
		return signerOrder === order && signer.status === SIGN_REQUEST_STATUS.DRAFT
	})
}

function hasSequentialDraftSigners(file: EditableRequestFile | null | undefined) {
	const fileSigners = file?.signers
	const signers: EditableRequestSigner[] = Array.isArray(fileSigners) ? fileSigners : []
	const signersNotSigned = signers.filter((signer: EditableRequestSigner) => !isSignerSigned(signer))
	if (signersNotSigned.length === 0) {
		return false
	}

	const currentOrder = getCurrentSigningOrder(signersNotSigned)
	return hasOrderDraftSigners(file, currentOrder)
}

const hasDraftSigners = computed(() => {
	const file = filesStore.getEditableFile()
	if (!isCurrentFileDetailed.value || !file?.signers) {
		return false
	}

	return isOrderedNumeric.value ? hasSequentialDraftSigners(file) : hasAnyDraftSigner(file)
})

const showSaveButton = computed(() => {
	if (shouldLoadDetail.value && !isCurrentFileDetailed.value) {
		return false
	}
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
	if (shouldLoadDetail.value && !isCurrentFileDetailed.value) {
		return false
	}
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
		const signatureFlow = getSignatureFlowPayloadForSave()
		const footerPolicy = getFooterPolicyPayloadForSave()
		await filesStore.saveOrUpdateSignatureRequest({
			signers,
			signatureFlow,
			footerPolicy,
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
	const nextFlow = value ? 'ordered_numeric' : 'parallel'

	if (value) {
		if (file?.signers) {
			const orders = file.signers.map((signer: EditableRequestSigner) => signer.signingOrder || 0)
			const hasDuplicateOrders = orders.length !== new Set(orders).size
			file.signers.forEach((signer: EditableRequestSigner, index: number) => {
				if (!signer.signingOrder || hasDuplicateOrders) {
					signer.signingOrder = index + 1
				}
			})
		}
		if (file) {
			file.signatureFlow = nextFlow
		}
	} else if (!isAdminFlowForced.value) {
		if (file?.signers) {
			file.signers.forEach((signer: EditableRequestSigner) => {
				if (!isSignerSigned(signer)) {
					signer.signingOrder = 1
				}
			})
		}
		if (file) {
			file.signatureFlow = nextFlow
		}
	}

	if (rememberSignatureFlow.value && canSaveSignatureFlowPreference.value) {
		void saveSignatureFlowPreference(nextFlow)
	}

	debouncedSave()
}

async function saveSignatureFlowPreference(flow: 'parallel' | 'ordered_numeric'): Promise<void> {
	try {
		await policiesStore.saveUserPreference('signature_flow', flow)
		syncRememberSignatureFlowWithPolicy()
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to save signing order preference'))
		rememberSignatureFlow.value = false
	}
}

async function onRememberSignatureFlowChange(value: boolean): Promise<void> {
	const previousValue = rememberSignatureFlow.value
	rememberSignatureFlow.value = value
	if (!canSaveSignatureFlowPreference.value) {
		return
	}

	try {
		if (value) {
			await saveSignatureFlowPreference(isOrderedNumeric.value ? 'ordered_numeric' : 'parallel')
			return
		}

		await policiesStore.clearUserPreference('signature_flow')
		syncRememberSignatureFlowWithPolicy()
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to clear signing order preference'))
		rememberSignatureFlow.value = previousValue
	}
}

function syncPreserveOrderWithFile() {
	preserveOrder.value = signatureFlow.value === 'ordered_numeric' && canChooseSigningOrderAtRequestLevel.value
}

function syncFileSignatureFlowWithPolicy() {
	const resolvedPolicy = toSignatureFlowMode(signatureFlowPolicy.value?.effectiveValue)
	if (canChooseSigningOrderAtRequestLevel.value || !resolvedPolicy || resolvedPolicy === 'none') {
		return
	}

	const file = currentFile.value
	if (!file) {
		return
	}

	file.signatureFlow = resolvedPolicy

	if (resolvedPolicy !== 'ordered_numeric' || !Array.isArray(file.signers)) {
		return
	}

	const orders = file.signers.map((signer: EditableRequestSigner) => signer.signingOrder || 0)
	const hasDuplicateOrders = orders.length !== new Set(orders).size
	file.signers.forEach((signer: EditableRequestSigner, index: number) => {
		if (!signer.signingOrder || hasDuplicateOrders) {
			signer.signingOrder = index + 1
		}
	})

	if (file.signers.every((signer: EditableRequestSigner) => typeof signer.signingOrder === 'number')) {
		normalizeSigningOrders(file.signers as Array<{ signingOrder: number }>)
	}
}

function syncRememberSignatureFlowWithPolicy() {
	rememberSignatureFlow.value = signatureFlowPolicy.value?.sourceScope === 'user'
}

function syncSelectedFooterTemplateSourceWithPolicy() {
	if (footerTemplateSourceOptions.value.length === 0) {
		selectedFooterTemplateSource.value = 'effective'
		return
	}

	if (!footerTemplateSourceOptions.value.some(option => option.value === selectedFooterTemplateSource.value)) {
		selectedFooterTemplateSource.value = footerTemplateSourceOptions.value[0]?.value ?? 'effective'
	}
}

function syncRememberFooterTemplateWithPolicy() {
	rememberFooterTemplate.value = footerPolicy.value?.sourceScope === 'user'
}

async function ensureCurrentFileDetail(force = false) {
	const file = currentFile.value
	if (typeof file?.id !== 'number' || (!force && (!shouldLoadDetail.value || isCurrentFileDetailed.value))) {
		return
	}

	isLoadingFileDetail.value = true
	try {
		await filesStore.fetchFileDetail({ fileId: file.id, force })
		syncFileSignatureFlowWithPolicy()
		syncPreserveOrderWithFile()
		syncSelectedFooterTemplateSourceWithPolicy()
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to load signer details'))
	} finally {
		isLoadingFileDetail.value = false
	}
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
	return capabilities.libresign?.config['sign-elements']['is-available'] === true
}

function closeModal() {
	modalSrc.value = ''
	filesStore.flushSelectedFile()
}

function getValidationFileUuid() {
	const file = filesStore.getFile()
	return getValidationRouteUuid(filesStore.getFile())
}

function getSignRouteUuid() {
	return getSigningRouteUuid(filesStore.getFile())
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

function editSigner(signer: EditableRequestSigner) {
	signerToEdit.value = toIdentifySignerToEdit(signer)
	const signerMethod = getSignerMethod(signer)
	if (signerMethod) {
		activeTab.value = `tab-${signerMethod}`
	}
	filesStore.enableIdentifySigner()
}

function customizeMessage(signer: EditableRequestSigner) {
	signerToEdit.value = toIdentifySignerToEdit(signer)
	filesStore.enableIdentifySigner()
}

function onTabChange(tabId: string) {
	if (activeTab.value !== tabId) {
		activeTab.value = tabId
		debouncedTabChange(tabId)
	}
}

function updateSigningOrder(signer: EditableRequestSigner, value: string) {
	const order = parseInt(value, 10)
	const file = filesStore.getEditableFile()
	if (isNaN(order)) {
		return
	}

	const signerLocalKey = signer.localKey
	const currentIndex = file.signers?.findIndex((currentSigner: EditableRequestSigner) => currentSigner.localKey === signerLocalKey) ?? -1
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
	file.signers = [...file.signers].sort((left: EditableRequestSigner, right: EditableRequestSigner) => {
		const orderLeft = left.signingOrder || 999
		const orderRight = right.signingOrder || 999
		if (orderLeft === orderRight) {
			return 0
		}
		return orderLeft - orderRight
	})
}

function confirmSigningOrder(signer: EditableRequestSigner) {
	const file = filesStore.getEditableFile()
	const signerLocalKey = signer.localKey
	const currentIndex = file.signers?.findIndex((currentSigner: EditableRequestSigner) => currentSigner.localKey === signerLocalKey) ?? -1
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

	const sortedSigners = [...file.signers].sort((left: EditableRequestSigner, right: EditableRequestSigner) => {
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

async function sendNotify(signer: EditableRequestSigner) {
	if (!signer.signRequestId) {
		showError(t('libresign', 'Signer request not found'))
		return
	}
	const file = filesStore.getEditableFile()
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

async function requestSignatureForSigner(signer: EditableRequestSigner) {
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
		const file = filesStore.getEditableFile()
		const signers = (file.signers || []).map((signer: EditableRequestSigner) => {
			if (signer.signRequestId === selectedSignRequestId) {
				return {
					...signer,
					status: SIGN_REQUEST_STATUS.ABLE_TO_SIGN,
					statusText: getSignRequestStatusText(SIGN_REQUEST_STATUS.ABLE_TO_SIGN),
				}
			}
			return signer
		})
		await filesStore.saveOrUpdateSignatureRequest({
			signers: signers as never,
			status: 1,
			signatureFlow: getSignatureFlowPayloadForSave(),
			footerPolicy: getFooterPolicyPayloadForSave(),
		})
		showSuccess(t('libresign', 'Signature requested'))
		showConfirmRequestSigner.value = false
		selectedSigner.value = null
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to request signature'))
	}
	hasLoading.value = false
}

async function sign() {
	await ensureCurrentFileDetail()
	const file = filesStore.getFile()
	if (file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		validationFile()
		return
	}

	const uuid = getSignRouteUuid()
	if (!uuid) {
		showError(t('libresign', 'Signer request not found'))
		return
	}
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
	await ensureCurrentFileDetail()
	hasLoading.value = true
	try {
		await filesStore.saveOrUpdateSignatureRequest({
			signatureFlow: getSignatureFlowPayloadForSave(),
			footerPolicy: getFooterPolicyPayloadForSave(),
		})
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
	await ensureCurrentFileDetail()
	hasLoading.value = true
	try {
		const response = await filesStore.saveOrUpdateSignatureRequest({
			status: 1,
			signatureFlow: getSignatureFlowPayloadForSave(),
			footerPolicy: getFooterPolicyPayloadForSave(),
		})
		showSuccess(t('libresign', response.message || 'Signature requested'))
		showConfirmRequest.value = false
	} catch (error: unknown) {
		showRequestError(error, t('libresign', 'Failed to request signatures'))
	}
	hasLoading.value = false
}

async function openManageFiles() {
	hasLoading.value = true
	const response = await filesStore.saveOrUpdateSignatureRequest({
		signatureFlow: getSignatureFlowPayloadForSave(),
		footerPolicy: getFooterPolicyPayloadForSave(),
	})
	hasLoading.value = false
	if (response && 'success' in response && response.success === false && response.message) {
		showError(response.message)
		return
	}
	showEnvelopeFilesDialog.value = true
}

function getCurrentFileUrl(file: { file?: string | { url?: string } | null, uuid?: string | null } | null | undefined): string | null {
	if (typeof file?.file === 'string') {
		return file.file
	}

	if (file?.file && typeof file.file === 'object' && typeof file.file.url === 'string') {
		return file.file.url
	}

	if (file?.uuid) {
		return generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: file.uuid })
	}

	return null
}

function openFile() {
	const file = filesStore.getFile()
	const fileUrl = getCurrentFileUrl(file)
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
	if (typeof file?.id !== 'number') {
		return
	}

	signingProgressStatus.value = file.status === undefined || file.status === null
		? null
		: Number(file.status)
	signingProgressStatusText.value = file.statusText || ''
	signingProgress.value = null

	stopPollingFunction.value = startLongPolling(
		file.id,
		Number(file.status ?? 0),
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
		syncFileSignatureFlowWithPolicy()
		syncPreserveOrderWithFile()
		syncRememberSignatureFlowWithPolicy()
		syncSelectedFooterTemplateSourceWithPolicy()
		syncRememberFooterTemplateWithPolicy()
		void ensureCurrentFileDetail()
	}
}, { immediate: true })

watch(signatureFlowPolicy, () => {
	syncFileSignatureFlowWithPolicy()
	syncPreserveOrderWithFile()
	syncRememberSignatureFlowWithPolicy()
})

watch(footerPolicy, () => {
	syncSelectedFooterTemplateSourceWithPolicy()
	syncRememberFooterTemplateWithPolicy()
})

const handleEditSigner = ((event: NextcloudEvent) => {
	editSigner((event as CustomEvent<EditableRequestSigner>).detail)
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
	void policiesStore.fetchEffectivePolicies()
	syncFileSignatureFlowWithPolicy()
	syncPreserveOrderWithFile()
	syncRememberSignatureFlowWithPolicy()
	syncSelectedFooterTemplateSourceWithPolicy()
	syncRememberFooterTemplateWithPolicy()
	void ensureCurrentFileDetail()
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
	rememberSignatureFlow,
	rememberFooterTemplate,
	selectedFooterTemplateSource,
	showOrderDiagram,
	showEnvelopeFilesDialog,
	signatureFlowPolicy,
	footerPolicy,
	debouncedSave,
	debouncedTabChange,
	signingProgress,
	signingProgressStatus,
	signingProgressStatusText,
	stopPollingFunction,
	signatureFlow,
	isAdminFlowForced,
	getSignatureFlowPayloadForSave,
	isOrderedNumeric,
	showSigningOrderOptions,
	showPreserveOrder,
	showRememberSignatureFlow,
	showFooterTemplateSelector,
	showRememberFooterTemplate,
	footerTemplateSourceOptions,
	showSignatureFlowPreferenceClearedNotice,
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
	onRememberSignatureFlowChange,
	onRememberFooterTemplateChange,
	onFooterTemplateSourceChange,
	getFooterPolicyPayloadForSave,
	syncFileSignatureFlowWithPolicy,
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
	getSignRouteUuid,
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

.request-signature-footer-template-selector {
	display: flex;
	flex-direction: column;
	gap: 6px;
	margin: 8px 0;

	&__label {
		font-weight: 600;
	}

	&__input {
		width: 100%;
		padding: 8px;
		border: 1px solid var(--color-border-maxcontrast);
		border-radius: 6px;
		background: var(--color-main-background);
		color: var(--color-main-text);
	}
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
