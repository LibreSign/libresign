/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, type Page } from '@playwright/test'

/**
 * Opens the add-signer dialog from the request-signature sidebar.
 *
 * When `enable_observer_profile` is disabled the UI exposes a single "Add"
 * button. When enabled, "Add" opens a menu with "Signer" and "Observer".
 */
export async function clickAddSigner(page: Page): Promise<void> {
	const addButton = page.getByRole('button', { name: 'Add', exact: true })
	await expect(addButton).toBeVisible({ timeout: 15_000 })
	await addButton.click()

	const signerMenuItem = page.getByRole('menuitem', { name: 'Signer' })
	if (await signerMenuItem.isVisible({ timeout: 1000 }).catch(() => false)) {
		await signerMenuItem.click()
	}

	await expect(page.getByRole('dialog', { name: 'Add new signer' })).toBeVisible({ timeout: 10_000 })
}

/**
 * Asserts that the request-signature sidebar exposes the add-participant control.
 */
export async function expectAddSignerControlVisible(page: Page): Promise<void> {
	await expect(page.getByRole('button', { name: 'Add', exact: true })).toBeVisible({ timeout: 15_000 })
}
