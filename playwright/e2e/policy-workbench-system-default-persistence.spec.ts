/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { ensureUserExists } from '../support/nc-provisioning'

test.describe.configure({ retries: 0, timeout: 45000 })

async function openSigningOrderDialog(page: Page) {
	const manageButtons = page.getByRole('button', { name: 'Manage this setting' })
	await expect(manageButtons.first()).toBeVisible({ timeout: 20000 })
	await manageButtons.first().click()
	await expect(page.getByLabel('Signing order')).toBeVisible()
}

async function getSigningOrderDialog(page: Page): Promise<Locator> {
	const dialog = page.getByLabel('Signing order')
	await expect(dialog).toBeVisible()
	return dialog
}

async function waitForEditorIdle(dialog: Locator) {
	const savingOverlays = dialog.locator('[aria-busy="true"]')
	await savingOverlays.first().waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {})
}

async function setAllowOverride(dialog: Locator, enabled: boolean): Promise<boolean> {
	const allowOverrideSwitch = dialog.getByLabel('Allow lower layers to override this rule').first()
	const label = dialog.getByText('Allow lower layers to override this rule').first()

	if (!(await allowOverrideSwitch.count())) {
		return false
	}

	if (enabled) {
		if (!(await allowOverrideSwitch.isChecked())) {
			await label.click()
		}
		await expect(allowOverrideSwitch).toBeChecked()
		return true
	}

	if (await allowOverrideSwitch.isChecked()) {
		await label.click()
	}
	await expect(allowOverrideSwitch).not.toBeChecked()
	return true
}

async function setDefaultSigningOrder(dialog: Locator, enabled: boolean): Promise<boolean> {
	const defaultSigningOrderSwitch = dialog.getByLabel('Set default signing order').first()
	const label = dialog.getByText('Set default signing order').first()

	if (!(await defaultSigningOrderSwitch.count())) {
		return false
	}

	if (enabled) {
		if (!(await defaultSigningOrderSwitch.isChecked())) {
			await label.click()
		}
		await expect(defaultSigningOrderSwitch).toBeChecked()
		return true
	}

	if (await defaultSigningOrderSwitch.isChecked()) {
		await label.click()
	}
	await expect(defaultSigningOrderSwitch).not.toBeChecked()
	return true
}

async function submitRule(dialog: Locator) {
	await waitForEditorIdle(dialog)

	const createButton = dialog.getByRole('button', { name: 'Create rule' }).first()
	if (await createButton.isVisible().catch(() => false)) {
		await expect(createButton).toBeEnabled({ timeout: 8000 })
		await createButton.click()
		await waitForEditorIdle(dialog)
		return
	}

	const saveButton = dialog.getByRole('button', { name: 'Save rule changes' }).first()
	await expect(saveButton).toBeVisible({ timeout: 8000 })
	await expect(saveButton).toBeEnabled({ timeout: 8000 })
	await saveButton.click()
	await waitForEditorIdle(dialog)
}

async function openGlobalRuleEditor(dialog: Locator, globalSection: Locator) {
	const createDefaultButton = globalSection.getByRole('button', { name: 'Create default rule' }).first()
	if (await createDefaultButton.isVisible().catch(() => false)) {
		await createDefaultButton.click()
		return
	}

	await globalSection.getByRole('button', { name: 'Edit default' }).first().click()
}

async function expectGlobalBaselineState(globalSection: Locator) {
	const createDefaultButton = globalSection.getByRole('button', { name: 'Create default rule' }).first()
	if (await createDefaultButton.isVisible().catch(() => false)) {
		await expect(createDefaultButton).toBeVisible()
		return
	}

	await expect(globalSection.getByRole('button', { name: 'Edit default' }).first()).toBeVisible()
}

