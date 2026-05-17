/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'
import {
	bootstrapLibreSignAdmin,
	ensureFooterTemplateEnabled,
	fillTemplateEditor,
	openSystemFooterRuleEditor,
} from '../support/footer-policy-workbench'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

async function waitForFooterTemplateRequest(page: Page, action: () => Promise<void>) {
	const requestPromise = page.waitForRequest((request) => {
		return request.method() === 'POST' && request.url().includes('/admin/footer-template/preview-pdf')
	})

	await action()
	const request = await requestPromise
	return request.postDataJSON() as {
		template: string
		width: number
		height: number
	}
}

async function saveRule(page: Page, ruleDialog: Locator): Promise<void> {
	const saveButton = ruleDialog.getByRole('button', { name: /Create rule|Save changes|Save policy rule changes|Save rule changes/i }).last()
	await expect(saveButton).toBeVisible({ timeout: 10000 })
	await expect(saveButton).toBeEnabled({ timeout: 10000 })
	const saveResponsePromise = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes('/apps/libresign/api/v1/policies/system/add_footer')
	})
	await saveButton.click()
	const saveResponse = await saveResponsePromise
	await expect(saveResponse.status()).toBe(200)
}

test('footer template persists after reset and page reload', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	let ruleDialog = await openSystemFooterRuleEditor(page)
	await ensureFooterTemplateEnabled(ruleDialog)
	const templateEditor = ruleDialog.locator('.code-editor .cm-content[contenteditable="true"]').first()

	// Save custom template
	const customTemplate = `<div>E2E_TEST_${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await fillTemplateEditor(ruleDialog, customTemplate)
	})
	await expect(templateEditor).toContainText('E2E_TEST_')

	// Click reset template to inherited default
	const resetButton = ruleDialog.getByRole('button', { name: /Reset template to inherited default/i }).first()
	await expect(resetButton).toBeVisible({ timeout: 10000 })
	await waitForFooterTemplateRequest(page, async () => {
		await resetButton.click()
	})

	// Persist rule and verify reset survives reload
	await saveRule(page, ruleDialog)

	await page.reload()
	ruleDialog = await openSystemFooterRuleEditor(page)
	await ensureFooterTemplateEnabled(ruleDialog)
	const templateAfterReload = ruleDialog.locator('.code-editor .cm-content[contenteditable="true"]').first()
	await expect(templateAfterReload).toBeVisible({ timeout: 10000 })
	await expect(templateAfterReload).not.toContainText('E2E_TEST_')
})
