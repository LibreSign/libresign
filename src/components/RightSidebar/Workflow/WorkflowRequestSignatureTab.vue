<template>
	<div class="workflow-request-signature-tab">
		<!-- ========================================= -->
		<!-- HEADER -->
		<!-- ========================================= -->
		<WorkflowHeaderCard
			:file="state.file.value"
			:state="state.snapshot.value"
			:can-validate="state.canValidate.value"
			@open-file="openFile"
			@manage-files="openManageFiles"
			@validate-file="validationFile" />

		<!-- ========================================= -->
		<!-- STEPPER -->
		<!-- ========================================= -->
		<WorkflowStepper
			:steps="state.steps.value" />

		<!-- ========================================= -->
		<!-- LOADING DETAIL -->
		<!-- ========================================= -->
		<NcNoteCard
			v-if="state.shouldLoadDetail.value && isLoadingFileDetail"
			type="info">
			{{ t('libresign', 'Loading signer details...') }}
		</NcNoteCard>

		<!-- ========================================= -->
		<!-- WARNINGS -->
		<!-- ========================================= -->
		<div
			v-if="hasWarnings"
			class="workflow-warning-stack">
			<NcNoteCard
				v-if="state.showDocMdpWarning.value"
				type="warning">
				{{ t('libresign', 'This document has been certified with no changes allowed. You cannot add more signers to this document.') }}
			</NcNoteCard>

			<NcNoteCard
				v-if="state.isOriginalFileDeleted.value"
				type="warning">
				{{ t('libresign', 'The original file was deleted. You can no longer add signers or open it.') }}
			</NcNoteCard>

			<NcNoteCard
				v-if="state.hasSignersWithDisabledMethods.value"
				type="warning">
				{{ t('libresign', 'Some signers use identification methods that have been disabled. Please remove or update them before requesting signatures.') }}
			</NcNoteCard>
		</div>

		<!-- ========================================= -->
		<!-- SIGNERS -->
		<!-- ========================================= -->
		<WorkflowSigners
		    :can-manage-signers="canManageSigners"
		    :signers="state.signers.value"
			:event="state.isOriginalFileDeleted.value ? '' : 'libresign:edit-signer'"
			@add-signer="addSigner"
			@edit-signer="editSigner"
			@delete-signer="filesStore.deleteSigner"
			@request-signature="requestSignatureForSigner"
			@send-reminder="sendNotify"
			@toggle-sign-order="onPreserveOrderChange"
			@signing-order-changed="debouncedSave"
			@customize-message="customizeMessage" />


		<WorkflowSignatureSetup
		    v-if="canManageSigners"
			:file="state.file.value"
			:is-loading="hasLoading"
			@edit-positions="save" />

		<!-- ========================================= -->
		<!-- SIGNING PROGRESS -->
		<!-- ========================================= -->
		<SigningProgress
			v-if="state.showSigningProgress.value"
			:status="signingProgressStatus ?? FILE_STATUS.SIGNING_IN_PROGRESS"
			:status-text="signingProgressStatusText"
			:progress="signingProgress ?? undefined"
			:is-loading="hasLoading" />

		<!-- ========================================= -->
		<!-- PRIMARY ACTION -->
		<!-- ========================================= -->
		<WorkflowActions
			:workflow="state.snapshot.value"
			:loading="hasLoading"
			@add-signer="addSigner"
			@setup-positions="save"
			@request-signatures="request"
			@sign-document="sign"
			@view-progress="validationFile"
			@secondary-action="showOrderDiagram = true" />

		<!-- ========================================= -->
		<!-- VISIBLE ELEMENTS (signature field editor) -->
		<!-- ========================================= -->
		<VisibleElements />

		<!-- ========================================= -->
		<!-- MODAL (iframe: sign / validate) -->
		<!-- ========================================= -->
		<NcModal
			v-if="modalSrc"
			size="full"
			:name="fileName"
			:close-button-contained="false"
			:close-button-outside="true"
			@close="closeModal">
			<iframe
				:src="modalSrc"
				class="workflow-iframe" />
		</NcModal>

		<!-- ========================================= -->
		<!-- DIALOG: identify / add signer -->
		<!-- ========================================= -->
		<NcDialog
			v-if="filesStore.identifyingSigner"
			id="request-signature-identify-signer"
			:size="size"
			:name="modalTitle"
			@closing="filesStore.disableIdentifySigner()">
			<NcAppSidebar
				:name="modalTitle"
				:active="activeTab"
				@update:active="onTabChange">
				<NcAppSidebarTab
					v-for="method in enabledMethods"
					:id="`tab-${method.name}`"
					:key="method.name"
					:name="method.friendly_name">
					<template #icon>
						<NcIconSvgWrapper
							:size="20"
							:svg="getSvgIcon(method.name)" />
					</template>

					<IdentifySigner
						:signer-to-edit="signerToEdit"
						:placeholder="method.friendly_name"
						:method="method.name"
						:methods="methods"
						:disabled="isSignerMethodDisabled"
						@phone-not-found="switchToEmail" />
				</NcAppSidebarTab>
			</NcAppSidebar>
		</NcDialog>

		<!-- ========================================= -->
		<!-- DIALOG: confirm bulk request -->
		<!-- ========================================= -->
		<NcDialog
			v-if="showConfirmRequest"
			:name="t('libresign', 'Confirm')"
			:message="t('libresign', 'Send signature request?')"
			@closing="showConfirmRequest = false">
			<template #actions>
				<NcButton @click="showConfirmRequest = false">
					{{ t('libresign', 'Cancel') }}
				</NcButton>

				<NcButton
					variant="primary"
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

		<!-- ========================================= -->
		<!-- DIALOG: confirm per-signer request -->
		<!-- ========================================= -->
		<NcDialog
			v-if="showConfirmRequestSigner"
			:name="t('libresign', 'Confirm')"
			:message="t('libresign', 'Send signature request?')"
			@closing="showConfirmRequestSigner = false; selectedSigner = null">
			<template #actions>
				<NcButton @click="showConfirmRequestSigner = false; selectedSigner = null">
					{{ t('libresign', 'Cancel') }}
				</NcButton>

				<NcButton
					variant="primary"
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

		<!-- ========================================= -->
		<!-- DIALOG: signing order diagram -->
		<!-- ========================================= -->
		<NcDialog
			v-if="showOrderDiagram"
			:name="t('libresign', 'Signing order diagram')"
			size="large"
			@closing="showOrderDiagram = false">
			<SigningOrderDiagram
				:signers="signingOrderDiagramSigners"
				:sender-name="currentUserDisplayName" />

			<template #actions>
				<NcButton @click="showOrderDiagram = false">
					{{ t('libresign', 'Close') }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- ========================================= -->
		<!-- ENVELOPE FILES LIST -->
		<!-- ========================================= -->
		<EnvelopeFilesList
			:open="showEnvelopeFilesDialog"
			@close="showEnvelopeFilesDialog = false" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'
import { mdiSend } from '@mdi/js'

// ── SVG icons for identify-method tabs ──────────────────────────────────────
import svgAccount  from '@mdi/svg/svg/account.svg?raw'
import svgEmail    from '@mdi/svg/svg/email.svg?raw'
import svgSms      from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp     from '@mdi/svg/svg/xmpp.svg?raw'
import svgSignal   from '../../../../img/logo-signal-app.svg?raw'
import svgTelegram from '../../../../img/logo-telegram-app.svg?raw'

// ── Nextcloud Vue components ─────────────────────────────────────────────────
import NcAppSidebar    from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton        from '@nextcloud/vue/components/NcButton'
import NcDialog        from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon   from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal         from '@nextcloud/vue/components/NcModal'
import NcNoteCard      from '@nextcloud/vue/components/NcNoteCard'

// ── Sub-components ───────────────────────────────────────────────────────────
import EnvelopeFilesList  from '../EnvelopeFilesList.vue'
import IdentifySigner     from '../../Request/IdentifySigner.vue'
import SigningOrderDiagram from '../../SigningOrder/SigningOrderDiagram.vue'
import SigningProgress     from '../../RequestSigningProgress.vue'
import VisibleElements    from '../../Request/VisibleElements.vue'
import WorkflowSignatureSetup from './WorkflowSignatureSetup.vue'

// ── New workflow UI components ───────────────────────────────────────────────
import WorkflowActions    from './WorkflowActions.vue'
import WorkflowHeaderCard from './WorkflowHeader.vue'
import WorkflowSigners    from './WorkflowSigners.vue'
import WorkflowStepper    from './WorkflowStepper.vue'

// ── Composables / stores ─────────────────────────────────────────────────────
import { useWorkflowController } from '../../../composables/useWorkflowController'
import { useFilesStore }         from '../../../store/files'
import { FILE_STATUS }           from '../../../constants'

// ────────────────────────────────────────────────────────────────────────────

defineOptions({ name: 'WorkflowRequestSignatureTab' })

const props = withDefaults(defineProps<{
	useModal?: boolean
}>(), {
	useModal: false,
})

/* ── Controller (owns all side-effects, exposes state proxy) ─────────────────
 *
 * We destructure every ref / computed we need in the template so the
 * template stays readable and doesn't reach into nested objects.
 * ─────────────────────────────────────────────────────────────────────────── */
const {
	// state proxy
	state,

	// local UI refs
	hasLoading,
	isLoadingFileDetail,
	signerToEdit,
	modalSrc,
	methods,
	showConfirmRequest,
	showConfirmRequestSigner,
	selectedSigner,
	activeTab,
	preserveOrder,
	showOrderDiagram,
	showEnvelopeFilesDialog,
	signingProgress,
	signingProgressStatus,
	signingProgressStatusText,

	// controller-only computeds
	currentUserDisplayName,
	size,
	modalTitle,
	fileName,
	enabledMethods,
	isSignerMethodDisabled,
	signingOrderDiagramSigners,
	canManageSigners,

	// actions
	addSigner,
	editSigner,
	customizeMessage,
	onTabChange,
	onPreserveOrderChange,
	updateSigningOrder,
	confirmSigningOrder,
	save,
	request,
	confirmRequest,
	sign,
	validationFile,
	openFile,
	openManageFiles,
	sendNotify,
	requestSignatureForSigner,
	confirmRequestSigner,
	closeModal,

	// helpers
	getSvgIcon,
	debouncedSave,
} = useWorkflowController({ useModal: props.useModal })

// Register the SVG icon map so the controller's getSvgIcon() lookup works
// (icons are bundled in the SFC layer, not in the composable)
const _svgMap: Record<string, string> = {
	account:  svgAccount,
	email:    svgEmail,
	signal:   svgSignal,
	sms:      svgSms,
	telegram: svgTelegram,
	whatsapp: svgWhatsapp,
	xmpp:     svgXmpp,
}
// Patch getSvgIcon to use our map (the controller exposes registerSvgIcons
// for this purpose, but a local override is equally clean)
function getSvgIconLocal(name: string): string {
	return _svgMap[name] ?? svgAccount
}

/* ── Store (needed for deleteSigner and identifyingSigner) ───────────────────
 *
 * The controller wraps everything else; these two usages are the only direct
 * store accesses required in the template.
 * ─────────────────────────────────────────────────────────────────────────── */
const filesStore = useFilesStore()

/* ── Derived template helpers ─────────────────────────────────────────────── */

const hasWarnings = computed(() =>
	state.showDocMdpWarning.value
	|| state.isOriginalFileDeleted.value
	|| state.hasSignersWithDisabledMethods.value,
)

/* ── switchToEmail — phone-not-found fallback ─────────────────────────────── */
function switchToEmail(phone: string): void {
	const emailMethod = methods.value.find(m => m.name === 'email')
	if (!emailMethod) return
	onTabChange(`tab-${emailMethod.name}`)
}
</script>

<style lang="scss" scoped>
.workflow-request-signature-tab {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 0;
}

.workflow-warning-stack {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.workflow-iframe {
	width: 100%;
	height: 100%;
	border: none;
}

/* ── Identify-signer dialog overrides (matches legacy) ───────────────────── */
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
