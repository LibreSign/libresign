/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Check if dark theme is used on a specific element
 * @param {HTMLElement} el - Element to check for dark theme, default is document.body, which is used for data-theme-* settings
 * @return {boolean} - Whether the dark theme is enabled via Nextcloud theme
 */
export function checkIfDarkTheme(el = document.body) {
	// Nextcloud uses --background-invert-if-dark for dark theme filters in CSS
	// Values:
	// - 'invert(100%)' for dark theme
	// - 'no' for light theme
	// This is the most reliable way to check for dark theme, including custom themes
	const backgroundInvertIfDark = window.getComputedStyle(el).getPropertyValue('--background-invert-if-dark')
	if (backgroundInvertIfDark !== undefined) {
		return backgroundInvertIfDark === 'invert(100%)'
	}

	// There is no theme? Fallback to the light theme
	return false
}
