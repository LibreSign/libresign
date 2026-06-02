/**
 * useWorkflowState.ts
 *
 * Pure reactive state layer for the document signing workflow.
 * This composable derives ALL computed state from the stores — no side effects,
 * no API calls, no event subscriptions. Everything it returns is a ComputedRef
 * so that templates and the controller stay fully reactive.
 *
 * Consumed by:
 *   - useWorkflowController  (orchestration / actions)
 *   - WorkflowStepper.vue
 *   - WorkflowHeaderCard.vue
 *   - WorkflowSigners.vue
 *   - WorkflowActions.vue
 */

import {
	computed,
	ref,
	type ComputedRef,
} from 'vue'

import { loadState } from '@nextcloud/initial-state'

import {
	FILE_STATUS,
	SIGN_REQUEST_STATUS,
} from '../constants'

import { useFilesStore } from '../store/files'
import type { EditableSignerDraft } from '../store/files'

import type {
	IdentifyMethodSetting as IdentifyMethodConfig,
	IdentifyMethodRecord,
	SignatureFlowMode,
} from '../types'

/* ─────────────────────────────────────────────────────────────────────────────
 * EXPORTED TYPES
 * ───────────────────────────────────────────────────────────────────────────── */

export type WorkflowStepStatus =
	| 'complete'
	| 'current'
	| 'upcoming'
	| 'locked'

export type WorkflowPrimaryAction =
	| 'add-signer'
	| 'setup-positions'
	| 'request-signatures'
	| 'sign-document'
	| 'view-progress'
	| 'completed'

type WorkflowStatusVariant =
	| 'neutral'
	| 'success'
	| 'info'
	| 'warning'
	| 'error'

export type WorkflowStep = {
	id: string
	label: string
	status: WorkflowStepStatus
	active: boolean
	completed: boolean
}

export type WorkflowDescriptor = {
	currentStage:
	| 'prepare'
	| 'place-fields'
	| 'request'
	| 'signing'
	| 'completed'
	isDraft: boolean
	isBlocked: boolean
	isCompleted: boolean
}

// ── Flat shape consumed by template components ──────────────────────────────

export type WorkflowState = {
	// ── Meta ────────────────────────────────────────────────────────────────
	workflow: WorkflowDescriptor
	steps: WorkflowStep[]
	primaryAction: WorkflowPrimaryAction
	statusVariant: WorkflowStatusVariant
	statusLabel: string
	statusSubtitle: string
	isReady: boolean

	// ── File ────────────────────────────────────────────────────────────────
	file: any | null
	signers: EditableSignerDraft[]
	isEnvelope: boolean
	envelopeFilesCount: number
	isOriginalFileDeleted: boolean
	isDocMdpProtected: boolean
	showDocMdpWarning: boolean

	// ── Signers ─────────────────────────────────────────────────────────────
	hasSigners: boolean
	totalSigners: number
	signersCount: number
	hasDraftSigners: boolean
	hasSignersWithDisabledMethods: boolean

	// ── Signatures ──────────────────────────────────────────────────────────
	hasVisibleElements: boolean
	hasSignaturePositions: boolean

	// ── Ordering ────────────────────────────────────────────────────────────
	isOrderedNumeric: boolean
	isOrderedSigning: boolean           // alias — consumed by WorkflowActions
	isAdminFlowForced: boolean
	showSigningOrderOptions: boolean
	showPreserveOrder: boolean
	showViewOrderButton: boolean
	shouldShowOrderedOptions: boolean

	// ── Document actions ────────────────────────────────────────────────────
	showSaveButton: boolean
	showRequestButton: boolean
	showSigningProgress: boolean
	shouldHighlightSetupSignPositionsButton: boolean

	// ── Detail loading ──────────────────────────────────────────────────────
	isCurrentFileDetailed: boolean
	shouldLoadDetail: boolean

	// ── Permissions ─────────────────────────────────────────────────────────
	canAddSigner: boolean
	canSave: boolean
	canSign: boolean
	canValidate: boolean
}

// ── ComputedRef-wrapped shape returned from the composable ──────────────────

