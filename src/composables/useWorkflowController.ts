/**
 * useWorkflowController.ts
 *
 * Orchestration layer for the document signing workflow.
 * This composable owns ALL side-effects: API calls, routing, event-bus
 * subscriptions, polling, dialogs, and user feedback. It composes
 * useWorkflowState for derived reactive state and never duplicates
 * state derivation logic.
 *
 * Consumed by: RequestSignatureTab.vue (and the new redesigned shell)
 */

import {
	computed,
	onBeforeUnmount,
	onMounted,
	ref,
	watch,
	type ComputedRef,
	type Ref,
} from 'vue'

import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import {
	emit as emitEventBus,
	subscribe,
	unsubscribe,
	type Event as NextcloudEvent,
	type EventHandler,
} from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

import {
	useWorkflowState,
	isSignerSigned,
	type WorkflowStateRefs,
	type WorkflowPrimaryAction,
	type WorkflowStep,
	type WorkflowDescriptor,
	type WorkflowState,
} from './useWorkflowState'

import { useFilesStore }      from '../store/files'
import { useSidebarStore }    from '../store/sidebar'
import { useSignStore }       from '../store/sign'
import { useUserConfigStore } from '../store/userconfig'
import { useSigningOrder }    from './useSigningOrder'
import { startLongPolling }   from '../services/longPolling'
import { openDocument }       from '../utils/viewer'
import router                 from '../router/router'

import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../constants'
import { getSignRequestStatusText }         from '../utils/getSignRequestStatusText'

import type { components, operations } from '../types/openapi/openapi'
import type {
	IdentifyMethodRecord,
	IdentifyMethodSetting as IdentifyMethodConfig,
	LibresignCapabilities,
	SignatureFlowMode,
	SignatureFlowValue,
} from '../types/index'
import type { EditableSignerDraft } from '../store/files'
import { showError, showSuccess } from '../services/toast'

/* ─────────────────────────────────────────────────────────────────────────────
 * LOCAL TYPES
 * ───────────────────────────────────────────────────────────────────────────── */

type LoadedDocumentState = Record<string, unknown>

type IdentifySignerMethod = Pick<IdentifyMethodRecord, 'method' | 'value'>

