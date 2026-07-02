/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import { setUserLanguage } from '../support/nc-provisioning'
import { openPolicyWorkbenchSystemRuleEditor } from '../support/policy-workbench-rules'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

async function openSignatureProcessingEditor(page: Page) {
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
	await searchField.fill('Signature processing')

	const settingCard = page.locator('article').filter({ hasText: /Signature processing/i }).first()
	await expect(settingCard).toBeVisible({ timeout: 15000 })

	await settingCard.getByRole('button', { name: /^Configure(?: setting)?$/i }).click()

	const policyDialog = page.locator('div[role="dialog"]').filter({ hasText: /Signature processing/i }).first()
	await expect(policyDialog).toBeVisible({ timeout: 10000 })

	return openPolicyWorkbenchSystemRuleEditor(policyDialog)
}

test('signature processing policy progressively reveals background infrastructure', async ({ page }) => {
	const editorDialog = await openSignatureProcessingEditor(page)

	await expect(editorDialog).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.getByText('Worker service', { exact: true })).toHaveCount(0)
	await expect(editorDialog.locator('input[id="signing-mode-parallel-input"]')).toHaveCount(0)

	await editorDialog.getByText('Process in background', { exact: true }).first().click()
	await expect(editorDialog.getByText('Worker service', { exact: true })).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.locator('.signing-mode-rule-editor__local-config')).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.locator('.signing-mode-rule-editor__parallel-label')).toHaveText('Concurrent jobs')
	await expect(editorDialog.locator('input[id="signing-mode-parallel-input"]')).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.getByText('Maximum concurrent signing jobs.', { exact: true })).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.getByText('workers', { exact: true })).toHaveCount(0)

	await editorDialog.getByText('External worker', { exact: true }).first().click()
	await expect(editorDialog.locator('.signing-mode-rule-editor__local-config')).toHaveCount(0)
	await expect(editorDialog.locator('input[id="signing-mode-parallel-input"]')).toHaveCount(0)
	await expect(editorDialog.getByText('Concurrent jobs', { exact: true })).toHaveCount(0)
	await expect(editorDialog.getByText('Maximum concurrent signing jobs.', { exact: true })).toHaveCount(0)

	await editorDialog.getByText('Local worker', { exact: true }).first().click()
	await expect(editorDialog.locator('input[id="signing-mode-parallel-input"]')).toBeVisible({ timeout: 10000 })
})

test('signature processing editor remains compact on mobile and dark color scheme', async ({ page }) => {
	await page.emulateMedia({ colorScheme: 'dark' })
	await page.setViewportSize({ width: 390, height: 844 })

	const editorDialog = await openSignatureProcessingEditor(page)
	await expect(editorDialog).toBeVisible({ timeout: 10000 })

	await editorDialog.getByText('Process in background', { exact: true }).first().click()
	await expect(editorDialog.getByText('Worker service', { exact: true })).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.locator('.signing-mode-rule-editor__local-config')).toBeVisible({ timeout: 10000 })
	await expect(editorDialog.locator('input[id="signing-mode-parallel-input"]')).toBeVisible({ timeout: 10000 })
	const infrastructureSection = editorDialog.locator('.signing-mode-rule-editor__infrastructure')
	await expect(infrastructureSection).toBeVisible({ timeout: 10000 })
	const localInfrastructureHeight = await infrastructureSection.evaluate((element) => element.getBoundingClientRect().height)

	await editorDialog.getByText('External worker', { exact: true }).first().click()
	await expect(editorDialog.locator('input[id="signing-mode-parallel-input"]')).toHaveCount(0)
	const externalInfrastructureHeight = await infrastructureSection.evaluate((element) => element.getBoundingClientRect().height)
	const dialogHeight = await editorDialog.evaluate((element) => element.getBoundingClientRect().height)

	expect(externalInfrastructureHeight).toBeLessThan(localInfrastructureHeight)
	expect(dialogHeight).toBeLessThanOrEqual(844)
	await expect(editorDialog.getByRole('button', { name: /^Create rule$/i })).toBeVisible({ timeout: 10000 })
})