export type WorkflowStateRefs = {
	workflow: ComputedRef<WorkflowDescriptor>
	steps: ComputedRef<WorkflowStep[]>
	primaryAction: ComputedRef<WorkflowPrimaryAction>
	statusVariant: ComputedRef<WorkflowStatusVariant>
	statusLabel: ComputedRef<string>
	statusSubtitle: ComputedRef<string>
	isReady: ComputedRef<boolean>

	file: ComputedRef<any | null>
	signers: ComputedRef<EditableSignerDraft[]>
	isEnvelope: ComputedRef<boolean>
	envelopeFilesCount: ComputedRef<number>
	isOriginalFileDeleted: ComputedRef<boolean>
	isDocMdpProtected: ComputedRef<boolean>
	showDocMdpWarning: ComputedRef<boolean>

	hasSigners: ComputedRef<boolean>
	totalSigners: ComputedRef<number>
	signersCount: ComputedRef<number>
	hasDraftSigners: ComputedRef<boolean>
	hasSignersWithDisabledMethods: ComputedRef<boolean>

	hasVisibleElements: ComputedRef<boolean>
	hasSignaturePositions: ComputedRef<boolean>

	isOrderedNumeric: ComputedRef<boolean>
	isOrderedSigning: ComputedRef<boolean>
	isAdminFlowForced: ComputedRef<boolean>
	showSigningOrderOptions: ComputedRef<boolean>
	showPreserveOrder: ComputedRef<boolean>
	showViewOrderButton: ComputedRef<boolean>
	shouldShowOrderedOptions: ComputedRef<boolean>

	showSaveButton: ComputedRef<boolean>
	showRequestButton: ComputedRef<boolean>
	showSigningProgress: ComputedRef<boolean>
	shouldHighlightSetupSignPositionsButton: ComputedRef<boolean>

	isCurrentFileDetailed: ComputedRef<boolean>
	shouldLoadDetail: ComputedRef<boolean>

	canAddSigner: ComputedRef<boolean>
	canSave: ComputedRef<boolean>
	canSign: ComputedRef<boolean>
	canValidate: ComputedRef<boolean>

	/**
	 * Convenience helper: snapshot of every ComputedRef as a plain object.
	 * Useful when passing the whole state to a child component as a single prop.
	 */
	snapshot: ComputedRef<WorkflowState>
}

/* ─────────────────────────────────────────────────────────────────────────────
 * INTERNAL HELPERS
 * ───────────────────────────────────────────────────────────────────────────── */

export function isSignerSigned(signer: Partial<EditableSignerDraft>): boolean {
	if (Array.isArray(signer?.signed)) {
		return signer.signed.length > 0
	}
	return Boolean(signer?.signed)
}

function getSignerMethod(
	signer: { identifyMethods?: Array<Pick<IdentifyMethodRecord, 'method'>> },
): string | undefined {
	return signer.identifyMethods?.[0]?.method
}

/* ─────────────────────────────────────────────────────────────────────────────
 * COMPOSABLE
 * ───────────────────────────────────────────────────────────────────────────── */

