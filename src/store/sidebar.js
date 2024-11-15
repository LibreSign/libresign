/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

export const useSidebarStore = defineStore('sidebar', {
	state: () => ({
		show: false,
		activeTab: '',
	}),

	actions: {
		canShow() {
			return this.show === false && this.activeTab.length > 0
		},
		isVisible() {
			return this.show === true && this.activeTab.length > 0
		},
		showSidebar() {
			set(this, 'show', true)
		},
		activeSignTab() {
			set(this, 'activeTab', 'sign-tab')
			this.showSidebar()
		},
		activeRequestSignatureTab() {
			set(this, 'activeTab', 'request-signature-tab')
			this.showSidebar()
		},
		setActiveTab(id) {
			set(this, 'activeTab', id ?? '')
			if (id) {
				this.showSidebar()
			} else {
				this.hideSidebar()
			}
		},
		hideSidebar() {
			set(this, 'show', false)
		},
	},
})
