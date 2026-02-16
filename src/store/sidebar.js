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
		sidebarRoutes: ['fileslist', 'SignPDF', 'ValidationFile', 'IdDocsApprove'],
	}),

	getters: {
		canShow() {
			return this.show === false && this.activeTab.length > 0
		},
		isVisible() {
			return this.show === true && this.activeTab.length > 0
		},
	},

	actions: {
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
		handleRouteChange(routeName) {
			if (routeName && !this.sidebarRoutes.includes(routeName)) {
				this.hideSidebar()
			}
		},
		toggleSidebar() {
			set(this, 'show', !this.show)
		},
	},
})
