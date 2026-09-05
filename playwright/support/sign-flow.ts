/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, type Page } from '@playwright/test'

/**
 * Clicks the primary "Sign document" CTA in the sign sidebar.
 * Public sign pages can leave the button outside the viewport; force clicks
 * still fail there, so scroll first and fall back to a DOM click.
 */
export async function clickSignDocumentButton(page: Page): Promise<void> {
	const signButton = page.getByRole('button', { name: 'Sign document' }).first()
	await expect(signButton).toBeVisible({ timeout: 15_000 })
	await signButton.scrollIntoViewIfNeeded().catch(() => {})

	const clicked = await signButton.click({ timeout: 5_000 }).then(() => true).catch(() => false)
	if (!clicked) {
		await signButton.evaluate((element: HTMLElement) => element.click())
	}
}
