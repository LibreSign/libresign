/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { waitForPolicyWorkbenchIdle } from '../support/policy-workbench-rules'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

test('signature_hash_algorithm allows creating and persisting a system rule from workbench UI', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	await page.goto('./settings/admin/libresign')

	const settingCard = await ensureCatalogSettingCardVisible(page, /Signature hash algorithm/i, 'hash')
	await settingCard.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Signature hash algorithm/i }).first()
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

	const algorithmCombobox = createDialog.getByRole('combobox').first()
	if (await algorithmCombobox.isVisible().catch(() => false)) {
		await algorithmCombobox.click()
		const sha512Option = page.locator('[role="option"]').filter({ hasText: /^SHA512$/i }).first()
		if (await sha512Option.isVisible().catch(() => false)) {
			await sha512Option.click()
		}
	}

	const saveResponse = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes('/apps/libresign/api/v1/policies/system/signature_hash_algorithm')
	})

	const submitButton = createDialog.getByRole('button', { name: /Create rule|Save changes/i }).first()
	await expect(submitButton).toBeEnabled({ timeout: 10000 })
	await submitButton.click()

	const response = await saveResponse
	expect(response.status()).toBe(200)

	await waitForPolicyWorkbenchIdle(page)
	await page.reload()

	const reopenedCard = await ensureCatalogSettingCardVisible(page, /Signature hash algorithm/i, 'hash')
	await reopenedCard.click()
	const reopenedDialog = page.getByRole('dialog').filter({ hasText: /Signature hash algorithm/i }).first()
	await expect(reopenedDialog).toBeVisible({ timeout: 10000 })
	await expect(reopenedDialog.getByRole('button', { name: /^Change$/i }).first()).toBeVisible({ timeout: 10000 })
})
