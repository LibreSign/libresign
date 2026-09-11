/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { clearPolicyWorkbenchRules, openPolicyWorkbenchSystemRuleEditor, waitForPolicyWorkbenchIdle } from '../support/policy-workbench-rules'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

test('identification_documents allows creating and persisting a system rule from workbench UI', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	await page.goto('./settings/admin/libresign')

	const settingCard = await ensureCatalogSettingCardVisible(page, /Identification documents flow/i, 'identification')
	await settingCard.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Identification documents flow/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	await page.getByText(/Loading rules/i).waitFor({ state: 'hidden', timeout: 20000 }).catch(() => {})
	await clearPolicyWorkbenchRules(dialog)

	const createDialog = await openPolicyWorkbenchSystemRuleEditor(dialog)
	await expect(createDialog).toBeVisible({ timeout: 10000 })

	const enableOption = createDialog.locator('.checkbox-radio-switch').filter({ hasText: /Enable identification documents flow/i }).first()
	const disableOption = createDialog.locator('.checkbox-radio-switch').filter({ hasText: /Disable identification documents flow/i }).first()
	if (await enableOption.isVisible().catch(() => false)) {
		if (await disableOption.isVisible().catch(() => false)) {
			await disableOption.locator('.checkbox-radio-switch__content').click()
		}
		await enableOption.locator('.checkbox-radio-switch__content').click()
	} else {
		await createDialog.getByText('Enable identification documents flow', { exact: true }).first().click()
	}

	const submitButton = createDialog.getByRole('button', { name: /Create rule|Save changes/i }).first()
	await expect(submitButton).toBeEnabled({ timeout: 10000 })
	const [response] = await Promise.all([
		page.waitForResponse((response) => {
			return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
				&& response.url().includes('/apps/libresign/api/v1/policies/system/identification_documents')
		}),
		submitButton.click(),
	])
	expect(response.status()).toBe(200)

	await waitForPolicyWorkbenchIdle(page)
	await page.reload()

	const reopenedCard = await ensureCatalogSettingCardVisible(page, /Identification documents flow/i, 'identification')
	await reopenedCard.click()
	const reopenedDialog = page.getByRole('dialog').filter({ hasText: /Identification documents flow/i }).first()
	await expect(reopenedDialog).toBeVisible({ timeout: 10000 })
	await expect(reopenedDialog.getByRole('button', { name: /^Change$/i }).first()).toBeVisible({ timeout: 10000 })
})
