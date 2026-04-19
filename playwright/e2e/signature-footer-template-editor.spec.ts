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

test('footer template persists customization after reset and reload', async ({ page }) => {
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

	// Enable customize footer template
	const isChecked = await customizeSwitch.evaluate(el => (el as HTMLInputElement).checked)
	if (!isChecked) {
		await customizeSwitch.click()
	}

	const editorSection = page.locator('.footer-template-section').first()
	await expect(editorSection).toBeVisible({ timeout: 20000 })

	const templateEditor = editorSection.getByRole('textbox', { name: 'Footer template' }).first()
	const customTemplate = `<div style="color:red">Reset test ${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await templateEditor.click()
		await templateEditor.press('Control+a')
		await templateEditor.fill(customTemplate)
	})

	await expect(editorSection.locator('.footer-preview')).toBeVisible({ timeout: 15000 })
	await expect(editorSection.locator('.footer-preview__loading')).toBeHidden({ timeout: 15000 })

	// Click reset button
	const resetButton = editorSection.getByRole('button', { name: 'Reset to default' })
	await expect(resetButton).toBeVisible({ timeout: 10000 })

	const resetRequestPromise = page.waitForRequest((request) => {
		return request.method() === 'POST'
			&& request.url().includes('/apps/libresign/api/v1/admin/footer-template')
	})
	await resetButton.click()
	await resetRequestPromise

	// Template should be cleared/reset after clicking reset button
	const resetTemplate = await templateEditor.inputValue()
	await expect(resetTemplate).toBe('')

	// Reload the page to verify reset persists
	await page.reload()
	await expect(editorSection).toBeVisible({ timeout: 20000 })

	// After reload, customize checkbox should be unchecked (reset state)
	const customizeAfterReload = page.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i }).first()
	const customizeCheckedAfterReload = await customizeAfterReload.evaluate(el => (el as HTMLInputElement).checked)
	await expect(customizeCheckedAfterReload).toBe(false)

	// Template editor should be empty or hidden
	const templateAfterReload = await templateEditor.inputValue().catch(() => '')
	await expect(templateAfterReload).toBe('')
})

test('footer template reset reverts to default after page reload', async ({ page }) => {
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

	// Step 1: Enable customize and save a custom template
	const isChecked = await customizeSwitch.evaluate(el => (el as HTMLInputElement).checked)
	if (!isChecked) {
		await customizeSwitch.click()
	}

	const editorSection = page.locator('.footer-template-section').first()
	await expect(editorSection).toBeVisible({ timeout: 20000 })

	const templateEditor = editorSection.getByRole('textbox', { name: 'Footer template' }).first()
	const customTemplate = `<div>CUSTOM_${Date.now()}</div>`
	await waitForFooterTemplateRequest(page, async () => {
		await templateEditor.click()
		await templateEditor.press('Control+a')
		await templateEditor.fill(customTemplate)
	})

	// Verify template was saved
	await expect(editorSection.locator('.footer-preview__loading')).toBeHidden({ timeout: 15000 })

	// Step 2: Reset
	const resetButton = editorSection.getByRole('button', { name: 'Reset to default' })
	await resetButton.click()
	const resetRequest = await page.waitForRequest((request) => {
		return request.method() === 'POST'
			&& request.url().includes('/apps/libresign/api/v1/admin/footer-template')
	})

	// Step 3: Reload and verify state
	await page.reload()
	await expect(editorSection).toBeVisible({ timeout: 20000 })

	// Customize should be OFF after reload
	const customizeAfterReload = page.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i }).first()
	const customizeCheckbox = customizeAfterReload.locator('input[type="checkbox"]').first()
	await expect(customizeCheckbox).not.toBeChecked()

	// Step 4: Enable customize again
	await customizeAfterReload.click()

	// Step 5: Verify template is back to default (empty)
	const templateAgain = await templateEditor.inputValue()
	await expect(templateAgain).toBe('')
})