async function chooseTarget(dialog: Locator, ariaLabel: 'Target groups' | 'Target users', optionText: string) {
	await waitForEditorIdle(dialog)

	const combobox = dialog.getByRole('combobox', { name: ariaLabel }).first()
	const labeledInput = dialog.getByLabel(ariaLabel).first()
	const targetInput = await combobox.count() ? combobox : labeledInput

	await expect(targetInput).toBeVisible({ timeout: 8000 })
	await targetInput.click()

	const searchInput = targetInput.locator('input').first()
	if (await searchInput.count()) {
		await searchInput.fill(optionText)
		const matchingOption = dialog.getByRole('option', { name: new RegExp(optionText, 'i') }).first()
		const matchingVisible = await matchingOption.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)
		if (matchingVisible) {
			await matchingOption.click()
			return
		}

		const anyOption = dialog.getByRole('option').first()
		const anyVisible = await anyOption.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)
		if (anyVisible) {
			await anyOption.click()
			return
		}

		await searchInput.press('ArrowDown')
		await searchInput.press('Enter')
	} else {
		const fallbackTextbox = dialog.getByRole('textbox').first()
		await fallbackTextbox.fill(optionText)
		await fallbackTextbox.press('ArrowDown')
		await fallbackTextbox.press('Enter')
	}
}

async function clickSectionAction(section: Locator, actionLabel: string) {
	await section.getByRole('button', { name: actionLabel }).first().click()
}

async function removeRuleWithConfirmation(page: Page, dialog: Locator, section: Locator, actionLabel: string) {
	const confirmButton = page.getByRole('button', { name: 'Remove rule' }).first()
	if (await confirmButton.isVisible().catch(() => false)) {
		await confirmButton.click()
		await waitForEditorIdle(dialog)
	}

	await clickSectionAction(section, actionLabel)

	const confirmationShown = await confirmButton.waitFor({ state: 'visible', timeout: 4000 }).then(() => true).catch(() => false)
	if (confirmationShown) {
		await confirmButton.click()
		await waitForEditorIdle(dialog)
	}
}

async function removeAllRulesByAction(
	page: Page,
	dialog: Locator,
	section: Locator,
	actionLabel: 'Delete user rule' | 'Delete group rule',
) {
	for (let attempt = 0; attempt < 8; attempt++) {
		const currentCount = await section.getByRole('button', { name: actionLabel }).count()
		if (!currentCount) {
			return
		}

		await removeRuleWithConfirmation(page, dialog, section, actionLabel)
		await waitForEditorIdle(dialog)
	}

	throw new Error(`Failed to remove all rules for action "${actionLabel}" after multiple attempts`)
}

async function ensureBaselineRulesForAdminTarget(page: Page, dialog: Locator) {
	const globalSection = dialog.getByRole('region', { name: 'Global default rules' })
	const groupSection = dialog.getByRole('region', { name: 'Group rules' })
	const userSection = dialog.getByRole('region', { name: 'User rules' })

	await removeAllRulesByAction(page, dialog, userSection, 'Delete user rule')
	await removeAllRulesByAction(page, dialog, groupSection, 'Delete group rule')

	// Normalize global default into a known baseline where lower layers may override.
	if (await globalSection.getByRole('button', { name: 'Edit default' }).count()) {
		await globalSection.getByRole('button', { name: 'Edit default' }).click()
		if (await setAllowOverride(dialog, true)) {
			await submitRule(dialog)
		}
	}
}

test('system default persists allow-override changes across edit cycles', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')
	await expect(page.getByRole('button', { name: 'Manage this setting' }).first()).toBeVisible()

	await openSigningOrderDialog(page)

	const signingOrderDialog = await getSigningOrderDialog(page)
	await ensureBaselineRulesForAdminTarget(page, signingOrderDialog)

	const globalSection = signingOrderDialog.getByRole('region', { name: 'Global default rules' })
	await openGlobalRuleEditor(signingOrderDialog, globalSection)
	await expect(signingOrderDialog.getByRole('heading', { name: 'Default rule' })).toBeVisible()
	await setAllowOverride(signingOrderDialog, true)
	await submitRule(signingOrderDialog)

	await signingOrderDialog.getByRole('button', { name: 'Edit default' }).click()
	await setAllowOverride(signingOrderDialog, true)

	await setAllowOverride(signingOrderDialog, false)
	const saveChangesResponsePromise = page.waitForResponse((response) => {
		return response.request().method() === 'POST'
			&& response.url().includes('/apps/libresign/api/v1/policies/system/signature_flow')
	})
	await signingOrderDialog.getByRole('button', { name: 'Save rule changes' }).click()
	const saveChangesResponse = await saveChangesResponsePromise
	expect(saveChangesResponse.status(), 'Expected Save changes request to succeed').toBe(200)

	await signingOrderDialog.getByRole('button', { name: 'Edit default' }).click()
	await setAllowOverride(signingOrderDialog, false)

	await expect(signingOrderDialog.getByText('Lower layers must inherit this rule')).toBeVisible()

	// Reset should restore inherited baseline behavior.
	await removeRuleWithConfirmation(page, signingOrderDialog, globalSection, 'Reset default')
	await expectGlobalBaselineState(globalSection)
})

