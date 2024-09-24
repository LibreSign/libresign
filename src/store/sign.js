/*
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
