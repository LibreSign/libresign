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
import { getCurrentUser } from '@nextcloud/auth'
import { emit, subscribe } from '@nextcloud/event-bus'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { getNavigation } from '@nextcloud/files'
import { generateRemoteUrl } from '@nextcloud/router'

import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'AppFilesTab',
	components: {
		RequestSignatureTab,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			sidebarTitleObserver: null,
			unsubscribeCreated: null,
			unsubscribeUpdated: null,
		}
	},
	mounted() {
		this.unsubscribeCreated = subscribe('libresign:file:created', this.handleLibreSignFileCreated)
		this.unsubscribeUpdated = subscribe('libresign:file:updated', this.handleLibreSignFileUpdated)
	},
	beforeUnmount() {
		this.disconnectTitleObserver()
		if (this.unsubscribeCreated) {
			this.unsubscribeCreated()
		}
		if (this.unsubscribeUpdated) {
			this.unsubscribeUpdated()
		}
	},
	methods: {
		async checkAndLoadPendingEnvelope() {
			const pendingEnvelope = window.OCA?.Libresign?.pendingEnvelope
			if (!pendingEnvelope) {
				return false
			}

			await this.filesStore.addFile(pendingEnvelope)
			this.filesStore.selectFile(pendingEnvelope.nodeId)
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
				console.log('Disconnecting sidebar title observer')
				this.sidebarTitleObserver.disconnect()
				this.sidebarTitleObserver = null
			}
		},

		async update(fileInfo) {
			if (await this.checkAndLoadPendingEnvelope()) {
				return
			}

			this.disconnectTitleObserver()

			if (this.filesStore.selectedNodeId === fileInfo.id) {
				return
			}

			await this.filesStore.addFile({
				nodeId: fileInfo.id,
				name: fileInfo.name,
				file: generateRemoteUrl(`dav/files/${getCurrentUser()?.uid}/${fileInfo.path + '/' + fileInfo.name}`)
					.replace(/\/\/$/, '/'),
				signers: [],
			})
			this.filesStore.selectFile(fileInfo.id)

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

		async handleLibreSignFileChangeWithNodeId(nodeId, eventType) {
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
				await this.handleLibreSignFileChangeWithNodeId(nodeId, eventType)
			}
		},

		async handleLibreSignFileCreated(payload) {
			await this.handleLibreSignFileChange(payload, 'created')
		},

		async handleLibreSignFileUpdated(payload) {
			await this.handleLibreSignFileChange(payload, 'updated')
		},
	},
}
</script>
