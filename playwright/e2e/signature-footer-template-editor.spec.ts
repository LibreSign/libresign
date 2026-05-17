/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page, Request, Response } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl } from '../support/nc-provisioning'
import { ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

const PREVIEW_URL_PATTERN = /admin\/footer-template\/preview-pdf/
const SYSTEM_FOOTER_POLICY_URL = '/apps/libresign/api/v1/policies/system/add_footer'

async function bootstrapLibreSignAdmin(page: Page) {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.request.delete('./ocs/v2.php/apps/libresign/api/v1/policies/user/add_footer', {
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
		},
	})

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})
}

async function clickSwitch(switchContainer: Locator): Promise<void> {
	await switchContainer.locator('.checkbox-radio-switch__content').click()
}

async function captureNextPreviewRequest(page: Page): Promise<Request> {
	return page.waitForRequest(
		(request) => request.method() === 'POST' && PREVIEW_URL_PATTERN.test(request.url()),
		{ timeout: 15000 },
	)
}

async function waitForSystemFooterPolicySave(page: Page, action: () => Promise<void>): Promise<{ request: Request, response: Response }> {
	const responsePromise = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes(SYSTEM_FOOTER_POLICY_URL)
	})

	await action()
	const response = await responsePromise
	return {
		request: response.request(),
		response,
	}
}

async function openFooterPolicyEditor(page: Page): Promise<Locator> {
	await page.goto('./settings/admin/libresign')

	const footerCard = await ensureCatalogSettingCardVisible(page, /Signature footer/i, 'footer')
	await footerCard.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Signature footer/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	return dialog
}

async function openSystemRuleEditor(dialog: Locator): Promise<Locator> {
	const changeButton = dialog.getByRole('button', { name: /^Change$/i }).first()
	if (await changeButton.isVisible().catch(() => false)) {
		await changeButton.click()
	} else {
		const createButton = dialog.getByRole('button', { name: /Create rule/i }).first()
		await expect(createButton).toBeVisible({ timeout: 5000 })
		await createButton.click()
		const everyoneOption = dialog.page().locator('[role="option"]').filter({ hasText: /Everyone/i }).first()
		if (await everyoneOption.isVisible().catch(() => false)) {
			await everyoneOption.click()
		}
	}

	const ruleDialog = dialog.page().getByRole('dialog', { name: /Edit rule|Create rule/i }).last()
	await expect(ruleDialog).toBeVisible({ timeout: 10000 })
	return ruleDialog
}

async function ensureCheckboxEnabled(scope: Locator, label: string, triggerPreview = false): Promise<void> {
	const switchContainer = scope.locator('.checkbox-radio-switch').filter({ hasText: new RegExp(label, 'i') }).first()
	await expect(switchContainer).toBeVisible({ timeout: 10000 })
	const checkbox = switchContainer.locator('input[type="checkbox"]').first()
	if (!await checkbox.isChecked().catch(() => false)) {
		const previewRequest = triggerPreview ? captureNextPreviewRequest(scope.page()) : null
		await clickSwitch(switchContainer)
		if (previewRequest) {
			await previewRequest
		}
	}
	await expect(checkbox).toBeChecked()
}

async function getFooterEditorContext(scope: Locator): Promise<{
	ruleDialog: Locator
	editorField: Locator
	preview: Locator
}> {
	await ensureCheckboxEnabled(scope, 'Add visible footer with signature details')
	await ensureCheckboxEnabled(scope, 'Customize footer template', true)

	const editorField = scope.locator('.code-editor .cm-content[contenteditable="true"]').first()
	await expect(editorField).toBeVisible({ timeout: 10000 })

	const preview = scope.locator('.signature-footer-rule-editor__preview').first()
	await expect(preview).toBeVisible({ timeout: 15000 })

	return {
		ruleDialog: scope,
		editorField,
		preview,
	}
}

async function replaceCodeMirrorContent(editorField: Locator, value: string): Promise<void> {
	await editorField.click()
	await editorField.press('Control+a')
	await editorField.fill(value)
}

