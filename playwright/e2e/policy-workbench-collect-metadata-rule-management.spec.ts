/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { clearPolicyWorkbenchRules, openPolicyWorkbenchSystemRuleEditor, waitForPolicyWorkbenchIdle } from '../support/policy-workbench-rules'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

test('collect_metadata allows creating and persisting a system rule from workbench UI', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	await page.goto('./settings/admin/libresign')

	const settingCard = await ensureCatalogSettingCardVisible(page, /Collect signer metadata/i, 'collect')
	await settingCard.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Collect signer metadata/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	await page.getByText(/Loading rules/i).waitFor({ state: 'hidden', timeout: 20000 }).catch(() => {})
	await clearPolicyWorkbenchRules(dialog)

	const createDialog = await openPolicyWorkbenchSystemRuleEditor(dialog)
	await expect(createDialog).toBeVisible({ timeout: 10000 })

	const selectMetadataOption = async (enabled: boolean) => {
		const label = enabled ? /Collect signer metadata/i : /Disable metadata collection/i
		const option = createDialog.locator('.checkbox-radio-switch').filter({ hasText: label }).first()
		await expect(option).toBeVisible({ timeout: 10_000 })
		await option.locator('.checkbox-radio-switch__content').click()
	}

	await selectMetadataOption(false)
	await selectMetadataOption(true)

	const saveResponse = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes('/apps/libresign/api/v1/policies/system/collect_metadata')
	})

	const submitButton = createDialog.getByRole('button', { name: /Create rule|Save changes/i }).first()
	await expect(submitButton).toBeEnabled({ timeout: 10000 })
	await submitButton.click()

	const response = await saveResponse
	expect(response.status()).toBe(200)

	await waitForPolicyWorkbenchIdle(page)
	await page.reload()

	const reopenedCard = await ensureCatalogSettingCardVisible(page, /Collect signer metadata/i, 'collect')
	await reopenedCard.click()
	const reopenedDialog = page.getByRole('dialog').filter({ hasText: /Collect signer metadata/i }).first()
	await expect(reopenedDialog).toBeVisible({ timeout: 10000 })
	await expect(reopenedDialog.getByRole('button', { name: /^Change$/i }).first()).toBeVisible({ timeout: 10000 })
})
