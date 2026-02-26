/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

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
			this.show = true
		},
		activeSignTab() {
			this.activeTab = 'sign-tab'
			this.showSidebar()
		},
		activeRequestSignatureTab() {
			this.activeTab = 'request-signature-tab'
			this.showSidebar()
		},
		setActiveTab(id) {
			this.activeTab = id ?? ''
			if (id) {
				this.showSidebar()
			} else {
				this.hideSidebar()
			}
		},
		hideSidebar() {
			this.show = false
		},
		handleRouteChange(routeName) {
			if (routeName && !this.sidebarRoutes.includes(routeName)) {
				this.hideSidebar()
			}
		},
		toggleSidebar() {
			this.show = !this.show
		},
	},
})