test('admin can create, edit, and delete global, group, and user rules from the policy workbench', async ({ page }) => {
	const userTarget = 'policy-e2e-user'

	await ensureUserExists(page.request, userTarget)

	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')
	await openSigningOrderDialog(page)

	const dialog = await getSigningOrderDialog(page)
	const globalSection = dialog.getByRole('region', { name: 'Global default rules' })
	const groupSection = dialog.getByRole('region', { name: 'Group rules' })
	const userSection = dialog.getByRole('region', { name: 'User rules' })

	await ensureBaselineRulesForAdminTarget(page, dialog)

	// Global rule: edit
	await openGlobalRuleEditor(dialog, globalSection)
	await setDefaultSigningOrder(dialog, true)
	await setAllowOverride(dialog, true)
	await submitRule(dialog)
	await expect(globalSection.getByRole('button', { name: 'Edit default' })).toBeVisible()

	// Global rule: enforce inheritance
	await globalSection.getByRole('button', { name: 'Edit default' }).click()
	expect(await setAllowOverride(dialog, false), 'Expected global allow-override switch in editor').toBe(true)
	await submitRule(dialog)
	await expect(globalSection.getByRole('button', { name: 'Edit default' })).toBeVisible()

	await globalSection.getByRole('button', { name: 'Edit default' }).click()
	expect(await setAllowOverride(dialog, true), 'Expected global allow-override switch in editor').toBe(true)
	await submitRule(dialog)
	await expect(globalSection.getByRole('button', { name: 'Edit default' })).toBeVisible()

	// Group rule: create
	await dialog.getByRole('button', { name: 'New group override' }).first().click()
	await chooseTarget(dialog, 'Target groups', 'admin')
	await setDefaultSigningOrder(dialog, true)
	await setAllowOverride(dialog, true)
	await submitRule(dialog)
	await expect(groupSection.getByRole('button', { name: 'Edit group rule' }).first()).toBeVisible()

	// Group rule: edit
	await groupSection.getByRole('button', { name: 'Edit group rule' }).first().click()
	expect(await setDefaultSigningOrder(dialog, false), 'Expected default-signing-order switch in group editor').toBe(true)
	await submitRule(dialog)
	await expect(groupSection.getByRole('button', { name: 'Edit group rule' }).first()).toBeVisible()

	// User rule: create
	await dialog.getByRole('button', { name: 'New user override' }).first().click()
	await chooseTarget(dialog, 'Target users', userTarget)
	await setDefaultSigningOrder(dialog, true)
	await submitRule(dialog)
	await expect(userSection.getByRole('button', { name: 'Edit user rule' }).first()).toBeVisible()

	// User rule: edit
	await clickSectionAction(userSection, 'Edit user rule')
	expect(await setDefaultSigningOrder(dialog, false), 'Expected default-signing-order switch in user editor').toBe(true)
	await submitRule(dialog)
	await expect(userSection.getByRole('button', { name: 'Edit user rule' }).first()).toBeVisible()

	// User rule: delete
	await removeAllRulesByAction(page, dialog, userSection, 'Delete user rule')
	await expect(userSection.getByRole('button', { name: 'Delete user rule' })).toHaveCount(0)

	// Group rule: delete
	await removeAllRulesByAction(page, dialog, groupSection, 'Delete group rule')
	await expect(groupSection.getByRole('button', { name: 'Delete group rule' })).toHaveCount(0)

	// Global rule: reset to inherited baseline
	await removeRuleWithConfirmation(page, dialog, globalSection, 'Reset default')
	await expectGlobalBaselineState(globalSection)
})
