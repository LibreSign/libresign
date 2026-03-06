/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useSidebarStore = defineStore('sidebar', () => {
	const show = ref(false)
	const activeTab = ref('')
	const sidebarRoutes = ref(['fileslist', 'SignPDF', 'SignPDFExternal', 'ValidationFile', 'IdDocsApprove'])

	const canShow = computed(() => show.value === false && activeTab.value.length > 0)
	const isVisible = computed(() => show.value === true && activeTab.value.length > 0)

	const showSidebar = () => {
		show.value = true
	}

	const activeSignTab = () => {
		activeTab.value = 'sign-tab'
		showSidebar()
	}

	const activeRequestSignatureTab = () => {
		activeTab.value = 'request-signature-tab'
		showSidebar()
	}

	const setActiveTab = (id) => {
		activeTab.value = id ?? ''
		if (id) {
			showSidebar()
		} else {
			hideSidebar()
		}
	}

	const hideSidebar = () => {
		show.value = false
	}

	const handleRouteChange = (routeName) => {
		if (routeName && !sidebarRoutes.value.includes(routeName)) {
			hideSidebar()
		}
	}

	const toggleSidebar = () => {
		show.value = !show.value
	}

	return {
		show,
		activeTab,
		sidebarRoutes,
		canShow,
		isVisible,
		showSidebar,
		activeSignTab,
		activeRequestSignatureTab,
		setActiveTab,
		hideSidebar,
		handleRouteChange,
		toggleSidebar,
	}
})
