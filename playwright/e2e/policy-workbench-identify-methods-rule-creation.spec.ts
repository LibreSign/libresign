/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'

import { ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { login } from '../support/nc-login'
import {
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
	setSystemPolicy,
	setUserLanguage,
} from '../support/nc-provisioning'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

const GROUP_ID = 'libresign-identify-rule-group'
const USER_ID = 'identifyruleuser'

async function openIdentificationFactorsDialog(page: Page): Promise<Locator> {
	await page.goto('./settings/admin/libresign')

	const card = await ensureCatalogSettingCardVisible(page, /Identification factors/i, 'identification')
	await card.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Identification factors/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	return dialog
}

async function openScopeRuleEditor(page: Page, dialog: Locator, scope: 'everyone' | 'group' | 'user'): Promise<Locator> {
	if (scope === 'everyone') {
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
	} else {
		await dialog.getByRole('button', { name: /Create rule/i }).first().click()
		const targetOption = page.locator('[role="option"]').filter({ hasText: scope === 'group' ? /Group/i : /User/i }).first()
		await expect(targetOption).toBeVisible({ timeout: 5000 })
		await targetOption.click()
	}

	const ruleDialog = page.getByRole('dialog', { name: /Edit rule|Create rule/i }).last()
	await expect(ruleDialog).toBeVisible({ timeout: 10000 })
	return ruleDialog
}

async function assertIdentifyMethodsAreAvailable(ruleDialog: Locator): Promise<void> {
	await expect(ruleDialog.getByText('No identification methods available.')).toHaveCount(0)
	const factors = ruleDialog.locator('.identify-methods-editor__method')
	await expect(factors.first()).toBeVisible({ timeout: 10000 })
	await expect.poll(async () => factors.count(), {
		timeout: 10000,
		message: 'Expected at least two identify method entries in rule editor',
	}).toBeGreaterThanOrEqual(2)
	await expect(ruleDialog.getByText(/Account|Email/i).first()).toBeVisible({ timeout: 10000 })
}

test('identification factors rule editor shows available methods for everyone, group and user scopes', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await ensureUserExists(page.request, USER_ID, '123456')
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, USER_ID, GROUP_ID)

	// Regression setup: explicit empty payload must no longer break rule creation.
	await setSystemPolicy(page.request, 'identify_methods', '[]')

	const dialog = await openIdentificationFactorsDialog(page)

	const everyoneRuleDialog = await openScopeRuleEditor(page, dialog, 'everyone')
	await assertIdentifyMethodsAreAvailable(everyoneRuleDialog)
	await everyoneRuleDialog.getByRole('button', { name: /Cancel/i }).click()

	const groupRuleDialog = await openScopeRuleEditor(page, dialog, 'group')
	await assertIdentifyMethodsAreAvailable(groupRuleDialog)
	await groupRuleDialog.getByRole('button', { name: /Cancel/i }).click()

	const userRuleDialog = await openScopeRuleEditor(page, dialog, 'user')
	await assertIdentifyMethodsAreAvailable(userRuleDialog)
})
