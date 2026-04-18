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

test('signature footer template editor updates preview and controls correctly', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')

	const addFooterSwitch = page.locator('.checkbox-radio-switch').filter({ hasText: /Add visible footer with signature details/i }).first()
	await expect(addFooterSwitch).toBeVisible({ timeout: 20000 })

	const customizeSwitch = page.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i }).first()
	let customizeVisible = await customizeSwitch.isVisible().catch(() => false)
	if (!customizeVisible) {
		await addFooterSwitch.click()
		customizeVisible = await customizeSwitch.isVisible().catch(() => false)
	}
	test.skip(!customizeVisible, 'Customize footer template control is not available in this environment.')
	await customizeSwitch.click()

	const editorSection = page.locator('.footer-template-section').first()
	await expect(editorSection).toBeVisible({ timeout: 20000 })

	const templateEditor = editorSection.getByRole('textbox', { name: 'Footer template' }).first()
	const initialTemplate = `<div>Playwright bootstrap ${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await templateEditor.click()
		await templateEditor.press('Control+a')
		await templateEditor.fill(initialTemplate)
	})

	await expect(editorSection.locator('.footer-preview')).toBeVisible({ timeout: 15000 })
	await expect(editorSection.locator('.footer-preview__loading')).toBeHidden({ timeout: 15000 })
	await expect(editorSection.getByText(/Page 1 of 1\./i)).toBeVisible({ timeout: 15000 })

	const zoomField = editorSection.getByRole('spinbutton', { name: 'Zoom level' }).first()
	await expect(zoomField).toHaveValue('100')

	await editorSection.getByRole('button', { name: 'Increase zoom level' }).click()
	await expect(zoomField).toHaveValue('110')

	await editorSection.getByRole('button', { name: 'Decrease zoom level' }).click()
	await expect(zoomField).toHaveValue('100')

	await zoomField.fill('140')
	await zoomField.press('Tab')
	await expect(zoomField).toHaveValue('140')

	const widthField = editorSection.getByRole('spinbutton', { name: 'Width' }).first()
	const widthPayload = await waitForFooterTemplateRequest(page, async () => {
		await widthField.fill('620')
		await widthField.press('Tab')
	})
	await expect(widthField).toHaveValue('620')
	await expect(widthPayload.width).toBe(620)

	const heightField = editorSection.getByRole('spinbutton', { name: 'Height' }).first()
	const heightPayload = await waitForFooterTemplateRequest(page, async () => {
		await heightField.fill('130')
		await heightField.press('Tab')
	})
	await expect(heightField).toHaveValue('130')
	await expect(heightPayload.height).toBe(130)

	const uniqueTemplate = `<div>Playwright footer ${Date.now()}</div>`
	const templatePayload = await waitForFooterTemplateRequest(page, async () => {
		await templateEditor.click()
		await templateEditor.press('Control+a')
		await templateEditor.fill(uniqueTemplate)
	})
	await expect(templatePayload.template).toContain('Playwright footer')
	await expect(editorSection.locator('.footer-preview__loading')).toBeHidden({ timeout: 15000 })
	await expect(editorSection.getByText(/Page 1 of 1\./i)).toBeVisible({ timeout: 15000 })
})
