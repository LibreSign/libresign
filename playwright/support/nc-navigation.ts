/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Browser-level navigation helpers for the Nextcloud / LibreSign UI.
 */

import type { Page } from '@playwright/test'

/**
 * Ensures the "Settings" section of the Nextcloud left sidebar is expanded
 * so that links like "Account" and "Policies" are visible.
 *
 * Works both when the sidebar is already expanded and when it still shows
 * only the collapsed "Settings" toggle button.
 */
export async function expandSettingsMenu(page: Page): Promise<void> {
	await page.keyboard.press('Escape').catch(() => {})
	const sidebar = page.locator('#app-navigation-vue')
	const settingsLink = sidebar.getByRole('link', { name: 'Account' })
	if (await settingsLink.count()) {
		return
	}

	const settingsToggle = sidebar.getByRole('button', { name: 'Settings' })
	if (await settingsToggle.count()) {
		await settingsToggle.first().click()
	}
}
