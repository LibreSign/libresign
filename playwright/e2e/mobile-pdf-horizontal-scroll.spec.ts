/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { devices, expect, test } from '@playwright/test'

test.use({
	...devices['Pixel 7'],
})

test('PDF viewer allows horizontal scrolling on mobile viewport', async ({ page }) => {
	// Navigate to a test page with a wide PDF
	await page.goto('./apps/libresign')
	
	// Wait for the app to load
	await expect(page.locator('[class*="pdf-editor"]')).toBeVisible({ timeout: 10000 })
	
	// Verify the PDF elements root container exists and is scrollable
	const pdfRoot = page.locator('.pdf-elements-root')
	await expect(pdfRoot).toBeVisible()
	
	// Check that overflow-x is set to auto (not hidden)
	const computedStyle = await pdfRoot.evaluate((el) => {
		return window.getComputedStyle(el).overflowX
	})
	
	expect(computedStyle).not.toBe('hidden')
	expect(['auto', 'scroll']).toContain(computedStyle)
	
	// Verify touch-action is set correctly for touch events
	const touchAction = await pdfRoot.evaluate((el) => {
		return window.getComputedStyle(el).touchAction
	})
	
	expect(touchAction).toContain('pan')
	expect(touchAction).not.toContain('pinch-zoom')
})
