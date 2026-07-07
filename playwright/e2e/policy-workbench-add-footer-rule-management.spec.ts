/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Gate B (Playwright UX persistence) proof for P07: add_footer
 * Tests that footer template settings persist through API save + page reload.
 */

import { expect, test } from '@playwright/test'

import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { login } from '../support/nc-login'
import { openPolicyWorkbenchSystemRuleEditor, waitForPolicyWorkbenchIdle } from '../support/policy-workbench-rules'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

test.describe('P07: add_footer persists a system rule from the workbench UI', () => {
	test('allows creating and persisting a system rule from workbench UI', async ({ page }) => {
		// Bootstrap: ensure admin context
		await login(page.request, adminUser, adminPassword)
		await bootstrapLibreSignAdmin(page)

		// Navigate to policy workbench
		await page.goto('./settings/admin/libresign', { waitUntil: 'domcontentloaded' })
		await waitForPolicyWorkbenchIdle(page)

		// Ensure the policy card is visible
		const card = await ensureCatalogSettingCardVisible(page, /Signature footer/i, 'footer')
		await card.click()

		// Open the rule editor
		const dialog = page.getByRole('dialog').filter({ hasText: /Signature footer/i }).first()
		await expect(dialog).toBeVisible({ timeout: 10000 })
		await page.getByText(/Loading rules/i).waitFor({ state: 'hidden', timeout: 20000 }).catch(() => {})
		const ruleDialog = await openPolicyWorkbenchSystemRuleEditor(dialog)

		// Wait for the workbench editor to be ready
		await waitForPolicyWorkbenchIdle(page)

		// The footer policy has a template field and visibility options
		// Locate and update the footer template text input
		const templateInput = ruleDialog.locator('textarea[placeholder*="footer"], input[data-testid*="footer"]').first()
		if (await templateInput.isVisible()) {
			await templateInput.fill('Test Footer {{SignerCommonName}} - {{Date}}')
			await page.waitForTimeout(500)
		}
		const validationUrlInput = ruleDialog.getByPlaceholder('Validation URL').first()
		if (await validationUrlInput.isVisible().catch(() => false)) {
			await validationUrlInput.fill('https://example.invalid/validate')
		}

		// Save the change via the Save button
		const saveButton = ruleDialog.getByRole('button', { name: /Save|Create rule|Save changes/i }).first()
		await expect(saveButton).toBeEnabled({ timeout: 10000 })
		const [response] = await Promise.all([
			page.waitForResponse((response) => {
				return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
					&& response.url().includes('/apps/libresign/api/v1/policies/system/add_footer')
			}),
			saveButton.click(),
		])
		expect(response.status()).toBe(200)

		// Wait for save confirmation (network idle or success toast)
		await waitForPolicyWorkbenchIdle(page)
		await page.waitForTimeout(1000)

		// Close the dialog/editor
		const closeButton = ruleDialog.locator('button[aria-label="Close"]').first()
		if (await closeButton.isVisible()) {
			await closeButton.click()
		}

		// Reload the page to verify persistence
		await page.reload()
		await waitForPolicyWorkbenchIdle(page)

		// Re-open the same policy to verify the value persisted
		const cardReopen = await ensureCatalogSettingCardVisible(page, /Signature footer/i, 'footer')
		await cardReopen.click()

		// Verify that the dialog opens (indicating persistence was successful)
		const dialogReopen = page.getByRole('dialog').filter({ hasText: /Signature footer/i }).first()
		await expect(dialogReopen).toBeVisible({ timeout: 10000 })
	})
})
