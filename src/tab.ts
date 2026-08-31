/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp, type App as VueApp } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t, n } from '@nextcloud/l10n'
import { FileType } from '@nextcloud/files'

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
	isDirectory?: () => boolean
	get?: (key: string) => unknown
}

interface SidebarContext {
	node?: SidebarNode
}

interface TabComponentInstance {
	$el?: Element
	update?: (fileInfo: FileInfo) => void
}

function mapNodeToFileInfo(node: SidebarNode = {}): FileInfo {
	const getValue = (key: string) => node.get?.(key) ?? node[key as keyof SidebarNode]
	const path = String(getValue('path') ?? '')
	const name = String(getValue('basename') || getValue('displayname') || getValue('name') || '')
	const dirname = String(getValue('dirname') || (path ? path.substring(0, path.lastIndexOf('/')) : ''))
	const type = String(getValue('type') ?? '')
	return {
		id: (getValue('fileid') ?? getValue('id') ?? '') as number | string,
		name,
		path: dirname,
		type,
		attributes: getValue('attributes') as Record<string, unknown> || {},
		isDirectory() {
			return node.isDirectory?.() ?? (type === FileType.Folder || type === 'folder')
		},
		get(key: string) {
			if (key === 'mimetype') {
				return getValue('mime') as string || getValue('mimetype') as string
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
	const fileInfo = mapNodeToFileInfo(node)
	const mimetype = fileInfo.get('mimetype') || ''
	const isFolder = fileInfo.isDirectory()

	if (isFolder) {
		const hasLibreSignStatus = fileInfo.attributes['libresign-signature-status'] !== undefined
		if (hasLibreSignStatus) {
			window.OCA.Libresign.fileInfo = fileInfo
			return true
		}
		return false
	}

	window.OCA.Libresign.fileInfo = fileInfo

	return mimetype === 'application/pdf'
}

function registerLibresignSidebarTab() {
	setupCustomElement()

	const sidebar = (window.OCA?.Files as {
		Sidebar?: {
			Tab: new (config: Record<string, unknown>) => unknown
			registerTab: (tab: unknown) => void
		}
	} | undefined)?.Sidebar
	if (!sidebar?.registerTab || !sidebar?.Tab) {
		return
	}

	let tabElement: LibreSignSidebarTabElement | null = null
	sidebar.registerTab(new sidebar.Tab({
		id: 'libresign',
		order: 95,
		name: t('libresign', 'LibreSign'),
		icon: 'icon-rename',
		iconSvgInline: LibreSignLogoDarkSvg,
		enabled: (input: SidebarNode | SidebarContext) => isEnabled({ node: (input as SidebarContext).node ?? input as SidebarNode }),
		async mount(element: Element, node: SidebarNode) {
			tabElement = document.createElement(tagName) as LibreSignSidebarTabElement
			tabElement.node = node
			element.appendChild(tabElement)
			await tabElement.setActive(true)
		},
		update(node: SidebarNode) {
			if (tabElement) {
				tabElement.node = node
			}
		},
		destroy() {
			tabElement?.remove()
			tabElement = null
		},
	}))
}

if (document.readyState === 'loading') {
	window.addEventListener('DOMContentLoaded', registerLibresignSidebarTab, { once: true })
} else {
	registerLibresignSidebarTab()
}
