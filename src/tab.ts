/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { t, n } from '@nextcloud/l10n'
import { FileType } from '@nextcloud/files'

import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'

import AppFilesTab from './components/RightSidebar/AppFilesTab.vue'

import './style/icons.scss'

if (!window.OCA.Libresign) {
	window.OCA.Libresign = {}
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

interface FileInfo {
	id: number | string
	name: string
	path: string
	type: string
	attributes: Record<string, unknown>
	isDirectory(): boolean
	get(key: string): string | undefined
}

interface TabComponentInstance {
	update?: (fileInfo: FileInfo) => void
}

window.addEventListener('DOMContentLoaded', () => {
	const sidebarService = window.OCA.Files?.Sidebar
	if (sidebarService?.registerTab && sidebarService?.Tab) {
		const tabPinia = createPinia()
		let currentApp: ReturnType<typeof createApp> | null = null
		let currentInstance: TabComponentInstance | null = null

		sidebarService.registerTab(new sidebarService.Tab({
			id: 'libresign',
			name: t('libresign', 'LibreSign'),
			iconSvg: LibreSignLogoDarkSvg,
			enabled(rawFileInfo: unknown) {
				if (!loadState('libresign', 'certificate_ok')) {
					return false
				}
				if (!rawFileInfo) {
					return false
				}
				const f = rawFileInfo as { type?: string, mimetype?: string, mime?: string, attributes?: Record<string, unknown> }
				const isFolder = f.type === FileType.Folder || f.type === 'dir' || f.type === 'folder'
				if (isFolder) {
					return f.attributes?.['libresign-signature-status'] !== undefined
				}
				const mimetype = f.mimetype || f.mime || ''
				return mimetype === 'application/pdf'
			},
			mount(el: HTMLElement, rawFileInfo: unknown) {
				const fileInfo = rawFileInfo as FileInfo
				currentApp = createApp(AppFilesTab)
				currentApp.config.globalProperties.t = t
				currentApp.config.globalProperties.n = n
				currentApp.use(tabPinia)
				currentInstance = currentApp.mount(el) as TabComponentInstance
				if (typeof currentInstance?.update === 'function') {
					currentInstance.update(fileInfo)
				}
				window.OCA.Libresign.fileInfo = fileInfo
			},
			update(rawFileInfo: unknown) {
				const fileInfo = rawFileInfo as FileInfo
				if (typeof currentInstance?.update === 'function') {
					currentInstance.update(fileInfo)
				}
				window.OCA.Libresign.fileInfo = fileInfo
			},
			destroy() {
				if (currentApp) {
					currentApp.unmount()
					currentApp = null
				}
				currentInstance = null
			},
		}))
	}
})
