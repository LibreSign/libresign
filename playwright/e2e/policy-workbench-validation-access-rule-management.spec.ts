/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { waitForPolicyWorkbenchIdle } from '../support/policy-workbench-rules'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

test('make_validation_url_private allows creating and persisting a system rule from workbench UI', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	await page.goto('./settings/admin/libresign')

	const settingCard = await ensureCatalogSettingCardVisible(page, /Validation page access/i, 'validation')
	await settingCard.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Validation page access/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	await page.getByText(/Loading rules/i).waitFor({ state: 'hidden', timeout: 20000 }).catch(() => {})

	const createRuleButton = page.getByRole('button', { name: /Create rule/i }).first()
	await expect(createRuleButton).toBeVisible({ timeout: 10000 })
	await createRuleButton.click()

	const createScopeDialog = page.getByRole('dialog').filter({ hasText: /What do you want to create\?/i }).last()
	if (await createScopeDialog.isVisible().catch(() => false)) {
		await createScopeDialog.getByRole('option', { name: /^Everyone\b/i }).first().click()
	}

	const createDialog = page.getByRole('dialog', { name: /Create rule/i }).last()
	await expect(createDialog).toBeVisible({ timeout: 10000 })

	const authenticatedOnly = createDialog.getByRole('radio', { name: /Authenticated[- ]only/i }).first()
	if (await authenticatedOnly.isVisible().catch(() => false)) {
		await authenticatedOnly.click({ force: true })
		await expect(authenticatedOnly).toBeChecked({ timeout: 5000 })
	}

	const saveResponse = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes('/apps/libresign/api/v1/policies/system/make_validation_url_private')
	})

	const submitButton = createDialog.getByRole('button', { name: /Create rule|Save changes/i }).first()
	await expect(submitButton).toBeEnabled({ timeout: 10000 })
	await submitButton.click()

	const response = await saveResponse
	expect(response.status()).toBe(200)

	await waitForPolicyWorkbenchIdle(page)
	await page.reload()

	const reopenedCard = await ensureCatalogSettingCardVisible(page, /Validation page access/i, 'validation')
	await reopenedCard.click()
	const reopenedDialog = page.getByRole('dialog').filter({ hasText: /Validation page access/i }).first()
	await expect(reopenedDialog).toBeVisible({ timeout: 10000 })
	await expect(reopenedDialog.getByRole('button', { name: /^Change$/i }).first()).toBeVisible({ timeout: 10000 })
})
