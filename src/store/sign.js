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
		id: 0,
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
		async initFromState() {
			this.errors = loadState('libresign', 'errors', [])

			const file = {
				id: loadState('libresign', 'id', 0),
				name: loadState('libresign', 'filename', ''),
				description: loadState('libresign', 'description', ''),
				status: loadState('libresign', 'status', ''),
				statusText: loadState('libresign', 'statusText', ''),
				nodeId: loadState('libresign', 'nodeId', 0),
				nodeType: loadState('libresign', 'nodeType', ''),
				uuid: loadState('libresign', 'uuid', null),
				signers: loadState('libresign', 'signers', []),
			}
			const filesStore = useFilesStore()
			const sidebarStore = useSidebarStore()
			await filesStore.addFile(file)
			filesStore.selectFile(file.id)
			this.setFileToSign(file)
			sidebarStore.activeSignTab()
		},
		setFileToSign(file) {
			if (file) {
				this.errors = []
				set(this, 'document', file)

				const sidebarStore = useSidebarStore()
				sidebarStore.activeSignTab()

				const signMethodsStore = useSignMethodsStore()
				const signer = file.signers.find(row => row.me) || {}

				signMethodsStore.settings = signer.signatureMethods || {}

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
