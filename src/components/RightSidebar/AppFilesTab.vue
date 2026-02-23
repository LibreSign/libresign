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

<script>
import { t } from '@nextcloud/l10n'

import { getCurrentUser } from '@nextcloud/auth'
import { emit, subscribe } from '@nextcloud/event-bus'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { getNavigation } from '@nextcloud/files'
import { generateRemoteUrl } from '@nextcloud/router'

import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'

export default {
	name: 'AppFilesTab',
	components: {
		RequestSignatureTab,
	},
	setup() {
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		return {
			filesStore,
			sidebarStore,
		}
	},
	data() {
		return {
			sidebarTitleObserver: null,
			unsubscribeCreated: null,
			unsubscribeUpdated: null,
			unsubscribeDeleted: null,
			unsubscribeEnvelopeRenamed: null,
		}
	},
	mounted() {
		this.unsubscribeCreated = subscribe('libresign:file:created', this.handleLibreSignFileCreated)
		this.unsubscribeUpdated = subscribe('libresign:file:updated', this.handleLibreSignFileUpdated)
		this.unsubscribeDeleted = subscribe('files:node:deleted', this.handleFilesNodeDeleted)
		this.unsubscribeEnvelopeRenamed = subscribe('libresign:envelope:renamed', this.handleEnvelopeRenamed)
	},
	beforeUnmount() {
		this.disconnectTitleObserver()
		if (this.unsubscribeCreated) {
			this.unsubscribeCreated()
		}
		if (this.unsubscribeUpdated) {
			this.unsubscribeUpdated()
		}
		if (this.unsubscribeDeleted) {
			this.unsubscribeDeleted()
		}
		if (this.unsubscribeEnvelopeRenamed) {
			this.unsubscribeEnvelopeRenamed()
		}
	},
	methods: {
		t,
		handleEnvelopeRenamed({ uuid, name }) {
			const current = this.filesStore.getFile()
			if (current?.uuid && current.uuid === uuid) {
				this.updateSidebarTitle(name)
			}
		},
		async checkAndLoadPendingEnvelope() {
			const pendingEnvelope = window.OCA?.Libresign?.pendingEnvelope
			if (!pendingEnvelope) {
				return false
			}

			await this.filesStore.addFile(pendingEnvelope)
			this.filesStore.selectFile(pendingEnvelope.id)
			this.sidebarStore.activeRequestSignatureTab()
			delete window.OCA.Libresign.pendingEnvelope

			this.$nextTick(() => {
				this.updateSidebarTitle(pendingEnvelope.name)
			})

			return true
		},

		updateSidebarTitle(envelopeName) {
			if (!envelopeName) return

			this.disconnectTitleObserver()

			const titleElement = document.querySelector('.app-sidebar-header__mainname')

			if (titleElement) {
				titleElement.textContent = envelopeName
				titleElement.setAttribute('title', envelopeName)

				this.sidebarTitleObserver = new MutationObserver(() => {
					if (titleElement.textContent !== envelopeName) {
						titleElement.textContent = envelopeName
						titleElement.setAttribute('title', envelopeName)
					}
				})

				this.sidebarTitleObserver.observe(titleElement, {
					childList: true,
					characterData: true,
					subtree: true
				})

				setTimeout(() => this.disconnectTitleObserver(), 5000)
			}
		},

		disconnectTitleObserver() {
			if (this.sidebarTitleObserver) {
				this.sidebarTitleObserver.disconnect()
				this.sidebarTitleObserver = null
			}
		},

		async update(fileInfo) {
			if (await this.checkAndLoadPendingEnvelope()) {
				return
			}

			this.disconnectTitleObserver()

			const isEnvelopeFolder = fileInfo.type === 'folder' &&
				fileInfo.attributes?.['libresign-signature-status'] !== undefined

			if (isEnvelopeFolder) {
				await this.filesStore.getAllFiles({
					'nodeIds[]': [fileInfo.id],
					force_fetch: true,
				})
			}

			const fileId = await this.filesStore.selectFileByNodeId(fileInfo.id)
			if (fileId) {
				const file = this.filesStore.getFile()
				const displayName = file?.name || fileInfo.name

				this.$nextTick(() => {
					const titleElement = document.querySelector('.app-sidebar-header__mainname')
					if (titleElement) {
						titleElement.textContent = displayName
						titleElement.setAttribute('title', displayName)
					}
				})
				return
			}

			await this.filesStore.addFile({
				id: -fileInfo.id,
				nodeId: fileInfo.id,
				name: fileInfo.name,
				file: generateRemoteUrl(`dav/files/${getCurrentUser()?.uid}/${fileInfo.path + '/' + fileInfo.name}`)
					.replace(/\/\/$/, '/'),
				signers: [],
			})
			this.filesStore.selectFile(-fileInfo.id)
			this.sidebarStore.activeRequestSignatureTab()

			this.$nextTick(() => {
				const titleElement = document.querySelector('.app-sidebar-header__mainname')
				if (titleElement) {
					titleElement.textContent = fileInfo.name
					titleElement.setAttribute('title', fileInfo.name)
				}
			})
		},

		async handleLibreSignFileChangeWithPath(path, eventType) {
			const client = getClient()
			const propfindPayload = getDefaultPropfind()
			const rootPath = getRootPath()

			const result = await client.stat(`${rootPath}${path}`, {
				details: true,
				data: propfindPayload,
			})
			emit(`files:node:${eventType}`, resultToNode(result.data))
		},

		async handleLibreSignFileChangeAtCurretntFolder() {
			const client = getClient()
			const propfindPayload = getDefaultPropfind()
			const rootPath = getRootPath()

			const navigation = getNavigation()
			const currentFolder = navigation?.active?.params?.dir || '/'

			const result = await client.stat(`${rootPath}${currentFolder}`, {
				details: true,
				data: propfindPayload,
			})
			emit('files:node:updated', resultToNode(result.data))
		},

		async handleLibreSignFileChange({ path, nodeId }, eventType) {
			if (!window.location.pathname.includes('/apps/files')) {
				return
			}

			if (path) {
				await this.handleLibreSignFileChangeWithPath(path, eventType)
			} else if (nodeId) {
				await this.handleLibreSignFileChangeAtCurretntFolder()
			}
		},

		async handleLibreSignFileCreated(payload) {
			await this.handleLibreSignFileChange(payload, 'created')
		},

		async handleLibreSignFileUpdated(payload) {
			await this.handleLibreSignFileChange(payload, 'updated')
		},

		handleFilesNodeDeleted(node) {
			const rawNodeId = node?.fileid ?? node?.id ?? node?.fileId ?? node?.nodeId
			const nodeId = typeof rawNodeId === 'string' ? parseInt(rawNodeId, 10) : rawNodeId

			if (!nodeId) {
				return
			}

			this.filesStore.removeFileByNodeId(nodeId)
		},
	},
}
</script>
