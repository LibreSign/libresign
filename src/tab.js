/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { translate as t, translatePlural } from '@nextcloud/l10n'
import { FileType, registerSidebarTab } from '@nextcloud/files'

import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'

import AppFilesTab from './components/RightSidebar/AppFilesTab.vue'

import './actions/openInLibreSignAction.js'
import './actions/showStatusInlineAction.js'
import './plugins/vuelidate.js'

import './style/icons.scss'

Vue.prototype.t = t
Vue.prototype.n = translatePlural

if (!window.OCA.Libresign) {
	window.OCA.Libresign = {}
}

Vue.use(PiniaVuePlugin)

const pinia = createPinia()

const tagName = 'libresign-files-sidebar-tab'
const View = Vue.extend(AppFilesTab)

function mapNodeToFileInfo(node = {}) {
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
		get(key) {
			if (key === 'mimetype') {
				return node.mime || node.mimetype
			}
			return undefined
		},
	}
}

function setupCustomElement() {
	if (window.customElements.get(tagName)) {
		return
	}

	class LibreSignSidebarTab extends HTMLElement {
		connectedCallback() {
			this.mountVue()
			this.updateFromNode()
		}

		disconnectedCallback() {
			this.destroyVue()
		}

		set node(value) {
			this._node = value
			this.updateFromNode()
		}

		get node() {
			return this._node
		}

		async setActive(active) {
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

			const instance = new View({ pinia })
			instance.$mount()
			this._vueInstance = instance
			this.appendChild(instance.$el)
		}

		destroyVue() {
			if (this._vueInstance) {
				this._vueInstance.$destroy()
				this._vueInstance.$el.remove()
				this._vueInstance = null
			}
		}

		updateFromNode() {
			if (!this._vueInstance || !this._node) {
				return
			}
			const fileInfo = mapNodeToFileInfo(this._node)
			this._vueInstance.update?.(fileInfo)
		}
	}

	window.customElements.define(tagName, LibreSignSidebarTab)
}

function isEnabled(context) {
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
