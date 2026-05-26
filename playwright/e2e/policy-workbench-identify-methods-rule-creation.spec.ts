/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'
import { randomBytes } from 'node:crypto'

import { ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { login } from '../support/nc-login'
import {
	deleteGroup,
	deleteUser,
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
	setSystemPolicy,
	setUserLanguage,
} from '../support/nc-provisioning'
import { clearPolicyWorkbenchRules } from '../support/policy-workbench-rules'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

const TEST_NAMESPACE = randomBytes(4).toString('hex')
const GROUP_ID = `libresign-identify-rule-group-${TEST_NAMESPACE}`
const USER_ID = `identifyruleuser-${TEST_NAMESPACE}`
const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || process.env.NEXTCLOUD_ADMIN_PASSWORD || 'admin'

test.afterEach(async ({ request }) => {
	await deleteUser(request, USER_ID, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(request, GROUP_ID, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
})

async function openIdentificationFactorsDialog(page: Page): Promise<Locator> {
	await page.goto('./settings/admin/libresign')

	const card = await ensureCatalogSettingCardVisible(page, /Identification factors/i, 'identification')
	await card.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Identification factors/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	return dialog
}

async function openScopeRuleEditor(page: Page, _dialog: Locator, scope: 'everyone' | 'group' | 'user'): Promise<Locator> {
	const activeDialog = await openIdentificationFactorsDialog(page)

	if (scope === 'everyone') {
		const changeButton = activeDialog.getByRole('button', { name: /^Change$/i }).first()
		if (await changeButton.isVisible({ timeout: 3000 }).catch(() => false)) {
			await changeButton.click()
		} else {
			await activeDialog.getByRole('button', { name: /Create rule|Create policy rule/i }).first().click()
			const everyoneOption = page.locator('[role="option"]').filter({ hasText: /Everyone/i }).first()
			if (await everyoneOption.isVisible({ timeout: 3000 }).catch(() => false)) {
				await everyoneOption.click()
			}
		}
	} else {
		const createRuleButton = activeDialog.getByRole('button', { name: /Create rule|Create policy rule/i }).first()
		const canOpenCreateScope = await createRuleButton.isVisible({ timeout: 2000 }).catch(() => false)
			&& await createRuleButton.isEnabled().catch(() => false)

		if (canOpenCreateScope) {
			await createRuleButton.click()
			const targetOption = page.locator('[role="option"]').filter({ hasText: scope === 'group' ? /Group/i : /Account/i }).first()
			await expect(targetOption).toBeVisible({ timeout: 5000 })
			await targetOption.click()
		} else {
			const scopeRow = activeDialog.locator('tbody tr').filter({ hasText: scope === 'group' ? /Group/i : /Account/i }).first()
			await expect(scopeRow).toBeVisible({ timeout: 10000 })
			await scopeRow.getByRole('button', { name: /^Change$/i }).first().click()
		}
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
	const adminUser = ADMIN_USER
	const adminPassword = ADMIN_PASSWORD

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await ensureUserExists(page.request, USER_ID, '123456')
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, USER_ID, GROUP_ID)
	await setSystemPolicy(page.request, 'identify_methods', JSON.stringify([
		{ name: 'account', enabled: true, requirement: 'required' },
		{ name: 'email', enabled: true, requirement: 'optional' },
	]))

	const dialog = await openIdentificationFactorsDialog(page)
	await clearPolicyWorkbenchRules(dialog)

	const everyoneRuleDialog = await openScopeRuleEditor(page, dialog, 'everyone')
	await assertIdentifyMethodsAreAvailable(everyoneRuleDialog)
	everyoneRuleDialog.getByRole('button', { name: /Cancel/i }).click()

	const groupRuleDialog = await openScopeRuleEditor(page, dialog, 'group')
	await assertIdentifyMethodsAreAvailable(groupRuleDialog)
	await groupRuleDialog.getByRole('button', { name: /Cancel/i }).click()

	const userRuleDialog = await openScopeRuleEditor(page, dialog, 'user')
	await assertIdentifyMethodsAreAvailable(userRuleDialog)
})