async function saveRule(ruleDialog: Locator): Promise<{ request: Request, response: Response }> {
	const saveButton = ruleDialog.getByRole('button', { name: /Create rule|Save changes|Save policy rule changes|Save rule changes/i }).last()
	await expect(saveButton).toBeVisible({ timeout: 10000 })
	await expect(saveButton).toBeEnabled({ timeout: 10000 })
	return waitForSystemFooterPolicySave(ruleDialog.page(), async () => {
		await saveButton.click()
	})
}

async function getPersistedSystemFooterPolicy(page: Page): Promise<{ customizeFooterTemplate: boolean, footerTemplate: string }> {
	const response = await page.request.get('./ocs/v2.php/apps/libresign/api/v1/policies/system/add_footer', {
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
		},
	})
	const payload = await response.json() as {
		ocs?: {
			data?: {
				policy?: {
					value?: string | { customizeFooterTemplate?: boolean, footerTemplate?: string }
				}
			}
		}
	}
	const rawValue = payload.ocs?.data?.policy?.value
	if (typeof rawValue === 'string') {
		return JSON.parse(rawValue) as { customizeFooterTemplate: boolean, footerTemplate: string }
	}

	if (rawValue && typeof rawValue === 'object') {
		return {
			customizeFooterTemplate: Boolean(rawValue.customizeFooterTemplate),
			footerTemplate: String(rawValue.footerTemplate ?? ''),
		}
	}

	return { customizeFooterTemplate: false, footerTemplate: '' }
}

test('signature footer template editor updates preview and controls correctly', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)

	const dialog = await openFooterPolicyEditor(page)
	const ruleDialog = await openSystemRuleEditor(dialog)
	const { editorField, preview } = await getFooterEditorContext(ruleDialog)

	const initialTemplate = `<div>Playwright bootstrap ${Date.now()}</div>`
	const initialPreviewRequest = captureNextPreviewRequest(page)
	await replaceCodeMirrorContent(editorField, initialTemplate)
	const initialPayload = initialPreviewRequest.then((request) => request.postDataJSON() as {
		template: string
		width: number
		height: number
	})

	await expect(preview.locator('.pdf-elements-root')).toBeVisible({ timeout: 15000 })
	await expect(preview.getByText(/Preview/i)).toBeVisible({ timeout: 15000 })
	await expect((await initialPayload).template).toContain('Playwright bootstrap')

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
	const widthRequest = captureNextPreviewRequest(page)
	await widthField.fill('620')
	await widthField.press('Tab')
	const widthPayload = await widthRequest.then((request) => request.postDataJSON() as { width: number })
	await expect(widthField).toHaveValue('620')
	await expect(widthPayload.width).toBe(620)

	const heightField = ruleDialog.getByRole('spinbutton', { name: 'Height' }).first()
	const heightRequest = captureNextPreviewRequest(page)
	await heightField.fill('130')
	await heightField.press('Tab')
	const heightPayload = await heightRequest.then((request) => request.postDataJSON() as { height: number })
	await expect(heightField).toHaveValue('130')
	await expect(heightPayload.height).toBe(130)

	const uniqueTemplate = `<div>Playwright footer ${Date.now()}</div>`
	const templateRequest = captureNextPreviewRequest(page)
	await replaceCodeMirrorContent(editorField, uniqueTemplate)
	const templatePayload = await templateRequest.then((request) => request.postDataJSON() as { template: string })
	await expect(templatePayload.template).toContain('Playwright footer')
})

