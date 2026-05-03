/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, type Locator, type Page } from '@playwright/test'
import { login } from './nc-login'
import { configureOpenSsl } from './nc-provisioning'

async function clickSwitchContent(switchContainer: Locator): Promise<void> {
	await switchContainer.locator('.checkbox-radio-switch__content').first().click()
}

export async function bootstrapLibreSignAdmin(page: Page): Promise<void> {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})
}

export async function openSystemFooterRuleEditor(page: Page): Promise<Locator> {
	await page.goto('./settings/admin/libresign')

	const footerCard = page.getByRole('button', { name: /Signature footer/i }).first()
	await expect(footerCard).toBeVisible({ timeout: 20000 })
	await footerCard.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Signature footer/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })

	const changeButton = dialog.getByRole('button', { name: /^Change$/i }).first()
	if (await changeButton.isVisible().catch(() => false)) {
		await changeButton.click()
	} else {
		const createButton = dialog.getByRole('button', { name: /Create rule/i }).first()
		await expect(createButton).toBeVisible({ timeout: 10000 })
		await createButton.click()
		const everyoneOption = page.locator('[role="option"]').filter({ hasText: /Everyone/i }).first()
		if (await everyoneOption.isVisible().catch(() => false)) {
			await everyoneOption.click()
		}
	}

	const ruleDialog = page.getByRole('dialog', { name: /Edit rule|Create rule/i }).last()
	await expect(ruleDialog).toBeVisible({ timeout: 10000 })
	return ruleDialog
}

export async function ensureFooterTemplateEnabled(scope: Locator): Promise<void> {
	const addFooterSwitch = scope.locator('.checkbox-radio-switch')
		.filter({ hasText: /Add visible footer(?: with signature details)?/i })
		.first()
	await expect(addFooterSwitch).toBeVisible({ timeout: 10000 })
	const addFooterCheckbox = addFooterSwitch.locator('input[type="checkbox"]').first()
	if (!await addFooterCheckbox.isChecked()) {
		await clickSwitchContent(addFooterSwitch)
		await expect(addFooterCheckbox).toBeChecked()
	}

	const customizeSwitch = scope.locator('.checkbox-radio-switch')
		.filter({ hasText: /Customize footer template/i })
		.first()
	await expect(customizeSwitch).toBeVisible({ timeout: 10000 })
	const customizeCheckbox = customizeSwitch.locator('input[type="checkbox"]').first()
	if (!await customizeCheckbox.isChecked()) {
		await clickSwitchContent(customizeSwitch)
		await expect(customizeCheckbox).toBeChecked()
	}
}

export async function fillTemplateEditor(scope: Locator, value: string): Promise<void> {
	const editor = scope.locator('.code-editor .cm-content[contenteditable="true"]').first()
	await expect(editor).toBeVisible({ timeout: 10000 })
	await editor.click()
	await editor.press('Control+a')
	await editor.fill(value)
}