export function useWorkflowState(): WorkflowStateRefs {
	const filesStore = useFilesStore()

	// ── Identify methods loaded once from server state ──────────────────────
	const methods = ref<IdentifyMethodConfig[]>(
		loadState<IdentifyMethodConfig[]>('libresign', 'identify_methods', []),
	)

	// ── Admin-level signature flow (read once; not reactive) ─────────────────
	const adminSignatureFlow = loadState<SignatureFlowMode>(
		'libresign',
		'signature_flow',
		'none',
	)

	function getMethodConfig(methodName: string | undefined): IdentifyMethodConfig | undefined {
		if (!methodName) return undefined
		return methods.value.find(m => m.name === methodName)
	}

	/* ── FILE ──────────────────────────────────────────────────────────────── */

	// const file = computed(() => filesStore.getFile() ?? null)
	const file = computed(() => {
		const id = filesStore.selectedFileId

		if (!id) {
			return null
		}

		return filesStore.files[id] || null
	})

	const signers = computed<EditableSignerDraft[]>(() => file.value?.signers ?? [])

	/* ── PERMISSIONS ───────────────────────────────────────────────────────── */

	const canAddSigner = computed(() => filesStore.canAddSigner())
	const canSave = computed(() => filesStore.canSave())
	const canSign = computed(() => filesStore.canSign())
	const canValidate = computed(() => filesStore.canValidate())

	/* ── SIGNERS ────────────────────────────────────────────────────────────── */

	const hasSigners = computed(() => signers.value.length > 0)

	const totalSigners = computed(() =>
		Number(file.value?.signersCount ?? signers.value.length ?? 0),
	)

	// signersCount is a template-facing alias of totalSigners
	const signersCount = totalSigners

	/**
	 * True when any signer has DRAFT status.
	 * Mirrors the logic from the original RequestSignatureTab exactly.
	 */
	const hasDraftSigners = computed(() =>
		signers.value.some(s => s.status === SIGN_REQUEST_STATUS.DRAFT),
	)

	const hasSignersWithDisabledMethods = computed(() => {
		if (!file.value?.signers) return false
		return file.value.signers.some((signer: EditableSignerDraft) => {
			if (isSignerSigned(signer)) return false
			const method = getSignerMethod(signer)
			if (!method) return false
			return !getMethodConfig(method)?.enabled
		})
	})

	/* ── FILE STATE ─────────────────────────────────────────────────────────── */

	const isOriginalFileDeleted = computed(() => filesStore.isOriginalFileDeleted())
	const isDocMdpProtected = computed(() => filesStore.isDocMdpNoChangesAllowed())
	const showDocMdpWarning = computed(() => isDocMdpProtected.value && !canAddSigner.value)

	/* ── SIGNATURE POSITIONS ────────────────────────────────────────────────── */

	const hasSignaturePositions = computed(() =>
		(file.value?.visibleElements?.length ?? 0) > 0,
	)

	// Alias used by WorkflowActions
	const hasVisibleElements = hasSignaturePositions

	const isReady = computed(() => hasSigners.value && hasSignaturePositions.value)

	const shouldHighlightSetupSignPositionsButton = computed(() =>
		hasSigners.value && !hasSignaturePositions.value,
	)

	/* ── SIGNATURE FLOW ─────────────────────────────────────────────────────── */

	const signatureFlow = computed(() => {
		const flow = file.value?.signatureFlow

		if (flow === 'ordered_numeric' || flow === 2) return 'ordered_numeric'
		if (flow === 'parallel' || flow === 1) return 'parallel'

		// Fall back to admin-configured flow
		if (adminSignatureFlow && adminSignatureFlow !== 'none') {
			return adminSignatureFlow
		}

		return 'parallel'
	})

	const isOrderedNumeric = computed(() => signatureFlow.value === 'ordered_numeric')
	const isOrderedSigning = isOrderedNumeric   // template alias (WorkflowActions)
	const isAdminFlowForced = computed(() => Boolean(adminSignatureFlow && adminSignatureFlow !== 'none'))

	/* ── DETAIL LOADING ─────────────────────────────────────────────────────── */

	const isCurrentFileDetailed = computed(() => file.value?.detailsLoaded === true)
	const shouldLoadDetail = computed(() => totalSigners.value > 0)

	/* ── ORDER UI ───────────────────────────────────────────────────────────── */

	const showSigningOrderOptions = computed(() =>
		!isOriginalFileDeleted.value
		&& isCurrentFileDetailed.value
		&& hasSigners.value
		&& canSave.value
		&& !isAdminFlowForced.value,
	)

	const showPreserveOrder = computed(() =>
		showSigningOrderOptions.value && totalSigners.value > 1,
	)

	const showViewOrderButton = computed(() =>
		!isOriginalFileDeleted.value
		&& isCurrentFileDetailed.value
		&& isOrderedNumeric.value
		&& totalSigners.value > 1
		&& hasSigners.value,
	)

	const shouldShowOrderedOptions = computed(() =>
		isOrderedNumeric.value && totalSigners.value > 1,
	)

	/* ── ENVELOPE ───────────────────────────────────────────────────────────── */

	const isEnvelope = computed(() => file.value?.nodeType === 'envelope')
	const envelopeFilesCount = computed(() => file.value?.filesCount ?? 0)

	/* ── SIGNING PROGRESS ───────────────────────────────────────────────────── */

	const showSigningProgress = computed(() =>
		file.value?.status === FILE_STATUS.SIGNING_IN_PROGRESS,
	)

	/* ── BUTTONS ────────────────────────────────────────────────────────────── */

	/**
	 * showSaveButton mirrors the full guard logic from RequestSignatureTab.
	 * The controller owns isSignElementsAvailable(); the state layer only guards
	 * the conditions it can derive from the store.
	 */
	const showSaveButton = computed(() => {
		if (shouldLoadDetail.value && !isCurrentFileDetailed.value)
			return false
		if (isOriginalFileDeleted.value || !canSave.value)
			return false

		const status = file.value?.status
		if (status === FILE_STATUS.PARTIAL_SIGNED || status === FILE_STATUS.SIGNED)
			return false
		if (hasSignersWithDisabledMethods.value)
			return false

		return true
	})

	const showRequestButton = computed(() => {
		if (shouldLoadDetail.value && !isCurrentFileDetailed.value)
			return false
		if (isOriginalFileDeleted.value || !canSave.value)
			return false
		if (hasSignersWithDisabledMethods.value)
			return false
		return hasDraftSigners.value
	})

	/* ── STATUS PILL ────────────────────────────────────────────────────────── */

	const statusVariant = computed<WorkflowStatusVariant>(() => {
		if (isOriginalFileDeleted.value) {
			return 'error'
		}

		switch (primaryAction.value) {
			case 'add-signer':
			case 'setup-positions':
				return 'neutral'

			case 'request-signatures':
				return 'success'

			case 'view-progress':
			case 'sign-document':
				return 'info'

			case 'completed':
				return 'success'

			default:
				return 'neutral'
		}
	})

	const statusLabel = computed(() => {
		switch (primaryAction.value) {
			case 'add-signer':
				return 'Draft'

			case 'setup-positions':
				return 'Preparing document'

			case 'request-signatures':
				return 'Ready to send'

			case 'view-progress':
				return 'Awaiting signatures'

			case 'sign-document':
				return 'Ready to sign'

			case 'completed':
				return 'Completed'

			default:
				return 'Draft'
		}
	})

	const statusSubtitle = computed(() => {
		const signerList = signers.value || []

		const signedCount = signerList.filter(signer =>
			isSignerSigned(signer),
		).length

		const total = signerList.length
		const remaining = total - signedCount

		switch (primaryAction.value) {
			case 'add-signer':
				return 'Add signers to begin the signature request workflow'

			case 'setup-positions':
				return 'Place signature fields to continue'

			case 'request-signatures':
				return `${total} signer${total === 1 ? '' : 's'} ready`

			case 'view-progress':
				if (signedCount > 0) {
					return `${signedCount} of ${total} signers completed`
				}

				return `${remaining} signer${remaining === 1 ? '' : 's'} pending`

			case 'sign-document':
				return 'Your signature is required'

			case 'completed':
				return 'All signatures completed'

			default:
				return ''
		}
    })

	/* ── PRIMARY ACTION ─────────────────────────────────────────────────────── */

	const primaryAction = computed<WorkflowPrimaryAction>(() => {
		if (file.value?.status === FILE_STATUS.SIGNED)
			return 'completed'

		if (canSign.value)
			return 'sign-document'

		if (showSigningProgress.value)
			return 'view-progress'

		if (!hasSigners.value)
			return 'add-signer'


		if (!hasSignaturePositions.value)
			return 'setup-positions'

		/**
		 * Legacy parity:
		 * only allow requesting while draft signers exist
		 */
		if (hasDraftSigners.value)
			return 'request-signatures'

		/**
		 * Requests already sent
		 */
		return 'view-progress'
	})

	/* ── WORKFLOW DESCRIPTOR ────────────────────────────────────────────────── */

	const workflow = computed<WorkflowDescriptor>(() => {
		if (file.value?.status === FILE_STATUS.SIGNED) {
			return { currentStage: 'completed', isCompleted: true, isDraft: false, isBlocked: false }
		}
		if (showSigningProgress.value) {
			return { currentStage: 'signing', isCompleted: false, isDraft: false, isBlocked: false }
		}
		if (!hasSigners.value) {
			return { currentStage: 'prepare', isCompleted: false, isDraft: true, isBlocked: true }
		}
		if (!hasSignaturePositions.value) {
			return { currentStage: 'place-fields', isCompleted: false, isDraft: true, isBlocked: true }
		}
		return { currentStage: 'request', isCompleted: false, isDraft: false, isBlocked: false }
	})

	/* ── STEPS (WorkflowStepper) ────────────────────────────────────────────── */

	const steps = computed<WorkflowStep[]>(() => {
		const hasFields = hasSignaturePositions.value
		const isRequested = showSigningProgress.value
		const isCompleted = file.value?.status === FILE_STATUS.SIGNED

		return [
			{
				id: 'signers',
				label: 'Add signers',
				status: hasSigners.value ? 'complete' : 'current',
				active: !hasSigners.value,
				completed: hasSigners.value,
			},
			{
				id: 'positions',
				label: 'Setup positions',
				status: hasFields ? 'complete' : hasSigners.value ? 'current' : 'locked',
				active: hasSigners.value && !hasFields,
				completed: hasFields,
			},
			{
				id: 'request',
				label: 'Request signatures',
				status: isRequested || isCompleted ? 'complete' : hasFields ? 'current' : 'locked',
				active: hasFields && !isRequested,
				completed: isRequested || isCompleted,
			},
			{
				id: 'complete',
				label: 'Completed',
				status: isCompleted ? 'complete' : isRequested ? 'current' : 'locked',
				active: isRequested && !isCompleted,
				completed: isCompleted,
			},
		]
	})

	/* ── SNAPSHOT (single-prop convenience) ─────────────────────────────────── */

	const snapshot = computed<WorkflowState>(() => ({
		workflow: workflow.value,
		steps: steps.value,
		primaryAction: primaryAction.value,
		statusVariant: statusVariant.value,
		statusLabel: statusLabel.value,
		statusSubtitle: statusSubtitle.value,
		isReady: isReady.value,

		file: file.value,
		signers: signers.value,
		isEnvelope: isEnvelope.value,
		envelopeFilesCount: envelopeFilesCount.value,
		isOriginalFileDeleted: isOriginalFileDeleted.value,
		isDocMdpProtected: isDocMdpProtected.value,
		showDocMdpWarning: showDocMdpWarning.value,

		hasSigners: hasSigners.value,
		totalSigners: totalSigners.value,
		signersCount: signersCount.value,
		hasDraftSigners: hasDraftSigners.value,
		hasSignersWithDisabledMethods: hasSignersWithDisabledMethods.value,

		hasVisibleElements: hasVisibleElements.value,
		hasSignaturePositions: hasSignaturePositions.value,

		isOrderedNumeric: isOrderedNumeric.value,
		isOrderedSigning: isOrderedSigning.value,
		isAdminFlowForced: isAdminFlowForced.value,
		showSigningOrderOptions: showSigningOrderOptions.value,
		showPreserveOrder: showPreserveOrder.value,
		showViewOrderButton: showViewOrderButton.value,
		shouldShowOrderedOptions: shouldShowOrderedOptions.value,

		showSaveButton: showSaveButton.value,
		showRequestButton: showRequestButton.value,
		showSigningProgress: showSigningProgress.value,
		shouldHighlightSetupSignPositionsButton: shouldHighlightSetupSignPositionsButton.value,

		isCurrentFileDetailed: isCurrentFileDetailed.value,
		shouldLoadDetail: shouldLoadDetail.value,

		canAddSigner: canAddSigner.value,
		canSave: canSave.value,
		canSign: canSign.value,
		canValidate: canValidate.value,
	}))

	/* ── RETURN ─────────────────────────────────────────────────────────────── */

	return {
		workflow,
		steps,
		primaryAction,
		statusVariant,
		statusLabel,
		statusSubtitle,
		isReady,

		file,
		signers,
		isEnvelope,
		envelopeFilesCount,
		isOriginalFileDeleted,
		isDocMdpProtected,
		showDocMdpWarning,

		hasSigners,
		totalSigners,
		signersCount,
		hasDraftSigners,
		hasSignersWithDisabledMethods,

		hasVisibleElements,
		hasSignaturePositions,

		isOrderedNumeric,
		isOrderedSigning,
		isAdminFlowForced,
		showSigningOrderOptions,
		showPreserveOrder,
		showViewOrderButton,
		shouldShowOrderedOptions,

		showSaveButton,
		showRequestButton,
		showSigningProgress,
		shouldHighlightSetupSignPositionsButton,

		isCurrentFileDetailed,
		shouldLoadDetail,

		canAddSigner,
		canSave,
		canSign,
		canValidate,

		snapshot,
	}
}
