/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { randomBytes } from 'node:crypto'
import { expect, test } from '@playwright/test'
import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import { ensureUserExists, deleteUser } from '../support/nc-provisioning'
import { login } from '../support/nc-login'

const POLICY_KEY = 'notification_delivery_mode'
const API_PATH = `./ocs/v2.php/apps/libresign/api/v1/policies/system/${POLICY_KEY}`
const ruleDialogName = /Create rule|Edit rule|What do you want to create\?/i

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

test('admin can save and reload async and explicit sync', async ({ page }, testInfo) => {
	await bootstrapLibreSignAdmin(page)
	await page.goto('./settings/admin/libresign')

	async function openSettings() {
		const card = await ensureCatalogSettingCardVisible(page, /^Notification delivery/i, 'notification delivery')
		await card.click()
		const dialog = page.getByRole('dialog').filter({ hasText: /Notification delivery/i }).first()
		await expect(dialog).toBeVisible()
		return dialog
	}

	async function openEditor() {
		const dialog = await openSettings()
		const change = dialog.getByRole('button', { name: /^Change$/i }).first()
		if (await change.isVisible().catch(() => false)) {
			await change.click()
		} else {
			await dialog.getByRole('button', { name: /Create rule/i }).first().click()
		}
		return page.getByRole('dialog', { name: ruleDialogName }).last()
	}

	async function readExplicitValue() {
		const response = await page.request.get(API_PATH, { headers: { 'OCS-ApiRequest': 'true', Accept: 'application/json' } })
		expect(response.status()).toBe(200)
		const body = await response.json()
		return body.ocs?.data?.policy?.value ?? null
	}

	async function selectAndSave(mode: 'sync' | 'async') {
		const editor = await openEditor()
		if (mode === 'async') {
			await editor.screenshot({ path: testInfo.outputPath('notification-options.png') })
		}
		const row = editor.locator('.checkbox-radio-switch').filter({ hasText: mode === 'async' ? /^Asynchronous/ : /^Synchronous/ }).first()
		await expect(row).toBeVisible()
		await row.click()
		const save = editor.getByRole('button', { name: /Create rule|Save changes|Save rule changes|Save policy rule changes/i }).last()
		await expect(save).toBeEnabled()
		const responsePromise = page.waitForResponse((response) => response.url().includes(`/policies/system/${POLICY_KEY}`) && response.request().method() === 'POST')
		await save.click()
		const response = await responsePromise
		expect(response.status()).toBe(200)
		expect(await readExplicitValue()).toBe(mode)
	}

	try {
		const dialog = await openSettings()
		await dialog.screenshot({ path: testInfo.outputPath('notification-before.png') })
		await page.keyboard.press('Escape')
		await selectAndSave('async')
		await page.reload()
		const asyncDialog = await openSettings()
		await expect(asyncDialog).toContainText('Background processing')
		await asyncDialog.screenshot({ path: testInfo.outputPath('notification-async-after.png') })
		await page.keyboard.press('Escape')
		await selectAndSave('sync')
		await page.reload()
		const syncDialog = await openSettings()
		await expect(syncDialog).toContainText('Process immediately')
		await syncDialog.screenshot({ path: testInfo.outputPath('notification-sync-reloaded.png') })
		await expect.poll(readExplicitValue).toBe('sync')
	} finally {
		await page.request.post(API_PATH, {
			headers: { 'OCS-ApiRequest': 'true' },
			data: { value: null, allowChildOverride: false },
		})
	}
})

test('a regular user cannot write or read the system notification policy', async ({ page, browser, baseURL }) => {
	await bootstrapLibreSignAdmin(page)
	const headers = { 'OCS-ApiRequest': 'true', Accept: 'application/json' }
	const previous = await page.request.get(API_PATH, { headers })
	expect(previous.status()).toBe(200)
	const previousValue = (await previous.json()).ocs?.data?.policy?.value ?? null
	const userId = `notification-policy-user-${randomBytes(4).toString('hex')}`
	await ensureUserExists(page.request, userId, '123456')
	const userContext = await browser.newContext({ baseURL: baseURL!, ignoreHTTPSErrors: true })
	try {
		await login(userContext.request, userId, '123456')
		const writeResponse = await userContext.request.post(API_PATH, {
			headers,
			data: { value: 'async', allowChildOverride: false },
			failOnStatusCode: false,
			maxRedirects: 0,
		})
		expect(writeResponse.status()).toBe(403)
		const readResponse = await userContext.request.get(API_PATH, {
			headers,
			failOnStatusCode: false,
			maxRedirects: 0,
		})
		expect(readResponse.status()).toBe(403)
		const current = await page.request.get(API_PATH, { headers })
		expect(current.status()).toBe(200)
		expect((await current.json()).ocs?.data?.policy?.value ?? null).toEqual(previousValue)
	} finally {
		await userContext.close()
		await deleteUser(page.request, userId).catch(() => {})
	}
})
