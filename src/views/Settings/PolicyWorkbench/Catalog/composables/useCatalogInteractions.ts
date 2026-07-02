/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref } from 'vue'

type Surface = 'cards' | 'list'

type LastPress = {
	surface: Surface,
	key: string,
	x: number,
	y: number,
}

type RecentSelectionGesture = {
	surface: Surface,
	key: string,
	at: number,
}

const DRAG_OPEN_THRESHOLD_PX = 6
const SELECTION_GUARD_WINDOW_MS = 400

type UseCatalogInteractionsOptions = {
	getFilter: () => string,
	onOpenSetting: (key: string) => void,
}

export function useCatalogInteractions(options: UseCatalogInteractionsOptions) {
	const lastPress = ref<LastPress | null>(null)
	const recentSelectionGesture = ref<RecentSelectionGesture | null>(null)
	const clearCatalogFocusOnClose = ref(false)

	function hasActiveTextSelection() {
		const selection = window.getSelection()
		return !!selection && selection.type === 'Range' && selection.toString().trim().length > 0
	}

	function markSelectionGesture(surface: Surface, key: string) {
		if (!hasActiveTextSelection()) {
			return
		}

		recentSelectionGesture.value = {
			surface,
			key,
			at: Date.now(),
		}
	}

	function shouldIgnoreDueToRecentSelection(surface: Surface, key: string) {
		const gesture = recentSelectionGesture.value
		if (!gesture) {
			return false
		}

		const expired = (Date.now() - gesture.at) > SELECTION_GUARD_WINDOW_MS
		const matchesTarget = gesture.surface === surface && gesture.key === key
		if (expired || !matchesTarget) {
			return false
		}

		recentSelectionGesture.value = null
		return true
	}

	function isPlainPrimaryClick(event: MouseEvent) {
		const button = typeof event.button === 'number' ? event.button : 0
		const hasModifier = Boolean(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
		return button === 0 && !hasModifier
	}

	function trackPress(surface: Surface, key: string, event: PointerEvent) {
		if (event.button !== 0) {
			lastPress.value = null
			return
		}

		lastPress.value = {
			surface,
			key,
			x: event.clientX,
			y: event.clientY,
		}
	}

	function movedBeyondThreshold(event: MouseEvent, press: { x: number, y: number }) {
		const deltaX = Math.abs(event.clientX - press.x)
		const deltaY = Math.abs(event.clientY - press.y)
		return deltaX > DRAG_OPEN_THRESHOLD_PX || deltaY > DRAG_OPEN_THRESHOLD_PX
	}

	function openSettingFromPointer(surface: Surface, key: string, event: MouseEvent) {
		if (!isPlainPrimaryClick(event)) {
			return
		}

		if (shouldIgnoreDueToRecentSelection(surface, key)) {
			return
		}

		if (hasActiveTextSelection()) {
			return
		}

		const press = lastPress.value
		if (press && press.surface === surface && press.key === key && movedBeyondThreshold(event, press)) {
			return
		}

		clearCatalogFocusOnClose.value = true
		options.onOpenSetting(key)
	}

	function openSettingFromAction(key: string, event: MouseEvent) {
		clearCatalogFocusOnClose.value = event.detail > 0
		options.onOpenSetting(key)
	}

	function openSettingFromKeyboard(key: string) {
		clearCatalogFocusOnClose.value = false
		options.onOpenSetting(key)
	}

	function escapeRegExp(value: string) {
		return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
	}

	function escapeHtml(value: string) {
		return value
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#39;')
	}

	function highlightText(value: string) {
		const query = options.getFilter().trim()
		const safeValue = escapeHtml(value)
		if (!query) {
			return safeValue
		}

		const matcher = new RegExp(`(${escapeRegExp(query)})`, 'ig')
		return safeValue.replace(matcher, '<mark>$1</mark>')
	}

	return {
		clearCatalogFocusOnClose,
		markSelectionGesture,
		trackPress,
		openSettingFromPointer,
		openSettingFromAction,
		openSettingFromKeyboard,
		highlightText,
	}
}
