<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<h3 v-if="filesStore.getSubtitle()">
			{{ filesStore.getSubtitle() }}
		</h3>
		<RequestSignatureTab :use-modal="true" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import type { Event as NextcloudEvent, EventHandler } from '@nextcloud/event-bus'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { getNavigation } from '@nextcloud/files'
import { generateRemoteUrl } from '@nextcloud/router'

import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import type { components } from '../../types/openapi/openapi'

defineOptions({
	name: 'AppFilesTab',
})

type OpenApiNextcloudFile = components['schemas']['FileSummary']
type OpenApiSigner = components['schemas']['SignerSummary']

type PendingEnvelope = {
	id: number
	uuid?: OpenApiNextcloudFile['uuid']
	name: string
	nodeType?: OpenApiNextcloudFile['nodeType'] | string
	files?: Array<{ fileId?: string | number }>
	filesCount?: number
	signers?: Array<{
		displayName?: OpenApiSigner['displayName']
		email?: OpenApiSigner['email']
		signRequestId?: OpenApiSigner['signRequestId'] | string | number
	}>
	settings?: {
		path?: string
	}
}

type FileInfo = {
	id: number
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

const sidebarTitleObserver = ref<MutationObserver | null>(null)

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
	if (!envelopeName) return

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

async function update(fileInfo: FileInfo) {
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
		const file = filesStore.getFile()
		const displayName = file?.name || fileInfo.name

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
	update,
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
