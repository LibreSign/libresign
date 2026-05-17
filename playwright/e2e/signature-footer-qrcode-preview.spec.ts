/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page, Request } from '@playwright/test'
import { login } from '../support/nc-login'
import { ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

const PREVIEW_URL_PATTERN = /admin\/footer-template\/preview-pdf/

async function captureNextPreviewRequest(page: Page): Promise<Request> {
	return page.waitForRequest(
		(req) => req.method() === 'POST' && PREVIEW_URL_PATTERN.test(req.url()),
		{ timeout: 15000 },
	)
}

/**
 * Click the visual toggle area of an NcCheckboxRadioSwitch.
 *
 * NcCheckboxRadioSwitch renders the interactive content in a child
 * `.checkbox-radio-switch__content` span that has `onClick: onToggle`
 * bound to it. Clicking the outer container span is unreliable because
 * events may not reach the handler; clicking the content span directly
 * is the correct approach.
 */
async function clickSwitch(switchContainer: Locator): Promise<void> {
	await switchContainer.locator('.checkbox-radio-switch__content').click()
}

async function openFooterPolicyEditor(page: Page) {
	await page.goto('./settings/admin/libresign')

	const footerCard = await ensureCatalogSettingCardVisible(page, /Signature footer/i, 'footer')
	await footerCard.click()

	// Expect the footer settings dialog to appear
	const dialog = page.getByRole('dialog').filter({ hasText: /Signature footer/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })

	return dialog
}

async function clickChangeOrCreateRule(dialog: ReturnType<Page['getByRole']>) {
	const changeBtn = dialog.getByRole('button', { name: /^Change$/i }).first()
	if (await changeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
		await changeBtn.click()
	} else {
		const createBtn = dialog.getByRole('button', { name: /Create rule/i }).first()
		await expect(createBtn).toBeVisible({ timeout: 5000 })
		await createBtn.click()
		// If scope selection dialog appears, pick "Everyone"
		const everyoneOption = dialog.page().locator('[role="option"]').filter({ hasText: /Everyone/i }).first()
		if (await everyoneOption.isVisible({ timeout: 3000 }).catch(() => false)) {
			await everyoneOption.click()
		}
	}

	// Wait for the rule editor to appear
	const ruleDialog = dialog.page().getByRole('dialog', { name: /Edit rule|Create rule/i }).last()
	await expect(ruleDialog).toBeVisible({ timeout: 8000 })
	return ruleDialog
}

test('toggleing writeQrcodeOnFooter sends correct flag to preview API and QR code appears/disappears in preview', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	const dialog = await openFooterPolicyEditor(page)
	const ruleDialog = await clickChangeOrCreateRule(dialog)

	// Enable the footer
	const enableSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Add visible footer/i })
	if (!(await enableSwitch.locator('input').isChecked().catch(() => false))) {
		await clickSwitch(enableSwitch)
		await expect(enableSwitch.locator('input')).toBeChecked({ timeout: 5000 })
	}

	// Enable QR code
	const qrcodeSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Write QR code on footer/i })
	await expect(qrcodeSwitch).toBeVisible({ timeout: 5000 })
	const qrcodeInput = qrcodeSwitch.locator('input')
	if (!(await qrcodeInput.isChecked().catch(() => false))) {
		await clickSwitch(qrcodeSwitch)
		await expect(qrcodeInput).toBeChecked({ timeout: 5000 })
	}

	// Enable template customization to show the preview
	const templateSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i })
	await expect(templateSwitch).toBeVisible({ timeout: 5000 })
	const templateInput = templateSwitch.locator('input')
	if (!(await templateInput.isChecked().catch(() => false))) {
		const previewReqPromise = captureNextPreviewRequest(page)
		await clickSwitch(templateSwitch)
		await previewReqPromise
		await expect(templateInput).toBeChecked({ timeout: 5000 })
	}

	// --- STEP 1: QR OFF → preview sends false ---
	const qrOffReqPromise = captureNextPreviewRequest(page)
	await clickSwitch(qrcodeSwitch)
	await expect(qrcodeInput).not.toBeChecked({ timeout: 5000 })
	const qrOffReq = await qrOffReqPromise
	const qrOffBody = qrOffReq.postDataJSON() as Record<string, unknown>
	expect(qrOffBody.writeQrcodeOnFooter, 'writeQrcodeOnFooter should be false when switch is OFF').toBe(false)

	// --- STEP 2: QR ON → preview sends true ---
	const qrOnReqPromise = captureNextPreviewRequest(page)
	await clickSwitch(qrcodeSwitch)
	await expect(qrcodeInput).toBeChecked({ timeout: 5000 })
	const qrOnReq = await qrOnReqPromise
	const qrOnBody = qrOnReq.postDataJSON() as Record<string, unknown>
	expect(qrOnBody.writeQrcodeOnFooter, 'writeQrcodeOnFooter should be true when switch is ON').toBe(true)

	// --- STEP 3: Assert the response is a valid PDF when writeQrcodeOnFooter is true ---
	const previewResponse = await page.waitForResponse(
		(res) => res.request().method() === 'POST' && PREVIEW_URL_PATTERN.test(res.url()),
		{ timeout: 15000 },
	)
	expect(previewResponse.status(), 'Preview endpoint should return 200').toBe(200)
	expect(previewResponse.headers()['content-type']).toContain('pdf')
	const body = await previewResponse.body()
	expect(body.length, 'PDF response should not be empty').toBeGreaterThan(100)
	expect(body.subarray(0, 4).toString(), 'Response should start with %PDF').toBe('%PDF')
})

test('preview request always includes writeQrcodeOnFooter when template is customized', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	const dialog = await openFooterPolicyEditor(page)
	const ruleDialog = await clickChangeOrCreateRule(dialog)

	// Enable footer
	const enableSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Add visible footer/i })
	if (!(await enableSwitch.locator('input').isChecked().catch(() => false))) {
		await clickSwitch(enableSwitch)
		await expect(enableSwitch.locator('input')).toBeChecked({ timeout: 5000 })
	}

	// Enable template
	const templateSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i })
	const templateInput = templateSwitch.locator('input')
	if (!(await templateInput.isChecked().catch(() => false))) {
		const reqPromise = captureNextPreviewRequest(page)
		await clickSwitch(templateSwitch)
		await reqPromise
		await expect(templateInput).toBeChecked({ timeout: 5000 })
	}

	// Ensure QR is OFF, then set to ON and verify
	const qrcodeSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Write QR code on footer/i })
	const qrcodeInput = qrcodeSwitch.locator('input')
	if (await qrcodeInput.isChecked().catch(() => false)) {
		const offReqPromise = captureNextPreviewRequest(page)
		await clickSwitch(qrcodeSwitch)
		await expect(qrcodeInput).not.toBeChecked({ timeout: 5000 })
		await offReqPromise
	}

	// Turn QR ON and verify the request body
	const onReqPromise = captureNextPreviewRequest(page)
	await clickSwitch(qrcodeSwitch)
	await expect(qrcodeInput).toBeChecked({ timeout: 5000 })
	const onReq = await onReqPromise
	const body = onReq.postDataJSON() as Record<string, unknown>

	expect(Object.prototype.hasOwnProperty.call(body, 'writeQrcodeOnFooter'),
		'writeQrcodeOnFooter field must be present in the preview request').toBe(true)
	expect(body.writeQrcodeOnFooter, 'writeQrcodeOnFooter must be true when switch is ON').toBe(true)
})