export type IdentifySignerToEdit = {
	localKey?: string
	displayName?: string
	description?: string
	identifyMethods?: IdentifySignerMethod[]
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

type RequestSignatureErrorData   = operations['request_signature-request']['responses'][422]['content']['application/json']['ocs']['data']
type UpdateSignatureErrorData    = operations['request_signature-update-sign']['responses'][422]['content']['application/json']['ocs']['data']
type DeleteRequestSignatureErrorData =
	| operations['request_signature-delete-one-request-signature-using-file-id']['responses'][401]['content']['application/json']['ocs']['data']
	| operations['request_signature-delete-one-request-signature-using-file-id']['responses'][422]['content']['application/json']['ocs']['data']
type NotifySignerErrorData  = operations['notify-signer']['responses'][401]['content']['application/json']['ocs']['data']
type NotifySignerSuccess    = operations['notify-signer']['responses'][200]['content']['application/json']
type OcsErrorData           = RequestSignatureErrorData | UpdateSignatureErrorData | DeleteRequestSignatureErrorData | NotifySignerErrorData

type FilesStoreContract = ReturnType<typeof useFilesStore>
type EditableRequestFile = ReturnType<FilesStoreContract['getEditableFile']>
type EditableRequestSigner = NonNullable<NonNullable<EditableRequestFile['signers']>[number]>

/* ─────────────────────────────────────────────────────────────────────────────
 * CONTROLLER OPTIONS
 * ───────────────────────────────────────────────────────────────────────────── */

export type WorkflowControllerOptions = {
	/** When true the controller opens PDFs / validation in an <iframe> modal */
	useModal?: boolean
}

/* ─────────────────────────────────────────────────────────────────────────────
 * RETURN SHAPE
 * ───────────────────────────────────────────────────────────────────────────── */

export type WorkflowController = {
	// ── State proxy (all ComputedRefs from useWorkflowState) ────────────────
	state: WorkflowStateRefs

	// ── Local UI refs ────────────────────────────────────────────────────────
	hasLoading:               Ref<boolean>
	isLoadingFileDetail:      Ref<boolean>
	signerToEdit:             Ref<IdentifySignerToEdit>
	modalSrc:                 Ref<string>
	documentData:             Ref<LoadedDocumentState>
	methods:                  Ref<IdentifyMethodConfig[]>
	showConfirmRequest:       Ref<boolean>
	showConfirmRequestSigner: Ref<boolean>
	selectedSigner:           Ref<EditableSignerDraft | null>
	activeTab:                Ref<string>
	preserveOrder:            Ref<boolean>
	showOrderDiagram:         Ref<boolean>
	showEnvelopeFilesDialog:  Ref<boolean>
	signingProgress:          Ref<components['schemas']['ProgressPayload'] | null>
	signingProgressStatus:    Ref<number | null>
	signingProgressStatusText: Ref<string>
	stopPollingFunction:      Ref<(() => void) | null>

	// ── Derived ComputedRefs (controller-only, not in state) ─────────────────
	currentUserDisplayName:                 ComputedRef<string>
	size:                                   ComputedRef<'full' | 'normal'>
	modalTitle:                             ComputedRef<string>
	fileName:                               ComputedRef<string>
	signatureFlow:                          ComputedRef<string>
	isAdminFlowForced:                      ComputedRef<boolean>
	enabledMethods:                         ComputedRef<IdentifyMethodConfig[]>
	isSignerMethodDisabled:                 ComputedRef<boolean>
	disabledMethodName:                     ComputedRef<string>
	signingOrderDiagramSigners:             ComputedRef<SigningOrderDiagramSigner[]>
	canManageSigners:						ComputedRef<boolean>
	currentFileUrl:                         ComputedRef<string | null>

	// ── Per-signer computed factories (return functions, not booleans) ────────
	canEditSigningOrder: ComputedRef<(signer: Partial<EditableSignerDraft>) => boolean>
	canDelete:           ComputedRef<(signer: Partial<EditableSignerDraft>) => boolean>
	canCustomizeMessage: ComputedRef<(signer: Partial<EditableSignerDraft>) => boolean>
	canRequestSignature: ComputedRef<(signer: Partial<EditableSignerDraft>) => boolean>
	canSendReminder:     ComputedRef<(signer: Partial<EditableSignerDraft>) => boolean>

	// ── Actions ──────────────────────────────────────────────────────────────
	addSigner:                () => void
	editSigner:               (signer: EditableSignerDraft) => void
	customizeMessage:         (signer: EditableSignerDraft) => void
	onTabChange:              (tabId: string) => void
	onPreserveOrderChange:    (value: boolean) => void
	syncPreserveOrderWithFile: () => void
	updateSigningOrder:       (signer: EditableSignerDraft, value: string | number) => void
	confirmSigningOrder:      (signer: EditableSignerDraft) => void

	save:                () => Promise<void>
	request:             () => void
	confirmRequest:      () => Promise<void>
	sign:                () => Promise<void>
	validationFile:      () => void
	openFile:            () => void
	openManageFiles:     () => Promise<void>

	sendNotify:                (signer: EditableSignerDraft) => Promise<void>
	requestSignatureForSigner: (signer: EditableSignerDraft) => void
	confirmRequestSigner:      () => Promise<void>

	startSigningProgressPolling: () => void
	stopSigningProgressPolling:  () => void

	ensureCurrentFileDetail:   (force?: boolean) => Promise<void>
	getValidationFileUuid:     () => string | number | null
	isSignElementsAvailable:   () => boolean
	closeModal:                () => void

	// ── Internal helpers (exposed for testing) ────────────────────────────────
	normalizeSignatureFlow:     (flow: unknown) => SignatureFlowValue | null
	getSignerMethod:            (signer: { identifyMethods?: Array<Pick<IdentifyMethodRecord, 'method'>> }) => string | undefined
	getMethodConfig:            (methodName: string | undefined) => IdentifyMethodConfig | undefined
	isSignerSigned:             (signer: Partial<EditableSignerDraft>) => boolean
	canSignerActInOrder:        (signer: Partial<EditableSignerDraft>) => boolean
	getSvgIcon:                 (name: string) => string
	showRequestError:           (error: unknown, fallbackMessage: string) => void
	debouncedSave:              ReturnType<typeof debounce>
	hasAnyDraftSigner:          (file: EditableRequestFile | null | undefined) => boolean
	getCurrentSigningOrder:     (signersNotSigned: EditableRequestSigner[]) => number
	hasOrderDraftSigners:       (file: EditableRequestFile | null | undefined, order: number) => boolean
	hasSequentialDraftSigners:  (file: EditableRequestFile | null | undefined) => boolean
}

/* ─────────────────────────────────────────────────────────────────────────────
 * COMPOSABLE
 * ───────────────────────────────────────────────────────────────────────────── */

export function useWorkflowController(
	options: WorkflowControllerOptions = {},
): WorkflowController {
	const { useModal = false } = options

	/* ── Dependencies ────────────────────────────────────────────────────────── */

	const state          = useWorkflowState()
	const filesStore     = useFilesStore()
	const signStore      = useSignStore()
	const sidebarStore   = useSidebarStore()
	const userConfigStore = useUserConfigStore() as ReturnType<typeof useUserConfigStore> & {
		files_list_signer_identify_tab?: string
	}
	const { normalizeSigningOrders } = useSigningOrder()
	const capabilities = getCapabilities() as LibresignCapabilities

	/* ── Static server state ─────────────────────────────────────────────────── */

	const adminSignatureFlow = loadState<SignatureFlowMode>('libresign', 'signature_flow', 'none')

	const EMPTY_DOCUMENT_STATE: LoadedDocumentState = {}
	const EMPTY_IDENTIFY_METHODS: IdentifyMethodConfig[] = []

	/* ── SVG icons (lazy import — keep bundle clean) ─────────────────────────── */
	// These are imported in the consuming Vue SFC; we expose getSvgIcon() as a
	// lookup so the controller remains icon-agnostic.
	let _svgIconMap: Record<string, string> = {}
	function registerSvgIcons(map: Record<string, string>) {
		_svgIconMap = map
	}
	function getSvgIcon(name: string): string {
		return _svgIconMap[name] ?? ''
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * LOCAL UI REFS
	 * ───────────────────────────────────────────────────────────────────────── */

	const hasLoading               = ref(false)
	const isLoadingFileDetail      = ref(false)
	const signerToEdit             = ref<IdentifySignerToEdit>({})
	const modalSrc                 = ref('')
	const documentData             = ref<LoadedDocumentState>(
		loadState<LoadedDocumentState>('libresign', 'file_info', EMPTY_DOCUMENT_STATE),
	)
	const methods                  = ref<IdentifyMethodConfig[]>(
		loadState<IdentifyMethodConfig[]>('libresign', 'identify_methods', EMPTY_IDENTIFY_METHODS),
	)
	const showConfirmRequest       = ref(false)
	const showConfirmRequestSigner = ref(false)
	const selectedSigner           = ref<EditableSignerDraft | null>(null)
	const activeTab                = ref('')
	const preserveOrder            = ref(false)
	const showOrderDiagram         = ref(false)
	const showEnvelopeFilesDialog  = ref(false)
	const signingProgress          = ref<components['schemas']['ProgressPayload'] | null>(null)
	const signingProgressStatus    = ref<number | null>(null)
	const signingProgressStatusText = ref('')
	const stopPollingFunction      = ref<(() => void) | null>(null)

	/* ─────────────────────────────────────────────────────────────────────────
	 * DERIVED COMPUTED (controller-only)
	 * ───────────────────────────────────────────────────────────────────────── */

	const currentUserDisplayName = computed(() => OC.getCurrentUser()?.displayName ?? '')

	const size = computed<'full' | 'normal'>(() =>
		window.matchMedia('(max-width: 512px)').matches ? 'full' : 'normal',
	)

	const modalTitle = computed(() =>
		Object.keys(signerToEdit.value).length > 0
			? t('libresign', 'Edit signer')
			: t('libresign', 'Add new signer'),
	)

	const fileName = computed(() => state.file.value?.name ?? '')

	/**
	 * signatureFlow — mirrors the original component's full normalisation logic
	 * (numeric → string, admin fallback, default parallel).
	 */
	const signatureFlow = computed(() => {
		let flow = state.file.value?.signatureFlow

		if (typeof flow === 'number') {
			const flowMap: Record<number, string> = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
			return flowMap[flow] ?? 'parallel'
		}
		if (flow && flow !== 'none') return flow
		if (adminSignatureFlow && adminSignatureFlow !== 'none') return adminSignatureFlow
		return 'parallel'
	})

	const isAdminFlowForced = computed(() =>
		Boolean(adminSignatureFlow && adminSignatureFlow !== 'none'),
	)

	const currentFileUrl = computed<string | null>(() => {
		const f = state.file.value
		if (!f) return null
		if (typeof f.file === 'string') return f.file
		if (f.file && typeof f.file === 'object' && typeof (f.file as any).url === 'string') {
			return (f.file as any).url
		}
		if (f.uuid) return generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: f.uuid })
		return null
	})

	const signingOrderDiagramSigners = computed<SigningOrderDiagramSigner[]>(() =>
		state.signers.value.map(signer => ({
			displayName: signer.displayName,
			signed:      isSignerSigned(signer),
			signingOrder: signer.signingOrder,
		})),
	)

	const canManageSigners = computed(() => state.canSave.value)

	/* ─────────────────────────────────────────────────────────────────────────
	 * INTERNAL HELPERS
	 * ───────────────────────────────────────────────────────────────────────── */

	function normalizeSignatureFlow(flow: unknown): SignatureFlowValue | null {
		if (
			flow === 'none'
			|| flow === 'parallel'
			|| flow === 'ordered_numeric'
			|| flow === 0
			|| flow === 1
			|| flow === 2
		) {
			return flow as SignatureFlowValue
		}
		return null
	}

	function getSignerMethod(
		signer: { identifyMethods?: Array<Pick<IdentifyMethodRecord, 'method'>> },
	): string | undefined {
		return signer.identifyMethods?.[0]?.method
	}

	function getMethodConfig(methodName: string | undefined): IdentifyMethodConfig | undefined {
		if (!methodName) return undefined
		return methods.value.find(m => m.name === methodName)
	}

	function getOcsErrorData(error: unknown): OcsErrorData | null {
		if (typeof error !== 'object' || error === null || !('response' in error)) return null
		const response = (error as any).response
		if (typeof response !== 'object' || response === null || !('data' in response)) return null
		const data = response.data
		if (typeof data !== 'object' || data === null || !('ocs' in data)) return null
		const ocs = data.ocs
		if (typeof ocs !== 'object' || ocs === null || !('data' in ocs)) return null
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
		if ('messages' in data && Array.isArray((data as any).messages)) {
			;(data as any).messages.forEach((m: any) => showError(m.message))
			return
		}
		if ('errors' in data && Array.isArray((data as any).errors)) {
			;(data as any).errors.forEach((e: any) => showError(e.message))
			return
		}
		showError(fallbackMessage)
	}

	function isSignElementsAvailable(): boolean {
		return (capabilities as any)?.libresign?.config?.['sign-elements']?.['is-available'] === true
	}

	function canSignerActInOrder(signer: Partial<EditableSignerDraft>): boolean {
		const methodConfig = getMethodConfig(getSignerMethod(signer as any))
		if (methodConfig && !methodConfig.enabled) return false

		if (!state.isOrderedNumeric.value) return true

		const signerOrder = signer.signingOrder ?? 1
		const allSigners: EditableSignerDraft[] = Array.isArray(state.file.value?.signers)
			? state.file.value.signers
			: []

		return !allSigners.some(s => (s.signingOrder ?? 1) < signerOrder && !isSignerSigned(s))
	}

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

	/* ─────────────────────────────────────────────────────────────────────────
	 * PER-SIGNER PERMISSION FACTORIES
	 * ───────────────────────────────────────────────────────────────────────── */

	const canEditSigningOrder = computed(() => (signer: Partial<EditableSignerDraft>) => {
		if (state.isOriginalFileDeleted.value) return false
		const minSigners = isAdminFlowForced.value ? 1 : 2
		return (
			state.isOrderedNumeric.value
			&& state.totalSigners.value >= minSigners
			&& state.canSave.value
			&& !isSignerSigned(signer)
		)
	})

	const canDelete = computed(() => (signer: Partial<EditableSignerDraft>) => {
		if (state.isOriginalFileDeleted.value) return false
		return state.canSave.value && !isSignerSigned(signer)
	})

	const canCustomizeMessage = computed(() => (signer: Partial<EditableSignerDraft>) => {
		if (state.isOriginalFileDeleted.value) return false
		if (isSignerSigned(signer) || !signer.signRequestId || signer.me) return false

		const method = getSignerMethod(signer as any)
		if (method === 'account' && !(signer as any).acceptsEmailNotifications) return false
		if (!canSignerActInOrder(signer)) return false

		return Boolean(method)
	})

	const canRequestSignature = computed(() => (signer: Partial<EditableSignerDraft>) => {
		if (state.isOriginalFileDeleted.value) return false

		const f = state.file.value
		if (
			!filesStore.canRequestSign
			|| f?.status === FILE_STATUS.DRAFT
			|| isSignerSigned(signer)
			|| !signer.signRequestId
			|| signer.me
			|| signer.status !== 0
		) {
			return false
		}

		return canSignerActInOrder(signer)
	})

	const canSendReminder = computed(() => (signer: Partial<EditableSignerDraft>) => {
		if (state.isOriginalFileDeleted.value) return false

		const f = state.file.value
		if (
			!filesStore.canRequestSign
			|| f?.status === FILE_STATUS.DRAFT
			|| isSignerSigned(signer)
			|| !signer.signRequestId
			|| signer.me
			|| signer.status !== 1
		) {
			return false
		}

		return canSignerActInOrder(signer)
	})

	/* ─────────────────────────────────────────────────────────────────────────
	 * IDENTIFY METHOD UI
	 * ───────────────────────────────────────────────────────────────────────── */

	const enabledMethods = computed<IdentifyMethodConfig[]>(() => {
		if (
			Object.keys(signerToEdit.value).length > 0
			&& signerToEdit.value.identifyMethods?.length
		) {
			const method       = getSignerMethod(signerToEdit.value as any)
			const methodConfig = getMethodConfig(method)
			if (methodConfig) return [methodConfig]
		}
		return methods.value.filter(m => m.enabled)
	})

	const isSignerMethodDisabled = computed(() => {
		if (
			Object.keys(signerToEdit.value).length > 0
			&& signerToEdit.value.identifyMethods?.length
		) {
			const method       = getSignerMethod(signerToEdit.value as any)
			const methodConfig = getMethodConfig(method)
			return !methodConfig?.enabled
		}
		return false
	})

	const disabledMethodName = computed(() => {
		if (isSignerMethodDisabled.value && signerToEdit.value.identifyMethods?.length) {
			const method       = getSignerMethod(signerToEdit.value as any)
			const methodConfig = getMethodConfig(method)
			return methodConfig?.friendly_name ?? method ?? ''
		}
		return ''
	})

	/* ─────────────────────────────────────────────────────────────────────────
	 * DEBOUNCED SAVE
	 * ───────────────────────────────────────────────────────────────────────── */

	const debouncedSave = debounce(async () => {
		try {
			const f             = filesStore.getFile()
			const signers       = state.isOrderedNumeric.value ? f?.signers : null
			const flow          = normalizeSignatureFlow(f?.signatureFlow)
			await filesStore.saveOrUpdateSignatureRequest({ signers, signatureFlow: flow })
		} catch (error: unknown) {
			showRequestError(error, t('libresign', 'Failed to save signature request'))
		}
	}, 1000)

	const debouncedTabChange = debounce((tabId: string) => {
		userConfigStore.update?.('files_list_signer_identify_tab', tabId)
	}, 500)

	/* ─────────────────────────────────────────────────────────────────────────
	 * DETAIL LOADER
	 * ───────────────────────────────────────────────────────────────────────── */

	async function ensureCurrentFileDetail(force = false): Promise<void> {
		const f = state.file.value
		if (
			typeof f?.id !== 'number'
			|| (!force && (!state.shouldLoadDetail.value || state.isCurrentFileDetailed.value))
		) {
			return
		}

		isLoadingFileDetail.value = true
		try {
			await filesStore.fetchFileDetail({ fileId: f.id, force })
		} catch (error: unknown) {
			showRequestError(error, t('libresign', 'Failed to load signer details'))
		} finally {
			isLoadingFileDetail.value = false
		}
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * SIGNING ORDER
	 * ───────────────────────────────────────────────────────────────────────── */

	function syncPreserveOrderWithFile(): void {
		const f = state.file.value
		if (!f) {
			preserveOrder.value = false
			return
		}
		const flow = normalizeSignatureFlow(f.signatureFlow)
		preserveOrder.value =
			(flow === 'ordered_numeric' || flow === 2)
			&& !isAdminFlowForced.value
	}

	function onPreserveOrderChange(value: boolean): void {
		preserveOrder.value = value
		const f = filesStore.getEditableFile()

		if (value) {
			if (f?.signers) {
				const orders = f.signers.map((s: EditableSignerDraft) => s.signingOrder ?? 0)
				const hasDuplicates = orders.length !== new Set(orders).size
				f.signers.forEach((s: EditableSignerDraft, i: number) => {
					if (!s.signingOrder || hasDuplicates) s.signingOrder = i + 1
				})
			}
			if (f) f.signatureFlow = 'ordered_numeric'
		} else if (!isAdminFlowForced.value) {
			if (f?.signers) {
				f.signers.forEach((s: EditableSignerDraft) => {
					if (!isSignerSigned(s)) s.signingOrder = 1
				})
			}
			if (f) f.signatureFlow = 'parallel'
		}

		debouncedSave()
	}

	function updateSigningOrder(signer: EditableSignerDraft, value: string | number): void {
		const order = parseInt(String(value), 10)
		if (isNaN(order)) return

		const f = filesStore.getEditableFile()
		const currentIndex = f?.signers?.findIndex(
			(s: EditableSignerDraft) => s.localKey === signer.localKey,
		) ?? -1

		if (currentIndex === -1 || !f?.signers) return

		const current = f.signers[currentIndex]
		if (!current) return

		current.signingOrder = order
		f.signers = [...f.signers].sort((a: EditableSignerDraft, b: EditableSignerDraft) =>
			(a.signingOrder ?? 999) - (b.signingOrder ?? 999),
		)
	}

	function confirmSigningOrder(signer: EditableSignerDraft): void {
		const f = filesStore.getEditableFile()
		const currentIndex = f?.signers?.findIndex(
			(s: EditableSignerDraft) => s.localKey === signer.localKey,
		) ?? -1

		if (currentIndex === -1 || !f?.signers) return

		const current = f.signers[currentIndex]
		if (!current) return

		const newOrder = current.signingOrder
		const oldOrder = signer.signingOrder

		if (newOrder === undefined || oldOrder === undefined) return

		f.signers.forEach((item: EditableSignerDraft, index: number) => {
			if (index === currentIndex) return
			const itemOrder = item.signingOrder
			if (itemOrder === undefined) return
			if (newOrder < oldOrder && itemOrder >= newOrder && itemOrder < oldOrder) {
				item.signingOrder = itemOrder + 1
			} else if (newOrder > oldOrder && itemOrder > oldOrder && itemOrder <= newOrder) {
				item.signingOrder = itemOrder - 1
			}
		})

		const sorted = [...f.signers].sort((a: EditableSignerDraft, b: EditableSignerDraft) =>
			(a.signingOrder ?? 999) - (b.signingOrder ?? 999),
		)

		if (sorted.every(s => typeof s.signingOrder === 'number')) {
			normalizeSigningOrders(sorted as Array<{ signingOrder: number }>)
		}

		f.signers = sorted
		debouncedSave()
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * SIGNER DIALOG
	 * ───────────────────────────────────────────────────────────────────────── */

	function addSigner(): void {
		signerToEdit.value = {}
		activeTab.value    = userConfigStore.files_list_signer_identify_tab ?? ''
		filesStore.enableIdentifySigner()
	}

	function editSigner(signer: EditableSignerDraft): void {
		signerToEdit.value = {
			localKey:        signer.localKey,
			displayName:     signer.displayName,
			description:     signer.description as string | undefined,
			identifyMethods: signer.identifyMethods?.map((m: IdentifyMethodRecord) => ({
				method: m.method,
				value:  m.value ?? '',
			})),
		}

		const method = getSignerMethod(signer as any)
		if (method) activeTab.value = `tab-${method}`
		filesStore.enableIdentifySigner()
	}

	function customizeMessage(signer: EditableSignerDraft): void {
		signerToEdit.value = {
			localKey:        signer.localKey,
			displayName:     signer.displayName,
			description:     signer.description as string | undefined,
			identifyMethods: signer.identifyMethods?.map((m: IdentifyMethodRecord) => ({
				method: m.method,
				value:  m.value ?? '',
			})),
		}
		filesStore.enableIdentifySigner()
	}

	function onTabChange(tabId: string): void {
		if (activeTab.value !== tabId) {
			activeTab.value = tabId
			debouncedTabChange(tabId)
		}
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * VALIDATION / OPEN
	 * ───────────────────────────────────────────────────────────────────────── */

	function getValidationFileUuid(): string | number | null {
		const f = state.file.value
		if (f?.uuid) return f.uuid

		const signer = f?.signers?.find((r: EditableSignerDraft) => r.me) ?? f?.signers?.[0]
		if (signer?.sign_uuid) return signer.sign_uuid

		const loaded = loadState<string | null>('libresign', 'sign_request_uuid', null)
		if (loaded) return loaded

		return f?.id ?? null
	}

	function validationFile(): void {
		const uuid = getValidationFileUuid()
		if (!uuid) {
			showError(t('libresign', 'Document not found'))
			return
		}

		if (useModal) {
			const absoluteUrl = generateUrl('/apps/libresign/p/validation/{uuid}', { uuid })
			const route       = router.resolve({ name: 'ValidationFileExternal', params: { uuid } })
			modalSrc.value    = route.href ?? absoluteUrl
			return
		}

		router.push({ name: 'ValidationFile', params: { uuid } })
		sidebarStore.hideSidebar()
	}

	function openFile(): void {
		const f       = state.file.value
		const fileUrl = currentFileUrl.value

		if (!fileUrl) {
			showError(t('libresign', 'Document URL not found'))
			return
		}
		if (typeof f?.name !== 'string' || typeof f?.nodeId !== 'number') {
			showError(t('libresign', 'Document not found'))
			return
		}

		openDocument({ fileUrl, filename: f.name, nodeId: f.nodeId })
	}

	function closeModal(): void {
		modalSrc.value = ''
		filesStore.flushSelectedFile()
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * SAVE / REQUEST SIGNATURES
	 * ───────────────────────────────────────────────────────────────────────── */

	async function save(): Promise<void> {
		await ensureCurrentFileDetail()
		hasLoading.value = true
		try {
			await filesStore.saveOrUpdateSignatureRequest({})
			emitEventBus(
				'libresign:show-visible-elements',
				new CustomEvent('libresign:show-visible-elements'),
			)
		} catch (error: unknown) {
			showRequestError(error, t('libresign', 'Failed to save signature request'))
		} finally {
			hasLoading.value = false
		}
	}

	function request(): void {
		showConfirmRequest.value = true
	}

	async function confirmRequest(): Promise<void> {
		await ensureCurrentFileDetail()
		hasLoading.value = true
		try {
			const response = await filesStore.saveOrUpdateSignatureRequest({ status: 1 })
			showSuccess(t('libresign', (response as any)?.message ?? 'Signature requested'), true, true)
			showConfirmRequest.value = false
		} catch (error: unknown) {
			showRequestError(error, t('libresign', 'Failed to request signatures'))
		} finally {
			hasLoading.value = false
		}
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * SIGN
	 * ───────────────────────────────────────────────────────────────────────── */

	async function sign(): Promise<void> {
		await ensureCurrentFileDetail()
		const f = state.file.value

		if (f?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
			validationFile()
			return
		}

		const uuid = (f as any)?.signUuid ?? null

		if (useModal) {
			const absoluteUrl = generateUrl('/apps/libresign/p/sign/{uuid}/pdf', { uuid })
			const route       = router.resolve({ name: 'SignPDFExternal', params: { uuid } })
			modalSrc.value    = route.href ?? absoluteUrl
			return
		}

		signStore.setFileToSign(filesStore.getFile())
		router.push({ name: 'SignPDF', params: { uuid } })
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * MANAGE FILES (envelope)
	 * ───────────────────────────────────────────────────────────────────────── */

	async function openManageFiles(): Promise<void> {
		hasLoading.value = true
		const response   = await filesStore.saveOrUpdateSignatureRequest({})
		hasLoading.value = false

		if ((response as any)?.success === false && (response as any)?.message) {
			showError((response as any).message)
			return
		}

		showEnvelopeFilesDialog.value = true
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * NOTIFY / PER-SIGNER REQUEST
	 * ───────────────────────────────────────────────────────────────────────── */

	async function sendNotify(signer: EditableSignerDraft): Promise<void> {
		if (!signer.signRequestId) {
			showError(t('libresign', 'Signer request not found'))
			return
		}
		const f = filesStore.getEditableFile()
		if (!f?.id) {
			showError(t('libresign', 'Document not found'))
			return
		}

		try {
			const { data } = await axios.post<NotifySignerSuccess>(
				generateOcsUrl('/apps/libresign/api/v1/notify/signer'),
				{ fileId: f.id, signRequestId: signer.signRequestId },
			)
			showSuccess(t('libresign', (data as any)?.ocs?.data?.message ?? 'Reminder sent'), true, true)
		} catch (error: unknown) {
			showRequestError(error, t('libresign', 'Failed to send reminder'))
		}
	}

	function requestSignatureForSigner(signer: EditableSignerDraft): void {
		selectedSigner.value           = signer
		showConfirmRequestSigner.value = true
	}

	async function confirmRequestSigner(): Promise<void> {
		if (!selectedSigner.value) return

		hasLoading.value = true
		try {
			const selectedSignRequestId = selectedSigner.value.signRequestId
			if (!selectedSignRequestId) {
				showError(t('libresign', 'Signer request not found'))
				return
			}

			const f       = filesStore.getEditableFile()
			const signers = (f.signers ?? []).map((s: EditableSignerDraft) => {
				if (s.signRequestId === selectedSignRequestId) {
					return {
						...s,
						status:     SIGN_REQUEST_STATUS.ABLE_TO_SIGN,
						statusText: getSignRequestStatusText(SIGN_REQUEST_STATUS.ABLE_TO_SIGN),
					}
				}
				return s
			})

			await filesStore.saveOrUpdateSignatureRequest({ signers: signers as never, status: 1 })
			showSuccess(t('libresign', 'Signature requested'), true, true)
			showConfirmRequestSigner.value = false
			selectedSigner.value           = null
		} catch (error: unknown) {
			showRequestError(error, t('libresign', 'Failed to request signature'))
		} finally {
			hasLoading.value = false
		}
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * LONG POLLING
	 * ───────────────────────────────────────────────────────────────────────── */

	function startSigningProgressPolling(): void {
		const f = state.file.value
		if (typeof f?.id !== 'number') return

		signingProgressStatus.value     = f.status == null ? null : Number(f.status)
		signingProgressStatusText.value = f.statusText ?? ''
		signingProgress.value           = null

		stopPollingFunction.value = startLongPolling(
			f.id,
			Number(f.status ?? 0),
			(data: PollingStatusData) => {
				signingProgressStatus.value     = data.status
				signingProgressStatusText.value = data.statusText ?? ''
				signingProgress.value           = data.progress ?? null

				const editable = filesStore.getEditableFile()
				if (editable) {
					editable.status     = data.status
					editable.statusText = data.statusText ?? editable.statusText
				}
			},
			() => !state.file.value || state.file.value.id !== f.id,
			(error: unknown) => {
				console.error('Signing progress polling error:', error)
				showError(t('libresign', 'Error monitoring signing progress'))
			},
		)
	}

	function stopSigningProgressPolling(): void {
		if (stopPollingFunction.value) {
			stopPollingFunction.value()
			stopPollingFunction.value = null
		}
		signingProgress.value           = null
		signingProgressStatus.value     = null
		signingProgressStatusText.value = ''
	}

	/* ─────────────────────────────────────────────────────────────────────────
	 * EVENT BUS
	 * ───────────────────────────────────────────────────────────────────────── */

	const handleEditSigner = ((event: NextcloudEvent) => {
		editSigner((event as CustomEvent<EditableSignerDraft>).detail)
	}) as EventHandler<NextcloudEvent>

	/* ─────────────────────────────────────────────────────────────────────────
	 * WATCHERS
	 * ───────────────────────────────────────────────────────────────────────── */

	watch(
		() => filesStore.selectedFileId,
		(newId) => {
			if (newId) {
				syncPreserveOrderWithFile()
				void ensureCurrentFileDetail()
			}
		},
		{ immediate: true },
	)

	watch(
		() => state.file.value?.status,
		(newStatus) => {
			if (newStatus === FILE_STATUS.SIGNING_IN_PROGRESS) {
				startSigningProgressPolling()
			} else if (stopPollingFunction.value) {
				stopSigningProgressPolling()
			}
		},
	)

	/* ─────────────────────────────────────────────────────────────────────────
	 * LIFECYCLE
	 * ───────────────────────────────────────────────────────────────────────── */

	onMounted(() => {
		subscribe('libresign:edit-signer', handleEditSigner)
		filesStore.disableIdentifySigner()
		activeTab.value = userConfigStore.files_list_signer_identify_tab ?? ''
		syncPreserveOrderWithFile()
		void ensureCurrentFileDetail()
	})

	onBeforeUnmount(() => {
		unsubscribe('libresign:edit-signer', handleEditSigner)
		if (stopPollingFunction.value) stopSigningProgressPolling()
	})

	/* ─────────────────────────────────────────────────────────────────────────
	 * RETURN
	 * ───────────────────────────────────────────────────────────────────────── */

	return {
		// ── State proxy ──────────────────────────────────────────────────────
		state,

		// ── Local UI refs ─────────────────────────────────────────────────────
		hasLoading,
		isLoadingFileDetail,
		signerToEdit,
		modalSrc,
		documentData,
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
		stopPollingFunction,

		// ── Controller-only ComputedRefs ──────────────────────────────────────
		currentUserDisplayName,
		size,
		modalTitle,
		fileName,
		signatureFlow,
		isAdminFlowForced,
		enabledMethods,
		isSignerMethodDisabled,
		disabledMethodName,
		signingOrderDiagramSigners,
		currentFileUrl,
		canManageSigners,

		// ── Per-signer factories ──────────────────────────────────────────────
		canEditSigningOrder,
		canDelete,
		canCustomizeMessage,
		canRequestSignature,
		canSendReminder,

		// ── Actions ───────────────────────────────────────────────────────────
		addSigner,
		editSigner,
		customizeMessage,
		onTabChange,
		onPreserveOrderChange,
		syncPreserveOrderWithFile,
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

		startSigningProgressPolling,
		stopSigningProgressPolling,

		ensureCurrentFileDetail,
		getValidationFileUuid,
		isSignElementsAvailable,
		closeModal,

		// ── Internals ─────────────────────────────────────────────────────────
		normalizeSignatureFlow,
		getSignerMethod,
		getMethodConfig,
		isSignerSigned,
		canSignerActInOrder,
		getSvgIcon,
		showRequestError,
		debouncedSave,
		hasAnyDraftSigner,
		getCurrentSigningOrder,
		hasOrderDraftSigners,
		hasSequentialDraftSigners,
	}
}
