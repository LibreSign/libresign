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
		nodeType: 'file',
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

			const file = {
				name: loadState('libresign', 'filename', ''),
				description: loadState('libresign', 'description', ''),
				status: loadState('libresign', 'status', ''),
				statusText: loadState('libresign', 'statusText', ''),
				nodeId: loadState('libresign', 'nodeId', 0),
				uuid: loadState('libresign', 'uuid', null),
				signers: loadState('libresign', 'signers', []),
			}
			this.setFileToSign(file)
			const filesStore = useFilesStore()
			filesStore.addFile(file)
			filesStore.selectedNodeId = file.nodeId
		},
		setFileToSign(file) {
			if (file) {
				this.errors = []
				set(this, 'document', file)

				const sidebarStore = useSidebarStore()
				sidebarStore.activeSignTab()

				const signMethodsStore = useSignMethodsStore()
				const signer = file.signers.find(row => row.me) || {}
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
