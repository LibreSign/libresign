/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'

import { ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { login } from '../support/nc-login'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

async function openLegalInformationDialog(page: Page): Promise<Locator> {
	await page.goto('./settings/admin/libresign')

	const card = await ensureCatalogSettingCardVisible(page, /Legal information/i, 'legal')
	await card.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Legal information/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	return dialog
}

async function openScopeRuleEditor(page: Page, dialog: Locator): Promise<Locator> {
	const changeButton = dialog.getByRole('button', { name: /^Change$/i }).first()
	if (await changeButton.isVisible({ timeout: 3000 }).catch(() => false)) {
		await changeButton.click()
	} else {
		await dialog.getByRole('button', { name: /Create rule/i }).first().click()
		const everyoneOption = page.locator('[role="option"]').filter({ hasText: /Everyone/i }).first()
		if (await everyoneOption.isVisible({ timeout: 3000 }).catch(() => false)) {
			await everyoneOption.click()
		}
	}

	const ruleDialog = page.getByRole('dialog', { name: /Edit rule|Create rule/i }).last()
	await expect(ruleDialog).toBeVisible({ timeout: 10000 })
	return ruleDialog
}

test('legal information heading menu shows visible H1-H6 labels with text', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)

	const dialog = await openLegalInformationDialog(page)
	const ruleDialog = await openScopeRuleEditor(page, dialog)

	const headingToggle = ruleDialog.getByRole('button', { name: /Heading style|H/i }).first()
	await expect(headingToggle).toBeVisible({ timeout: 10000 })
	await headingToggle.click()

	const menu = page.getByRole('menu')
	await expect(menu).toBeVisible({ timeout: 10000 })

	const toggleBox = await headingToggle.boundingBox()
	const menuBox = await menu.boundingBox()
	expect(toggleBox).not.toBeNull()
	expect(menuBox).not.toBeNull()
	expect(menuBox!.y).toBeGreaterThanOrEqual(toggleBox!.y + toggleBox!.height)
	expect(menuBox!.x).toBeGreaterThanOrEqual(toggleBox!.x - 1)

	const paragraph = menu.getByRole('menuitem', { name: /^Paragraph$/i }).first()
	await expect(paragraph).toBeVisible({ timeout: 10000 })

	const paragraphFontSize = await paragraph.evaluate((element) => getComputedStyle(element).fontSize)
	expect(paragraphFontSize).toBeTruthy()

	for (let level = 1; level <= 6; level++) {
		const row = menu.getByRole('menuitem', { name: new RegExp(`^H${level}\\s+Heading\\s+${level}$`, 'i') }).first()
		await expect(row).toBeVisible({ timeout: 10000 })
		const fontSize = await row.evaluate((element) => getComputedStyle(element).fontSize)
		expect(fontSize).toBeTruthy()
	}
})
