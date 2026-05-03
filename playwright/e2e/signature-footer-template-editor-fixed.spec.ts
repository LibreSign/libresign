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

test('signature footer template editor updates preview and controls correctly', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	const ruleDialog = await openSystemFooterRuleEditor(page)
	await ensureFooterTemplateEnabled(ruleDialog)

	const templateEditor = ruleDialog.locator('.code-editor .cm-content[contenteditable="true"]').first()
	const initialTemplate = `<div>Playwright bootstrap ${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await fillTemplateEditor(ruleDialog, initialTemplate)
	})
	await expect(templateEditor).toContainText('Playwright bootstrap')

	const previewSection = ruleDialog.locator('.signature-footer-rule-editor__preview').first()
	await expect(previewSection).toBeVisible({ timeout: 15000 })
	await expect(previewSection.getByText(/Preview/i)).toBeVisible({ timeout: 15000 })

	const zoomField = ruleDialog.getByRole('spinbutton', { name: 'Zoom level' }).first()
	await expect(zoomField).toHaveValue('100')

	await ruleDialog.getByRole('button', { name: 'Increase zoom level' }).click()
	await expect(zoomField).toHaveValue('110')

	await ruleDialog.getByRole('button', { name: 'Decrease zoom level' }).click()
	await expect(zoomField).toHaveValue('100')

	await zoomField.fill('140')
	await zoomField.press('Tab')
	await expect(zoomField).toHaveValue('140')

	const widthField = ruleDialog.getByRole('spinbutton', { name: 'Width' }).first()
	const widthPayload = await waitForFooterTemplateRequest(page, async () => {
		await widthField.fill('620')
		await widthField.press('Tab')
	})
	await expect(widthField).toHaveValue('620')
	await expect(widthPayload.width).toBe(620)

	const heightField = ruleDialog.getByRole('spinbutton', { name: 'Height' }).first()
	const heightPayload = await waitForFooterTemplateRequest(page, async () => {
		await heightField.fill('130')
		await heightField.press('Tab')
	})
	await expect(heightField).toHaveValue('130')
	await expect(heightPayload.height).toBe(130)

	const uniqueTemplate = `<div>Playwright footer ${Date.now()}</div>`
	const templatePayload = await waitForFooterTemplateRequest(page, async () => {
		await fillTemplateEditor(ruleDialog, uniqueTemplate)
	})
	await expect(templatePayload.template).toContain('Playwright footer')
	await expect(previewSection.locator('.pdf-elements-root')).toBeVisible({ timeout: 15000 })
})

test('footer template reset removes customization after page reload', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)
	let ruleDialog = await openSystemFooterRuleEditor(page)
	await ensureFooterTemplateEnabled(ruleDialog)

	const templateEditor = ruleDialog.locator('.code-editor .cm-content[contenteditable="true"]').first()
	const customTemplate = `<div style="color:red">Reset test ${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await fillTemplateEditor(ruleDialog, customTemplate)
	})
	await expect(templateEditor).toContainText('Reset test')

	const previewSection = ruleDialog.locator('.signature-footer-rule-editor__preview').first()
	await expect(previewSection).toBeVisible({ timeout: 15000 })

	const resetButton = ruleDialog.getByRole('button', { name: /Reset template to inherited default/i }).first()
	await expect(resetButton).toBeVisible({ timeout: 10000 })
	await waitForFooterTemplateRequest(page, async () => {
		await resetButton.click()
	})
	await saveRule(page, ruleDialog)

	await page.reload()
	ruleDialog = await openSystemFooterRuleEditor(page)
	await ensureFooterTemplateEnabled(ruleDialog)
	const templateAfterReload = ruleDialog.locator('.code-editor .cm-content[contenteditable="true"]').first()
	await expect(templateAfterReload).toBeVisible({ timeout: 10000 })
	await expect(templateAfterReload).not.toContainText('Reset test')
})