test('footer template persists customization after save and reload', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)

	const customTemplate = `<div style="color:red">Reset test ${Date.now()}</div>`
	const capturedSavePayloads: string[] = []
	page.on('request', (request) => {
		if (['POST', 'PUT', 'PATCH'].includes(request.method()) && request.url().includes(SYSTEM_FOOTER_POLICY_URL)) {
			const payload = request.postDataJSON() as { value?: string }
			capturedSavePayloads.push(String(payload.value ?? ''))
		}
	})
	let dialog = await openFooterPolicyEditor(page)
	let ruleDialog = await openSystemRuleEditor(dialog)
	let editorContext = await getFooterEditorContext(ruleDialog)

	const savePreviewRequest = captureNextPreviewRequest(page)
	await replaceCodeMirrorContent(editorContext.editorField, customTemplate)
	await savePreviewRequest
	const { request: saveRequest, response: saveResponse } = await saveRule(ruleDialog)
	await expect(saveResponse.status()).toBe(200)
	const savePayload = saveRequest.postDataJSON() as { value?: string }
	const decodedValue = JSON.parse(savePayload.value ?? '{}') as { footerTemplate?: string }
	expect(decodedValue.footerTemplate ?? '').toBe(customTemplate)
	const persistedAfterSave = await getPersistedSystemFooterPolicy(page)
	expect(capturedSavePayloads.map((payload) => JSON.parse(payload).footerTemplate ?? '')).toContain(customTemplate)
	expect(persistedAfterSave.customizeFooterTemplate).toBe(true)
	expect(persistedAfterSave.footerTemplate).toBe(customTemplate)

	await page.reload()
	const persistedAfterReload = await getPersistedSystemFooterPolicy(page)
	expect(persistedAfterReload.customizeFooterTemplate).toBe(true)
	expect(persistedAfterReload.footerTemplate).toBe(customTemplate)
	dialog = await openFooterPolicyEditor(page)
	ruleDialog = await openSystemRuleEditor(dialog)
	editorContext = await getFooterEditorContext(ruleDialog)

	await expect.poll(async () => {
		const text = await editorContext.editorField.textContent()
		return (text ?? '').trim()
	}, { timeout: 10000 }).toContain(customTemplate)
})

test('footer template reset reverts to inherited default after save and reload', async ({ page }) => {
	await bootstrapLibreSignAdmin(page)

	const customTemplate = `<div>CUSTOM_${Date.now()}</div>`
	let dialog = await openFooterPolicyEditor(page)
	let ruleDialog = await openSystemRuleEditor(dialog)
	let editorContext = await getFooterEditorContext(ruleDialog)

	const savePreviewRequest = captureNextPreviewRequest(page)
	await replaceCodeMirrorContent(editorContext.editorField, customTemplate)
	await savePreviewRequest
	await saveRule(ruleDialog)
	const persistedAfterCustomSave = await getPersistedSystemFooterPolicy(page)
	expect(persistedAfterCustomSave.customizeFooterTemplate).toBe(true)
	expect(persistedAfterCustomSave.footerTemplate).toBe(customTemplate)

	dialog = await openFooterPolicyEditor(page)
	ruleDialog = await openSystemRuleEditor(dialog)
	editorContext = await getFooterEditorContext(ruleDialog)
	const persistedBeforeReset = await getPersistedSystemFooterPolicy(page)
	expect(persistedBeforeReset.customizeFooterTemplate).toBe(true)
	expect(persistedBeforeReset.footerTemplate).toBe(customTemplate)

	const resetButton = ruleDialog.getByRole('button', { name: 'Reset template to inherited default' }).first()
	if (await resetButton.isVisible().catch(() => false)) {
		const resetPreviewRequest = captureNextPreviewRequest(page)
		await resetButton.click()
		await resetPreviewRequest
	} else {
		const customizeSwitch = ruleDialog.locator('.checkbox-radio-switch').filter({ hasText: /Customize footer template/i }).first()
		await expect(customizeSwitch).toBeVisible({ timeout: 10000 })
		const customizeCheckbox = customizeSwitch.locator('input[type="checkbox"]').first()
		if (await customizeCheckbox.isChecked().catch(() => false)) {
			await clickSwitch(customizeSwitch)
		}
		await expect(customizeCheckbox).not.toBeChecked()
	}
	await saveRule(ruleDialog)
	const persistedAfterReset = await getPersistedSystemFooterPolicy(page)
	expect(persistedAfterReset.footerTemplate).not.toBe(customTemplate)
	expect(persistedAfterReset.footerTemplate.length).toBeGreaterThan(0)

	await page.reload()
	const persistedAfterReload = await getPersistedSystemFooterPolicy(page)
	expect(persistedAfterReload.footerTemplate).toBe(persistedAfterReset.footerTemplate)
	expect(typeof persistedAfterReload.customizeFooterTemplate).toBe('boolean')
})
