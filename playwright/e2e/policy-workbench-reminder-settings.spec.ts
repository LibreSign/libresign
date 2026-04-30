/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { login } from '../support/nc-login'
import { setUserLanguage } from '../support/nc-provisioning'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

test('admin can open reminder settings from policy workbench', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	await login(
		page.request,
		adminUser,
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 10000 })
	await searchField.fill('Automatic reminders')

	const reminderCard = page.locator('article').filter({ hasText: /Automatic reminders/i }).first()
	await expect(reminderCard).toBeVisible({ timeout: 15000 })

	await reminderCard.getByRole('button', { name: /^Configure(?: setting)?$/i }).click()

	const reminderDialog = page.locator('div[role="dialog"]').filter({ hasText: 'Automatic reminders' }).first()
	await expect(reminderDialog).toBeVisible({ timeout: 10000 })

	const changeButton = reminderDialog.getByRole('button', { name: /^Change$/i }).first()
	await expect(changeButton).toBeVisible({ timeout: 10000 })
	await changeButton.click()

	const createRuleDialog = page.getByRole('dialog', { name: /Create rule/i }).last()
	await expect(createRuleDialog).toBeVisible({ timeout: 10000 })
	await expect(createRuleDialog.getByText('Enable automatic reminders', { exact: true })).toBeVisible({ timeout: 10000 })
})
