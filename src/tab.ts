/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t, n } from '@nextcloud/l10n'
import { FileType, registerSidebarTab } from '@nextcloud/files'

import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'

import AppFilesTab from './components/RightSidebar/AppFilesTab.vue'

import './actions/openInLibreSignAction'
import './actions/showStatusInlineAction'

import './style/icons.scss'

if (!window.OCA.Libresign) {
	window.OCA.Libresign = {}
}

const tagName = 'libresign-files-sidebar-tab'

interface FileInfo {
	id: number | string
	name: string
	path: string
	type: string
	attributes: any
	isDirectory(): boolean
	get(key: string): string | undefined
}

function mapNodeToFileInfo(node: any = {}): FileInfo {
	const name = node.basename || node.displayname || node.name || ''
	const dirname = node.dirname || (node.path ? node.path.substring(0, node.path.lastIndexOf('/')) : '')
	return {
		id: node.fileid || node.id,
		name,
		path: dirname,
		type: node.type,
		attributes: node.attributes,
		isDirectory() {
			return node.type === FileType.Folder || node.type === FileType.Collection || node.type === 'folder'
		},
		get(key: string) {
			if (key === 'mimetype') {
				return node.mime || node.mimetype
			}
			return undefined
		},
	}
}

interface LibreSignSidebarTabElement extends HTMLElement {
	_node?: any
	_active?: boolean
	_vueInstance?: any
	node?: any
	update(fileInfo: FileInfo): void
	setActive(active: boolean): Promise<void>
	mountVue(): void
	destroyVue(): void
	updateFromNode(): void
}

function setupCustomElement() {
	if (window.customElements.get(tagName)) {
		return
	}

	const pinia = createPinia()

	class LibreSignSidebarTab extends HTMLElement implements LibreSignSidebarTabElement {
		_node?: any
		_active?: boolean
		_vueInstance?: any

		connectedCallback() {
			this.mountVue()
			this.updateFromNode()
		}

		disconnectedCallback() {
			this.destroyVue()
		}

		set node(value: any) {
			this._node = value
			this.updateFromNode()
		}

		get node() {
			return this._node
		}

		async setActive(active: boolean) {
			this._active = active
			if (active) {
				this.updateFromNode()
			}
			return Promise.resolve()
		}

		mountVue() {
			if (this._vueInstance) {
				return
			}

			const app = createApp(AppFilesTab)
			app.config.globalProperties.t = t
			app.config.globalProperties.n = n
			app.use(pinia)

			const element = document.createElement('div')
			this._vueInstance = app.mount(element)
			this.appendChild(element)
		}

		destroyVue() {
			if (this._vueInstance && this._vueInstance.$el) {
				// For Vue 3, we need to unmount the app
				// The best way would be to track the app instance
				this._vueInstance = null
			}
		}

		updateFromNode() {
			if (!this._vueInstance || !this._node) {
				return
			}
			const fileInfo = mapNodeToFileInfo(this._node)
			// Call update on the mounted component if it exists
			if (typeof this._vueInstance.update === 'function') {
				this._vueInstance.update(fileInfo)
			}
		}
	}

	window.customElements.define(tagName, LibreSignSidebarTab as any)
}

function isEnabled(context: any) {
	if (!context?.node) {
		return false
	}

	if (!loadState('libresign', 'certificate_ok')) {
		return false
	}

	const node = context.node
	const mimetype = node.mime || node.mimetype || ''
	const isFolder = node.type === FileType.Folder || node.type === FileType.Collection || node.type === 'folder'

	if (isFolder) {
		const hasLibreSignStatus = node.attributes?.['libresign-signature-status'] !== undefined
		if (hasLibreSignStatus) {
			window.OCA.Libresign.fileInfo = mapNodeToFileInfo(node)
			return true
		}
		return false
	}

	window.OCA.Libresign.fileInfo = mapNodeToFileInfo(node)

	return mimetype === 'application/pdf'
}

window.addEventListener('DOMContentLoaded', () => {
	setupCustomElement()
	registerSidebarTab({
		id: 'libresign',
		order: 95,
		displayName: t('libresign', 'LibreSign'),
		iconSvgInline: LibreSignLogoDarkSvg,
		enabled: isEnabled,
		tagName,
	})
})
