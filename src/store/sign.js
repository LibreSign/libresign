/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

import { loadState } from '@nextcloud/initial-state'

import { useFilesStore } from './files.js'
import { useSidebarStore } from './sidebar.js'
import { useSignMethodsStore } from './signMethods.js'

const defaultState = {
	errors: [],
	document: {
		name: '',
		description: '',
		status: '',
		statusText: '',
		url: '',
		nodeId: 0,
		uuid: '',
		signers: [],
	},
	mounted: false,
}

export const useSignStore = defineStore('sign', {
	state: () => ({ ...defaultState }),

	actions: {
		initFromState() {
			this.errors = loadState('libresign', 'errors', [])
			const pdf = loadState('libresign', 'pdf', [])
			const file = {
				name: loadState('libresign', 'filename'),
				description: loadState('libresign', 'description', ''),
				status: loadState('libresign', 'status'),
				statusText: loadState('libresign', 'statusText'),
				url: pdf.url,
				nodeId: loadState('libresign', 'nodeId'),
				uuid: loadState('libresign', 'uuid', null),
				signers: loadState('libresign', 'signers', []),
			}
			this.setDocumentToSign(file)
			const filesStore = useFilesStore()
			filesStore.addFile(file)
			filesStore.selectedNodeId = file.nodeId
		},
		setDocumentToSign(document) {
			if (document) {
				this.errors = []
				set(this, 'document', document)

				const sidebarStore = useSidebarStore()
				sidebarStore.activeSignTab()

				const signMethodsStore = useSignMethodsStore()
				const signer = document.signers.find(row => row.me) || {}
				signMethodsStore.settings = signer.signatureMethods
				return
			}
			this.reset()
		},
		reset() {
			set(this, 'document', defaultState)
			const sidebarStore = useSidebarStore()
			sidebarStore.setActiveTab()
		},
	},
})
