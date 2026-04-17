/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import { login } from '../support/nc-login'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

async function waitForFooterTemplateRequest(page: Page, action: () => Promise<void>) {
	const requestPromise = page.waitForRequest((request) => {
		return request.method() === 'POST'
			&& request.url().includes('/apps/libresign/api/v1/admin/footer-template')
	})

	await action()
	const request = await requestPromise
	return request.postDataJSON() as {
		template: string
		width: number
		height: number
	}
}

test('footer template persists after reset and page reload', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')

	const addFooterSwitch = page.locator('.checkbox-radio-switch').filter({ hasText: /Add visible footer/i }).first()
	await expect(addFooterSwitch).toBeVisible({ timeout: 20000 })

	const customizeSwitch = page.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i }).first()

	// Make sure customize is available
	const customizeAvailable = await customizeSwitch.isVisible({ timeout: 5000 }).catch(() => false)
	if (!customizeAvailable) {
		return
	}

	// Enable customize
	const isChecked = await customizeSwitch.evaluate(el => (el as HTMLInputElement).checked)
	if (!isChecked) {
		await customizeSwitch.click()
	}

	const editorSection = page.locator('.footer-template-section').first()
	const templateEditor = editorSection.getByRole('textbox', { name: 'Footer template' }).first()

	// Save custom template
	const customTemplate = `<div>E2E_TEST_${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await templateEditor.click()
		await templateEditor.press('Control+a')
		await templateEditor.fill(customTemplate)
	})

	// Click reset
	const resetButton = editorSection.getByRole('button', { name: 'Reset to default' })
	await resetButton.click()
	await page.waitForRequest((request) => {
		return request.method() === 'POST'
			&& request.url().includes('/apps/libresign/api/v1/admin/footer-template')
	})

	// Verify template is empty after reset
	const resetTemplate = await templateEditor.inputValue()
	await expect(resetTemplate).toBe('')

	// Reload and verify state persists
	await page.reload()
	await expect(editorSection).toBeVisible({ timeout: 20000 })

	// After reload, customize should be OFF
	const customizeAfterReload = page.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i }).first()
	const customizeCheckbox = customizeAfterReload.locator('input[type="checkbox"]').first()
	await expect(customizeCheckbox).not.toBeChecked()

	// Template should still be empty
	const templateAfterReload = await templateEditor.inputValue().catch(() => '')
	await expect(templateAfterReload).toBe('')
})
