/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable, useMutationObserver, usePreferredDark } from '@vueuse/core'
import { ref, watch } from 'vue'

import { checkIfDarkTheme } from '../utils/isDarkTheme.js'

/**
 * Check whether the dark theme is enabled on a specific element.
 * If you need to check an entire page, use `useIsDarkTheme` instead.
 * Reacts on element attributes changes and system theme changes.
 * @param {HTMLElement} el - The element to check for the dark theme enabled on
 * @return {boolean} - computed boolean whether the dark theme is enabled
 */
export function useIsDarkThemeElement(el = document.body) {
	const isDarkTheme = ref(checkIfDarkTheme(el))
	const isDarkSystemTheme = usePreferredDark()

	/** Update the isDarkTheme */
	function updateIsDarkTheme() {
		isDarkTheme.value = checkIfDarkTheme(el)
	}

	// Watch for element changes to handle data-theme* attributes change
	useMutationObserver(el, updateIsDarkTheme, { attributes: true })
	// Watch for system theme changes for the default theme
	watch(isDarkSystemTheme, updateIsDarkTheme, { immediate: true })

	return isDarkTheme
}

/**
 * Shared composable to check whether the dark theme is enabled on the page.
 * Reacts on body data-theme-* attributes changes and system theme changes.
 * @return {boolean} - computed boolean whether the dark theme is enabled
 */
export const useIsDarkTheme = createSharedComposable(() => useIsDarkThemeElement())
