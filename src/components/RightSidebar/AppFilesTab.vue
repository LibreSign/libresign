<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<h3 v-if="filesStore.getSubtitle()">
			{{ filesStore.getSubtitle() }}
		</h3>
		<NcButton v-if="canSignImmediately || isDirectSigning"
			variant="tertiary"
			:disabled="isDirectSigning"
			@click="signPdfNow">
			<template #icon>
				<NcLoadingIcon v-if="isDirectSigning" :size="20" />
				<NcIconSvgWrapper v-else :path="mdiSignature" :size="20" />
			</template>
			<!-- TRANSLATORS One-time signing action in the LibreSign sidebar for an unsigned PDF. -->
			{{ t('libresign', 'Sign PDF now') }}
		</NcButton>
		<RequestSignatureTab :use-modal="true" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { mdiSignature } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import type { Event as NextcloudEvent, EventHandler } from '@nextcloud/event-bus'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { getNavigation } from '@nextcloud/files'
import { generateRemoteUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'
import DirectSignDialog from '../Signing/DirectSignDialog.vue'
import { canSignDirectly } from '../../services/directSigning.ts'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { usePoliciesStore } from '../../store/policies.ts'
import { addCurrentAccountAsSigner } from '../../services/selfSigning.ts'
import type {
	EditableFileSettingsDraft,
	FileListItemRecord,
} from '../../types/index'

defineOptions({
	name: 'AppFilesTab',
})

type PendingEnvelope = {
	id: number
	uuid?: string | null
	name: string
	nodeType?: string
	filesCount?: number
	files?: Array<Pick<FileListItemRecord, 'fileId'>>
	signers?: Array<{ displayName?: string; email?: string; signRequestId?: number }>
	settings?: Pick<EditableFileSettingsDraft, 'path'>
}

type FileInfo = {
	// Nextcloud node id as tab.ts sends it: the numeric `fileid` when the node
	// has one, otherwise the string `Node.id` of `@nextcloud/files`.
	id: number | string
	type?: string
	name?: string
	path?: string
	attributes?: Record<string, unknown>
}

type LibreSignNodePayload = {
	path?: string
	nodeId?: number
}

type DeletedNode = {
	fileid?: number | string
	id?: number | string
	fileId?: number | string
	nodeId?: number | string
}

type EnvelopeRenamedPayload = {
	uuid?: string
	name?: string
}

const filesStore = useFilesStore()
const sidebarStore = useSidebarStore()
const policiesStore = usePoliciesStore()

const sidebarTitleObserver = ref<MutationObserver | null>(null)

type DirectSignTarget = { nodeId: number; name: string }
const directSignTarget = ref<DirectSignTarget | null>(null)
const isDirectSigning = ref(false)
const directlySignedNodeId = ref<number | null>(null)
const canSignImmediately = computed(() => {
	const target = directSignTarget.value
	const file = filesStore.getFile()
	if (!target || isDirectSigning.value || directlySignedNodeId.value === target.nodeId || filesStore.hasSigners(file)) {
		return false
	}
	if (!loadState('libresign', 'certificate_ok', false)
		|| !loadState('libresign', 'can_request_sign', false)) {
		return false
	}
	const policies = loadState<{ policies?: { identify_methods?: { effectiveValue?: import('../../types/index').EffectivePolicyValue } } }>(
		'libresign', 'effective_policies', { policies: {} },
	)
	return canSignDirectly(policies?.policies?.identify_methods?.effectiveValue ?? null)
})

async function signPdfNow() {
	if (!canSignImmediately.value || !directSignTarget.value) {
		return
	}
	const target = directSignTarget.value
	isDirectSigning.value = true
	try {
		const signed = await spawnDialog(DirectSignDialog, target)
		if (signed) {
			directlySignedNodeId.value = target.nodeId
		}
	} finally {
		isDirectSigning.value = false
	}
}

function handleEnvelopeRenamed({ uuid, name }: EnvelopeRenamedPayload) {
	const current = filesStore.getFile()
	if (current?.uuid && current.uuid === uuid) {
		updateSidebarTitle(name)
	}
}

async function checkAndLoadPendingEnvelope() {
	const pendingEnvelope = (window as Window & { OCA?: { Libresign?: { pendingEnvelope?: PendingEnvelope } } }).OCA?.Libresign?.pendingEnvelope
	if (!pendingEnvelope) {
		return false
	}

	await filesStore.addFile(pendingEnvelope)
	filesStore.selectFile(pendingEnvelope.id)
	sidebarStore.activeRequestSignatureTab()
	delete (window as Window & { OCA?: { Libresign?: { pendingEnvelope?: PendingEnvelope } } }).OCA?.Libresign?.pendingEnvelope

	nextTick(() => {
		updateSidebarTitle(pendingEnvelope.name)
	})

	return true
}

function updateSidebarTitle(envelopeName?: string) {
	if (!envelopeName) { return }

	disconnectTitleObserver()

	const titleElement = document.querySelector('.app-sidebar-header__mainname')

	if (titleElement) {
		titleElement.textContent = envelopeName
		titleElement.setAttribute('title', envelopeName)

		sidebarTitleObserver.value = new MutationObserver(() => {
			if (titleElement.textContent !== envelopeName) {
				titleElement.textContent = envelopeName
				titleElement.setAttribute('title', envelopeName)
			}
		})

		sidebarTitleObserver.value.observe(titleElement, {
			childList: true,
			characterData: true,
			subtree: true,
		})

		setTimeout(() => disconnectTitleObserver(), 5000)
	}
}

function disconnectTitleObserver() {
	if (sidebarTitleObserver.value) {
		sidebarTitleObserver.value.disconnect()
		sidebarTitleObserver.value = null
	}
}

async function preparePendingSelfSign(nodeId: number | string): Promise<void> {
	const pendingNodeId = window.OCA?.Libresign?.pendingSelfSignNodeId
	if (pendingNodeId === undefined || String(pendingNodeId) !== String(nodeId)) {
		return
	}
	delete window.OCA.Libresign.pendingSelfSignNodeId
	try {
		await policiesStore.fetchEffectivePolicies()
		const response = await addCurrentAccountAsSigner(
			filesStore,
			policiesStore.getEffectiveValue('identify_methods'),
			{ readyToSign: true },
		)
		if (response && 'success' in response && response.success === false) {
			// TRANSLATORS Error shown when the signed-in account cannot join a signature request.
			showError(('message' in response && response.message) || t('libresign', 'Cannot add your account as signer'))
		}
	} catch {
		// TRANSLATORS Error shown when adding the current account to a signature request fails.
		showError(t('libresign', 'Failed to add signer'))
	}
}

async function update(fileInfo: FileInfo) {
	const nodeId = Number(fileInfo.id)
	directSignTarget.value = Number.isSafeInteger(nodeId) && nodeId > 0
		&& fileInfo.type !== 'folder'
		&& fileInfo.name?.toLowerCase().endsWith('.pdf')
		&& fileInfo.attributes?.['libresign-signature-status'] === undefined
		? { nodeId, name: fileInfo.name }
		: null
	if (await checkAndLoadPendingEnvelope()) {
		return
	}

	disconnectTitleObserver()

	const isEnvelopeFolder = fileInfo.type === 'folder'
		&& fileInfo.attributes?.['libresign-signature-status'] !== undefined

	if (isEnvelopeFolder) {
		await filesStore.getAllFiles({
			'nodeIds[]': [fileInfo.id],
			force_fetch: true,
		})
	}

	const fileId = await filesStore.selectFileByNodeId(fileInfo.id)
	if (fileId) {
		const displayName = filesStore.getSelectedFileView()?.name || fileInfo.name
		await preparePendingSelfSign(fileInfo.id)

		nextTick(() => {
			const titleElement = document.querySelector('.app-sidebar-header__mainname')
			if (titleElement && displayName) {
				titleElement.textContent = displayName
				titleElement.setAttribute('title', displayName)
			}
		})
		return
	}

	await filesStore.addFile({
		id: -fileInfo.id,
		nodeId: fileInfo.id,
		name: fileInfo.name,
		file: {
			url: generateRemoteUrl(`dav/files/${getCurrentUser()?.uid}/${fileInfo.path + '/' + fileInfo.name}`)
			.replace(/\/\/$/, '/'),
		},
		signers: [],
	})
	filesStore.selectFile(-fileInfo.id)
	sidebarStore.activeRequestSignatureTab()
	await preparePendingSelfSign(fileInfo.id)

	nextTick(() => {
		const titleElement = document.querySelector('.app-sidebar-header__mainname')
		if (titleElement && fileInfo.name) {
			titleElement.textContent = fileInfo.name
			titleElement.setAttribute('title', fileInfo.name)
		}
	})
}

async function handleLibreSignFileChangeWithPath(path: string, eventType: string) {
	const client = getClient()
	const propfindPayload = getDefaultPropfind()
	const rootPath = getRootPath()

	const result = await client.stat(`${rootPath}${path}`, {
		details: true,
		data: propfindPayload,
	})
	const statResult = 'data' in result ? result.data : result
	emit(`files:node:${eventType}`, resultToNode(statResult))
}

async function handleLibreSignFileChangeAtCurretntFolder() {
	const client = getClient()
	const propfindPayload = getDefaultPropfind()
	const rootPath = getRootPath()

	const navigation = getNavigation()
	const currentFolder = navigation?.active?.params?.dir || '/'

	const result = await client.stat(`${rootPath}${currentFolder}`, {
		details: true,
		data: propfindPayload,
	})
	const statResult = 'data' in result ? result.data : result
	emit('files:node:updated', resultToNode(statResult))
}

async function handleLibreSignFileChange({ path, nodeId }: LibreSignNodePayload, eventType: string) {
	if (!window.location.pathname.includes('/apps/files')) {
		return
	}

	if (path) {
		await handleLibreSignFileChangeWithPath(path, eventType)
	} else if (nodeId) {
		await handleLibreSignFileChangeAtCurretntFolder()
	}
}

async function handleLibreSignFileCreated(payload: LibreSignNodePayload) {
	await handleLibreSignFileChange(payload, 'created')
}

async function handleLibreSignFileUpdated(payload: LibreSignNodePayload) {
	await handleLibreSignFileChange(payload, 'updated')
}

function handleFilesNodeDeleted(node: DeletedNode) {
	const rawNodeId = node?.fileid ?? node?.id ?? node?.fileId ?? node?.nodeId
	const nodeId = typeof rawNodeId === 'string' ? parseInt(rawNodeId, 10) : rawNodeId

	if (!nodeId) {
		return
	}

	filesStore.removeFileByNodeId(nodeId)
}

const onLibreSignFileCreated = ((payload: NextcloudEvent) => {
	void handleLibreSignFileCreated((payload ?? {}) as LibreSignNodePayload)
}) as EventHandler<NextcloudEvent>

const onLibreSignFileUpdated = ((payload: NextcloudEvent) => {
	void handleLibreSignFileUpdated((payload ?? {}) as LibreSignNodePayload)
}) as EventHandler<NextcloudEvent>

const onFilesNodeDeleted = ((payload: NextcloudEvent) => {
	handleFilesNodeDeleted((payload ?? {}) as DeletedNode)
}) as EventHandler<NextcloudEvent>

const onEnvelopeRenamed = ((payload: NextcloudEvent) => {
	handleEnvelopeRenamed((payload ?? {}) as EnvelopeRenamedPayload)
}) as EventHandler<NextcloudEvent>

onMounted(() => {
	subscribe('libresign:file:created', onLibreSignFileCreated)
	subscribe('libresign:file:updated', onLibreSignFileUpdated)
	subscribe('files:node:deleted', onFilesNodeDeleted)
	subscribe('libresign:envelope:renamed', onEnvelopeRenamed)
})

onBeforeUnmount(() => {
	disconnectTitleObserver()
	unsubscribe('libresign:file:created', onLibreSignFileCreated)
	unsubscribe('libresign:file:updated', onLibreSignFileUpdated)
	unsubscribe('files:node:deleted', onFilesNodeDeleted)
	unsubscribe('libresign:envelope:renamed', onEnvelopeRenamed)
})

defineExpose({
	filesStore,
	sidebarStore,
	sidebarTitleObserver,
	t,
	handleEnvelopeRenamed,
	checkAndLoadPendingEnvelope,
	updateSidebarTitle,
	disconnectTitleObserver,
	preparePendingSelfSign,
	update,
	canSignImmediately,
	signPdfNow,
	handleLibreSignFileChangeWithPath,
	handleLibreSignFileChangeAtCurretntFolder,
	handleLibreSignFileChange,
	handleLibreSignFileCreated,
	handleLibreSignFileUpdated,
	handleFilesNodeDeleted,
	onLibreSignFileCreated,
	onLibreSignFileUpdated,
	onFilesNodeDeleted,
	onEnvelopeRenamed,
})
</script>
