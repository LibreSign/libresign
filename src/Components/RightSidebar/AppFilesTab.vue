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
		}
	},
	methods: {
		checkAndLoadPendingEnvelope() {
			const pendingEnvelope = window.OCA?.Libresign?.pendingEnvelope
			if (pendingEnvelope?.id) {
				this.filesStore.addFile(pendingEnvelope)
				this.filesStore.selectFile(pendingEnvelope.id)
				delete window.OCA.Libresign.pendingEnvelope

				this.$nextTick(() => {
					this.updateSidebarTitle(pendingEnvelope.name)
				})

				return true
			}
			return false
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
			if (this.checkAndLoadPendingEnvelope()) {
				return
			}

			this.disconnectTitleObserver()

			if (this.filesStore.selectedNodeId === fileInfo.id) {
				return
			}

			this.filesStore.addFile({
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
	},
}
</script>
