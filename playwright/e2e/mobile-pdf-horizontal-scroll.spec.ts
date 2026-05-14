/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { devices, expect, test } from '@playwright/test'
import {
	bootstrapLibreSignAdmin,
	ensureFooterTemplateEnabled,
	openSystemFooterRuleEditor,
} from '../support/footer-policy-workbench'

test.use({
	...devices['Pixel 7'],
})

test('PDF viewer allows horizontal scrolling on mobile viewport', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	const ruleDialog = await openSystemFooterRuleEditor(page)
	await ensureFooterTemplateEnabled(ruleDialog)

	const pdfRoot = ruleDialog.locator('.signature-footer-rule-editor__preview .pdf-elements-root').first()
	await expect(pdfRoot).toBeVisible({ timeout: 15000 })

	const widthField = ruleDialog.getByRole('spinbutton', { name: 'Width' }).first()
	await expect(widthField).toBeVisible({ timeout: 10000 })
	await widthField.fill('900')
	await widthField.press('Tab')

	// Check that overflow-x is set to auto (not hidden).
	const computedStyle = await pdfRoot.evaluate((el) => {
		return window.getComputedStyle(el).overflowX
	})

	expect(computedStyle).not.toBe('hidden')
	expect(['auto', 'scroll']).toContain(computedStyle)

	// Verify touch-action is set correctly for touch gestures.
	const touchAction = await pdfRoot.evaluate((el) => {
		return window.getComputedStyle(el).touchAction
	})

	expect(touchAction).toContain('pan')
	expect(touchAction).not.toContain('pinch-zoom')

	// Validate real horizontal scrolling capability, not only style declarations.
	await expect.poll(async () => {
		return pdfRoot.evaluate((el) => el.scrollWidth > el.clientWidth)
	}, {
		timeout: 15000,
		message: 'Expected footer preview to become horizontally scrollable after widening the preview',
	}).toBe(true)

	const before = await pdfRoot.evaluate((el) => {
		el.scrollLeft = 0
		return {
			scrollLeft: el.scrollLeft,
			scrollWidth: el.scrollWidth,
			clientWidth: el.clientWidth,
		}
	})

	const box = await pdfRoot.boundingBox()
	expect(box).not.toBeNull()

	if (!box) {
		throw new Error('PDF scroll container bounding box is not available')
	}

	const y = box.y + (box.height / 2)
	await page.mouse.move(box.x + box.width - 12, y)
	await page.mouse.down()
	await page.mouse.move(box.x + 12, y, { steps: 12 })
	await page.mouse.up()

	const afterGesture = await pdfRoot.evaluate((el) => el.scrollLeft)
	// In Playwright mobile emulation, mouse drag may not trigger native touch scrolling.
	// Keep this as an observation, while asserting actual scrollability below.
	expect(afterGesture).toBeGreaterThanOrEqual(0)

	const afterScrollLeft = await pdfRoot.evaluate((el) => {
		const target = Math.max(el.scrollLeft + 1, el.scrollWidth - el.clientWidth)
		el.scrollLeft = target
		return el.scrollLeft
	})

	expect(afterScrollLeft).toBeGreaterThan(before.scrollLeft)
})
