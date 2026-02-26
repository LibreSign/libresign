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
			@update:checked="onPreserveOrderChange">
			{{ t('libresign', 'Sign in order') }}
		</NcCheckboxRadioSwitch>
		<NcButton v-if="showViewOrderButton && !isOriginalFileDeleted"
			type="tertiary"
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
						@update:value="updateSigningOrder(signer, $event)"
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
				type="secondary"
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
			:status="signingProgressStatus"
			:status-text="signingProgressStatusText"
			:progress="signingProgress"
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
<script>

import { t } from '@nextcloud/l10n'

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

const iconMap = {
	svgAccount,
	svgEmail,
	svgSignal,
	svgSms,
	svgTelegram,
	svgWhatsapp,
	svgXmpp,
}

import signingOrderMixin from '../../mixins/signingOrderMixin.js'

export default {
	name: 'RequestSignatureTab',
	mixins: [signingOrderMixin],
	components: {
		IdentifySigner,
		NcActionButton,
		NcActionInput,
		NcActions,
		NcAppSidebar,
		NcAppSidebarTab,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcIconSvgWrapper,
		NcFormBox,
		NcLoadingIcon,
		NcModal,
		NcNoteCard,
		Signers,
		SigningOrderDiagram,
		SigningProgress,
		VisibleElements,
	},
	props: {
		useModal: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		const userConfigStore = useUserConfigStore()

		return {
			filesStore,
			signStore,
			sidebarStore,
			userConfigStore,
			mdiAccountPlus,
			mdiBell,
			mdiChartGantt,
			mdiDelete,
			mdiPencil,
			mdiFileDocument,
			mdiFileMultiple,
			mdiFilePlus,
			mdiInformation,
			mdiMessageText,
			mdiOrderNumericAscending,
			mdiSend,
		}
	},
	data() {
		return {
			hasLoading: false,
			signerToEdit: {},
			modalSrc: '',
			document: {},
			methods: [],
			showConfirmRequest: false,
			showConfirmRequestSigner: false,
			selectedSigner: null,
			activeTab: '',
			preserveOrder: false,
			showOrderDiagram: false,
			showEnvelopeFilesDialog: false,
			adminSignatureFlow: '',
			debouncedSave: null,
			debouncedTabChange: null,
			signingProgress: null,
			signingProgressStatus: null,
			signingProgressStatusText: '',
			stopPollingFunction: null,
			mdiAccountPlus,
			mdiBell,
			mdiChartGantt,
			mdiDelete,
			mdiPencil,
			mdiFileDocument,
			mdiFileMultiple,
			mdiFilePlus,
			mdiInformation,
			mdiMessageText,
			mdiOrderNumericAscending,
			mdiSend,
		}
	},
	computed: {
		signatureFlow() {
			const file = this.filesStore.getFile()
			let flow = file?.signatureFlow

			if (typeof flow === 'number') {
				const flowMap = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
				return flowMap[flow]
			}

			if (flow && flow !== 'none') {
				return flow
			}
			if (this.adminSignatureFlow && this.adminSignatureFlow !== 'none') {
				return this.adminSignatureFlow
			}
			return 'parallel'
		},
		isAdminFlowForced() {
			return this.adminSignatureFlow && this.adminSignatureFlow !== 'none'
		},
		isOrderedNumeric() {
			return this.signatureFlow === 'ordered_numeric'
		},
		showSigningOrderOptions() {
			return !this.isOriginalFileDeleted
				&& this.hasSigners
				&& this.filesStore.canSave()
				&& !this.isAdminFlowForced
		},
		showPreserveOrder() {
			return !this.isOriginalFileDeleted
				&& this.totalSigners > 1
				&& this.filesStore.canSave()
				&& !this.isAdminFlowForced
		},
		showViewOrderButton() {
			return !this.isOriginalFileDeleted
				&& this.isOrderedNumeric
				&& this.totalSigners > 1
				&& this.hasSigners
		},
		shouldShowOrderedOptions() {
			return this.isOrderedNumeric && this.totalSigners > 1
		},
		currentUserDisplayName() {
			return OC.getCurrentUser()?.displayName || ''
		},
		showDocMdpWarning() {
			return this.filesStore.isDocMdpNoChangesAllowed() && !this.filesStore.canAddSigner()
		},
		isOriginalFileDeleted() {
			return this.filesStore.isOriginalFileDeleted()
		},
		canEditSigningOrder() {
			return (signer) => {
				if (this.isOriginalFileDeleted) {
					return false
				}
				const minSigners = this.isAdminFlowForced ? 1 : 2

				return this.isOrderedNumeric
					&& this.totalSigners >= minSigners
					&& this.filesStore.canSave()
					&& !this.isSignerSigned(signer)
			}
		},
		canDelete() {
			return (signer) => {
				if (this.isOriginalFileDeleted) {
					return false
				}
				return this.filesStore.canSave() && !this.isSignerSigned(signer)
			}
		},
		canCustomizeMessage() {
			return (signer) => {
				if (this.isOriginalFileDeleted) {
					return false
				}
				if (this.isSignerSigned(signer) || !signer.signRequestId || signer.me) {
					return false
				}

				const method = signer.identifyMethods?.[0]?.method
				if (method === 'account' && !signer.acceptsEmailNotifications) {
					return false
				}

				if (!this.canSignerActInOrder(signer)) {
					return false
				}

				return !!method
			}
		},
		canRequestSignature() {
			return (signer) => {
				if (this.isOriginalFileDeleted) {
					return false
				}
				const file = this.filesStore.getFile()
				if (!this.filesStore.canRequestSign
					|| file?.status === FILE_STATUS.DRAFT
					|| this.isSignerSigned(signer)
					|| !signer.signRequestId
					|| signer.me
					|| signer.status !== 0) {
					return false
				}

				return this.canSignerActInOrder(signer)
			}
		},
		canSendReminder() {
			return (signer) => {
				if (this.isOriginalFileDeleted) {
					return false
				}
				const file = this.filesStore.getFile()
				if (!this.filesStore.canRequestSign
					|| file?.status === FILE_STATUS.DRAFT
					|| this.isSignerSigned(signer)
					|| !signer.signRequestId
					|| signer.me
					|| signer.status !== 1) {
					return false
				}

				return this.canSignerActInOrder(signer)
			}
		},
		hasSignersWithDisabledMethods() {
			const file = this.filesStore.getFile()
			if (!file?.signers) {
				return false
			}

			return file.signers.some(signer => {
				if (this.isSignerSigned(signer)) {
					return false
				}
				const method = signer.identifyMethods?.[0]?.method
				if (!method) {
					return false
				}
				const methodConfig = this.methods.find(m => m.name === method)
				return !methodConfig?.enabled
			})
		},
		showSaveButton() {
			if (this.isOriginalFileDeleted) {
				return false
			}
			if (!this.filesStore.canSave()) {
				return false
			}

			if (!this.isSignElementsAvailable()) {
				return false
			}

			const file = this.filesStore.getFile()

			if (file.status === FILE_STATUS.PARTIAL_SIGNED || file.status === FILE_STATUS.SIGNED) {
				return false
			}

			if (this.hasSignersWithDisabledMethods) {
				return false
			}

			return true
		},
		showRequestButton() {
			if (this.isOriginalFileDeleted) {
				return false
			}
			if (!this.filesStore.canSave()) {
				return false
			}
			if (this.hasSignersWithDisabledMethods) {
				return false
			}
			return this.hasDraftSigners
		},
		hasDraftSigners() {
			const file = this.filesStore.getFile()
			if (!file?.signers) {
				return false
			}

			return this.isOrderedNumeric
				? this.hasSequentialDraftSigners(file)
				: this.hasAnyDraftSigner(file)
		},
		hasSigners() {
			return this.filesStore.hasSigners(this.filesStore.getFile())
		},
		totalSigners() {
			return this.filesStore.getFile()?.signers?.length || 0
		},
		fileName() {
			return this.filesStore.getFile()?.name ?? ''
		},
		isEnvelope() {
			return this.filesStore.getFile()?.nodeType === 'envelope'
		},
		envelopeFilesCount() {
			return this.filesStore.getFile()?.filesCount || 0
		},
		size() {
			return window.matchMedia('(max-width: 512px)').matches ? 'full' : 'normal'
		},
		modalTitle() {
			if (Object.keys(this.signerToEdit).length > 0) {
				return this.t('libresign', 'Edit signer')
			}
			return this.t('libresign', 'Add new signer')
		},
		enabledMethods() {
			if (Object.keys(this.signerToEdit).length > 0 && this.signerToEdit.identifyMethods?.length) {
				const signerMethod = this.signerToEdit.identifyMethods[0].method
				const signerMethodConfig = this.methods.find(m => m.name === signerMethod)

				if (signerMethodConfig) {
					return [signerMethodConfig]
				}
			}

			return this.methods.filter(method => method.enabled)
		},
		isSignerMethodDisabled() {
			if (Object.keys(this.signerToEdit).length > 0 && this.signerToEdit.identifyMethods?.length) {
				const signerMethod = this.signerToEdit.identifyMethods[0].method
				const methodConfig = this.methods.find(m => m.name === signerMethod)
				return !methodConfig?.enabled
			}
			return false
		},
		disabledMethodName() {
			if (this.isSignerMethodDisabled && this.signerToEdit.identifyMethods?.length) {
				const signerMethod = this.signerToEdit.identifyMethods[0].method
				const methodConfig = this.methods.find(m => m.name === signerMethod)
				return methodConfig?.friendly_name || signerMethod
			}
			return ''
		},
		showSigningProgress() {
			return this.signingProgressStatus === FILE_STATUS.SIGNING_IN_PROGRESS
		},
	},
	watch: {
		signers(signers) {
			this.init(signers)
		},
		'filesStore.selectedFileId': {
			handler(newFileId) {
				if (newFileId) {
					this.syncPreserveOrderWithFile()
				}
			},
			immediate: true,
		},
		'filesStore.currentFile.status'(newStatus) {
			if (newStatus === FILE_STATUS.SIGNING_IN_PROGRESS) {
				this.startSigningProgressPolling()
			} else if (this.stopPollingFunction) {
				this.stopSigningProgressPolling()
			}
		},
	},
	async mounted() {
		subscribe('libresign:edit-signer', this.editSigner)
		this.filesStore.disableIdentifySigner()

		this.activeTab = this.userConfigStore.files_list_signer_identify_tab || ''

		this.adminSignatureFlow = loadState('libresign', 'signature_flow', 'none')

		this.syncPreserveOrderWithFile()
	},
	beforeUnmount() {
		unsubscribe('libresign:edit-signer')
		// Clean up long polling if active
		if (this.stopPollingFunction) {
			this.stopSigningProgressPolling()
		}
	},
	created() {
		this.methods = loadState('libresign', 'identify_methods', [])
		this.document = loadState('libresign', 'file_info', {})

		this.debouncedSave = debounce(async () => {
			try {
				const file = this.filesStore.getFile()
				const signers = this.isOrderedNumeric ? file?.signers : null
				await this.filesStore.saveOrUpdateSignatureRequest({
					signers,
					signatureFlow: file?.signatureFlow,
				})
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
		}, 1000)

		this.debouncedTabChange = debounce((tabId) => {
			this.userConfigStore.update('files_list_signer_identify_tab', tabId)
		}, 500)
	},
	methods: {
		t,
		isSignerSigned(signer) {
			if (Array.isArray(signer?.signed)) {
				return signer.signed.length > 0
			}
			return !!signer?.signed
		},
		onPreserveOrderChange(value) {
			this.preserveOrder = value
			const file = this.filesStore.getFile()

			if (value) {
				if (file?.signers) {
					file.signers.forEach((signer, index) => {
						if (!signer.signingOrder) {
							signer.signingOrder = index + 1
						}
					})
				}
				if (file) {
					file.signatureFlow = 'ordered_numeric'
				}
			} else {
				if (!this.isAdminFlowForced) {
					if (file?.signers) {
						file.signers.forEach(signer => {
							if (!this.isSignerSigned(signer)) {
								signer.signingOrder = 1
							}
						})
					}
					if (file) {
						file.signatureFlow = 'parallel'
					}
				}
			}

			this.debouncedSave()
		},

		syncPreserveOrderWithFile() {
			const file = this.filesStore.getFile()
			if (!file) {
				this.preserveOrder = false
				return
			}

			const flow = file.signatureFlow

			if ((flow === 'ordered_numeric' || flow === 2) && !this.isAdminFlowForced) {
				this.preserveOrder = true
			} else {
				this.preserveOrder = false
			}
		},
		getSvgIcon(name) {
			return iconMap[`svg${name.charAt(0).toUpperCase() + name.slice(1)}`] || iconMap.svgAccount
		},
		canSignerActInOrder(signer) {
			const method = signer.identifyMethods?.[0]?.method
			if (method) {
				const methodConfig = this.methods.find(m => m.name === method)
				if (!methodConfig?.enabled) {
					return false
				}
			}

			if (!this.isOrderedNumeric) {
				return true
			}

			const file = this.filesStore.getFile()
			const signerOrder = signer.signingOrder || 1
			const signers = Array.isArray(file?.signers) ? file.signers : []

			const hasPendingLowerOrder = signers.some(s => {
				const otherOrder = s.signingOrder || 1
				return otherOrder < signerOrder && !this.isSignerSigned(s)
			})

			return !hasPendingLowerOrder
		},
		hasAnyDraftSigner(file) {
			const signers = Array.isArray(file?.signers) ? file.signers : []
			return signers.some(signer => signer.status === SIGN_REQUEST_STATUS.DRAFT)
		},
		hasSequentialDraftSigners(file) {
			const signers = Array.isArray(file?.signers) ? file.signers : []
			const signersNotSigned = signers.filter(s => !this.isSignerSigned(s))
			if (signersNotSigned.length === 0) {
				return false
			}

			const currentOrder = this.getCurrentSigningOrder(signersNotSigned)
			return this.hasOrderDraftSigners(file, currentOrder)
		},
		getCurrentSigningOrder(signersNotSigned) {
			return Math.min(...signersNotSigned.map(s => s.signingOrder || 1))
		},
		hasOrderDraftSigners(file, order) {
			const signers = Array.isArray(file?.signers) ? file.signers : []
			return signers.some(signer => {
				const signerOrder = signer.signingOrder || 1
				return signerOrder === order && signer.status === SIGN_REQUEST_STATUS.DRAFT
			})
		},
		isSignElementsAvailable() {
			return getCapabilities()?.libresign?.config?.['sign-elements']?.['is-available'] === true
		},
		closeModal() {
			this.modalSrc = ''
			this.filesStore.flushSelectedFile()
		},
		getValidationFileUuid() {
			const file = this.filesStore.getFile()

			if (file?.uuid) {
				return file.uuid
			}

			const signer = file?.signers?.find(row => row.me) || file?.signers?.[0] || {}
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
		},
		validationFile() {
			const targetUuid = this.getValidationFileUuid()

			if (!targetUuid) {
				showError(this.t('libresign', 'Document not found'))
				return
			}

			if (this.useModal) {
				const route = router.resolve({ name: 'ValidationFileExternal', params: { uuid: targetUuid } })
				this.modalSrc = route.href
				return
			}
			this.$router.push({ name: 'ValidationFile', params: { uuid: targetUuid } })
			this.sidebarStore.hideSidebar()
		},
		addSigner() {
			this.signerToEdit = {}
			this.activeTab = this.userConfigStore.files_list_signer_identify_tab || ''
			this.filesStore.enableIdentifySigner()
		},
		editSigner(signer) {
			this.signerToEdit = signer
			if (signer.identifyMethods?.length) {
				const signerMethod = signer.identifyMethods[0].method
				this.activeTab = `tab-${signerMethod}`
			}
			this.filesStore.enableIdentifySigner()
		},
		customizeMessage(signer) {
			this.signerToEdit = signer
			this.filesStore.enableIdentifySigner()
		},
		onTabChange(tabId) {
			if (this.activeTab !== tabId) {
				this.activeTab = tabId
				this.debouncedTabChange(tabId)
			}
		},
		updateSigningOrder(signer, value) {
			const order = parseInt(value, 10)
			const file = this.filesStore.getFile()

			if (isNaN(order)) {
				return
			}

			const currentIndex = file.signers.findIndex(s => s.identify === signer.identify)
			if (currentIndex === -1) {
				return
			}

			file.signers[currentIndex].signingOrder = order

			const sortedSigners = [...file.signers].sort((a, b) => {
				const orderA = a.signingOrder || 999
				const orderB = b.signingOrder || 999
				if (orderA === orderB) {
					return 0
				}
				return orderA - orderB
			})

			file.signers = sortedSigners
		},
		confirmSigningOrder(signer) {
			const file = this.filesStore.getFile()

			const currentIndex = file.signers.findIndex(s => s.identify === signer.identify)
			if (currentIndex === -1) {
				return
			}

			const order = file.signers[currentIndex].signingOrder
			const oldOrder = signer.signingOrder

			for (let i = 0; i < file.signers.length; i++) {
				if (i === currentIndex) continue

				const currentItemOrder = file.signers[i].signingOrder

				if (order < oldOrder) {
					if (currentItemOrder >= order && currentItemOrder < oldOrder) {
						file.signers[i].signingOrder = currentItemOrder + 1
					}
				} else if (order > oldOrder) {
					if (currentItemOrder > oldOrder && currentItemOrder <= order) {
						file.signers[i].signingOrder = currentItemOrder - 1
					}
				}
			}

			const sortedSigners = [...file.signers].sort((a, b) => {
				const orderA = a.signingOrder || 999
				const orderB = b.signingOrder || 999
				return orderA - orderB
			})

			this.normalizeSigningOrders(sortedSigners)

			file.signers = sortedSigners

			this.debouncedSave()
		},
		async sendNotify(signer) {
			const body = {
				fileId: this.filesStore.getFile().id,
				signRequestId: signer.signRequestId,
			}

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/notify/signer'), body)
				.then(({ data }) => {
					showSuccess(t('libresign', data.ocs.data.message))
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})

		},
		async requestSignatureForSigner(signer) {
			this.selectedSigner = signer
			this.showConfirmRequestSigner = true
		},
		async confirmRequestSigner() {
			if (!this.selectedSigner) return

			this.hasLoading = true
			try {
				const file = this.filesStore.getFile()
				const signers = file.signers.map(s => {
					if (s.signRequestId === this.selectedSigner.signRequestId) {
						return { ...s, status: 1 }
					}
					return s
				})
				await this.filesStore.saveOrUpdateSignatureRequest({
					signers,
					status: 1,
				})
				showSuccess(t('libresign', 'Signature requested'))
				this.showConfirmRequestSigner = false
				this.selectedSigner = null
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
			this.hasLoading = false
		},
		async sign() {
			const file = this.filesStore.getFile()
			if (file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
				this.validationFile()
				return
			}

			const uuid = file.signUuid
			if (this.useModal) {
				const route = router.resolve({ name: 'SignPDFExternal', params: { uuid } })
				this.modalSrc = route.href
				return
			}
			this.signStore.setFileToSign(this.filesStore.getFile())
			this.$router.push({ name: 'SignPDF', params: { uuid } })
		},

		async save() {
			this.hasLoading = true
			try {
				await this.filesStore.saveOrUpdateSignatureRequest({})
				emit('libresign:show-visible-elements')
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
			this.hasLoading = false
		},
		async request() {
			this.showConfirmRequest = true
		},
		async confirmRequest() {
			this.hasLoading = true
			try {
				const response = await this.filesStore.saveOrUpdateSignatureRequest({ status: 1 })
				showSuccess(t('libresign', response.message))
				this.showConfirmRequest = false
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
			this.hasLoading = false
		},
		async openManageFiles() {
			const file = this.filesStore.getFile()

			this.hasLoading = true
			let response = await this.filesStore.saveOrUpdateSignatureRequest({})
			this.hasLoading = false
			if (response?.success === false && response?.message) {
				showError(response.message)
				return
			}

			this.showEnvelopeFilesDialog = true
		},
		openFile() {
			const file = this.filesStore.getFile()
			const fileUrl = this.document?.files?.[0]?.file
				|| (file?.uuid ? generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: file.uuid }) : null)

			if (!fileUrl) {
				showError(this.t('libresign', 'Document URL not found'))
				return
			}

			openDocument({
				fileUrl,
				filename: file.name,
				nodeId: file.nodeId,
			})
		},
		startSigningProgressPolling() {
			const file = this.filesStore.getFile()
			if (!file?.id) {
				return
			}

			this.signingProgressStatus = file.status
			this.signingProgressStatusText = file.statusText || ''
			this.signingProgress = null

			this.stopPollingFunction = startLongPolling(
				file.id,
				file.status,
				(data) => {
					this.signingProgressStatus = data.status
					this.signingProgressStatusText = data.statusText
					this.signingProgress = data.progress

					const currentFile = this.filesStore.getFile()
					if (currentFile) {
						currentFile.status = data.status
						currentFile.statusText = data.statusText
					}
				},
				() => !this.filesStore.getFile() || this.filesStore.getFile().id !== file.id,
				(error) => {
					console.error('Error during signing progress polling:', error)
					showError(this.t('libresign', 'Error monitoring signing progress'))
				}
			)
		},
		stopSigningProgressPolling() {
			if (this.stopPollingFunction) {
				this.stopPollingFunction()
				this.stopPollingFunction = null
			}
			this.signingProgress = null
			this.signingProgressStatus = null
			this.signingProgressStatusText = ''
		},
	},
}
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
