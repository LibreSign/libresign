/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp, type App as VueApp } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t, n } from '@nextcloud/l10n'
import { FileType, registerSidebarTab } from '@nextcloud/files'

import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'

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
	attributes: Record<string, unknown>
	isDirectory(): boolean
	get(key: string): string | undefined
}

interface SidebarNode {
	fileid?: number | string
	id?: number | string
	basename?: string
	displayname?: string
	name?: string
	dirname?: string
	path?: string
	type?: string
	mime?: string
	mimetype?: string
	attributes?: Record<string, unknown>
}

interface SidebarContext {
	node?: SidebarNode
}

interface TabComponentInstance {
	$el?: Element
	update?: (fileInfo: FileInfo) => void
}

function mapNodeToFileInfo(node: SidebarNode = {}): FileInfo {
	const name = node.basename || node.displayname || node.name || ''
	const dirname = node.dirname || (node.path ? node.path.substring(0, node.path.lastIndexOf('/')) : '')
	return {
		id: node.fileid ?? node.id ?? '',
		name,
		path: dirname,
		type: node.type || '',
		attributes: node.attributes || {},
		isDirectory() {
			return node.type === FileType.Folder || node.type === 'folder'
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
	_node?: SidebarNode
	_active?: boolean
	_vueApp?: VueApp<Element> | null
	_vueInstance?: TabComponentInstance | null
	_mountPromise?: Promise<void> | null
	node?: SidebarNode
	update(fileInfo: FileInfo): void
	setActive(active: boolean): Promise<void>
	mountVue(): Promise<void>
	destroyVue(): void
	updateFromNode(): void
}

function setupCustomElement() {
	if (window.customElements.get(tagName)) {
		return
	}

	const pinia = createPinia()

	class LibreSignSidebarTab extends HTMLElement implements LibreSignSidebarTabElement {
		_node?: SidebarNode
		_active?: boolean
		_vueApp?: VueApp<Element> | null
		_vueInstance?: TabComponentInstance | null
		_mountPromise?: Promise<void> | null

		connectedCallback() {
			void this.mountVue()
		}

		disconnectedCallback() {
			this.destroyVue()
		}

		set node(value: SidebarNode) {
			this._node = value
			this.updateFromNode()
		}

		get node(): SidebarNode | undefined {
			return this._node
		}

		async setActive(active: boolean) {
			this._active = active
			if (active) {
				this.updateFromNode()
			}
			return Promise.resolve()
		}

		update(fileInfo: FileInfo): void {
			if (this._vueInstance && typeof this._vueInstance.update === 'function') {
				this._vueInstance.update(fileInfo)
			}
		}

		async mountVue() {
			if (this._vueInstance || this._mountPromise) {
				return this._mountPromise ?? Promise.resolve()
			}

			this._mountPromise = (async () => {
				const { default: AppFilesTab } = await import('./components/RightSidebar/AppFilesTab.vue')
				if (!this.isConnected || this._vueInstance) {
					return
				}

				const app = createApp(AppFilesTab)
				app.config.globalProperties.t = t
				app.config.globalProperties.n = n
				app.use(pinia)

				const element = document.createElement('div')
				this._vueApp = app
				this._vueInstance = app.mount(element)
				this.appendChild(element)
				this.updateFromNode()
			})().finally(() => {
				this._mountPromise = null
			})

			return this._mountPromise
		}

		destroyVue() {
			this._vueApp?.unmount()
			this._vueApp = null
			this._vueInstance = null
		}

		updateFromNode() {
			if (!this._vueInstance || !this._node) {
				return
			}
			const fileInfo = mapNodeToFileInfo(this._node)
			if (typeof this._vueInstance.update === 'function') {
				this._vueInstance.update(fileInfo)
			}
		}
	}

	window.customElements.define(tagName, LibreSignSidebarTab as CustomElementConstructor)
}

function isEnabled(context: SidebarContext | null | undefined) {
	if (!context?.node) {
		return false
	}

	if (!loadState('libresign', 'certificate_ok')) {
		return false
	}

	const node = context.node
	const mimetype = node.mime || node.mimetype || ''
	const isFolder = node.type === FileType.Folder || node.type === 'folder'

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
