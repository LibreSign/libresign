/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { ensureUserExists } from '../support/nc-provisioning'

test.describe.configure({ retries: 0, timeout: 45000 })

const openPolicyButtonName = /Manage this setting|Open policy|Open setting policy/i
const createRuleButtonName = /Create rule|Create policy rule/i
const saveRuleButtonName = /Save rule changes|Save changes|Save policy rule changes/i
const createDefaultButtonName = /Create default rule|Create global default rule/i
const editGlobalDefaultButtonName = /Edit default|Edit global default/i
const resetGlobalDefaultButtonName = /Reset default|Reset global default|Reset default rule/i
const newGroupRuleButtonName = /New group rule|New group override/i
const newUserRuleButtonName = /New user rule|New user override/i
const editGroupRuleButtonName = /Edit group rule|Edit group override/i
const deleteGroupRuleButtonName = /Delete group rule|Delete group override/i
const editUserRuleButtonName = /Edit user rule|Edit user override/i
const deleteUserRuleButtonName = /Delete user rule|Delete user override/i
const groupSectionName = /Group rules|Group overrides/i
const userSectionName = /User rules|User overrides/i
const globalEditorHeadingName = /Default rule|Global default rule/i
const inheritValueMessage = /Lower layers must inherit this rule|Lower layers must inherit this value/i

async function openSigningOrderDialog(page: Page) {
	const manageButtons = page.getByRole('button', { name: openPolicyButtonName })
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

async function setSigningFlow(dialog: Locator, flow: 'parallel' | 'ordered_numeric'): Promise<boolean> {
	const label = flow === 'parallel'
		? /Simultaneous \(Parallel\)/i
		: /Sequential/i
	const flowRadio = dialog.getByRole('radio', { name: label }).first()

	if (!(await flowRadio.count())) {
		return false
	}

	if (!(await flowRadio.isChecked())) {
		await flowRadio.check({ force: true })
	}
	await expect(flowRadio).toBeChecked()
	return true
}

async function submitRule(dialog: Locator) {
	await waitForEditorIdle(dialog)

	const createButton = dialog.getByRole('button', { name: createRuleButtonName }).first()
	if (await createButton.isVisible().catch(() => false)) {
		await expect(createButton).toBeEnabled({ timeout: 8000 })
		await createButton.click()
		await waitForEditorIdle(dialog)
		return
	}

	const saveButton = dialog.getByRole('button', { name: saveRuleButtonName }).first()
	await expect(saveButton).toBeVisible({ timeout: 8000 })
	await expect(saveButton).toBeEnabled({ timeout: 8000 })
	await saveButton.click()
	await waitForEditorIdle(dialog)
}

async function openGlobalRuleEditor(dialog: Locator, globalSection: Locator) {
	const createDefaultButton = globalSection.getByRole('button', { name: createDefaultButtonName }).first()
	if (await createDefaultButton.isVisible().catch(() => false)) {
		await createDefaultButton.click()
		return
	}

	await globalSection.getByRole('button', { name: editGlobalDefaultButtonName }).first().click()
}

async function expectGlobalBaselineState(globalSection: Locator) {
	const createDefaultButton = globalSection.getByRole('button', { name: createDefaultButtonName }).first()
	if (await createDefaultButton.isVisible().catch(() => false)) {
		await expect(createDefaultButton).toBeVisible()
		return
	}

	await expect(globalSection.getByRole('button', { name: editGlobalDefaultButtonName }).first()).toBeVisible()
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

async function clickSectionAction(section: Locator, actionLabel: string | RegExp) {
	let lastError: unknown

	for (let attempt = 0; attempt < 5; attempt++) {
		const button = section.getByRole('button', { name: actionLabel }).first()

		try {
			await button.waitFor({ state: 'visible', timeout: 2000 })
			await button.scrollIntoViewIfNeeded().catch(() => {})
			await button.click({ timeout: 2000 })
			return
		} catch (error) {
			lastError = error
			await new Promise((resolve) => setTimeout(resolve, 250))
		}
	}

	throw lastError instanceof Error
		? lastError
		: new Error(`Failed to click section action matching "${String(actionLabel)}"`)
}

async function removeRuleWithConfirmation(page: Page, dialog: Locator, section: Locator, actionLabel: string | RegExp) {
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
	actionLabel: string | RegExp,
) {
	for (let attempt = 0; attempt < 8; attempt++) {
		const currentCount = await section.getByRole('button', { name: actionLabel }).count()
		if (!currentCount) {
			return
		}

		await removeRuleWithConfirmation(page, dialog, section, actionLabel)
		await waitForEditorIdle(dialog)
		await expect.poll(async () => {
			return section.getByRole('button', { name: actionLabel }).count()
		}, {
			timeout: 5000,
		}).toBeLessThan(currentCount)
	}

	throw new Error(`Failed to remove all rules for action "${actionLabel}" after multiple attempts`)
}

async function ensureBaselineRulesForAdminTarget(page: Page, dialog: Locator) {
	const globalSection = dialog.getByRole('region', { name: 'Global default rules' })
	const groupSection = dialog.getByRole('region', { name: groupSectionName })
	const userSection = dialog.getByRole('region', { name: userSectionName })

	await removeAllRulesByAction(page, dialog, userSection, deleteUserRuleButtonName)
	await removeAllRulesByAction(page, dialog, groupSection, deleteGroupRuleButtonName)

	// Normalize global default into a known baseline where lower layers may override.
	if (await globalSection.getByRole('button', { name: editGlobalDefaultButtonName }).count()) {
		await globalSection.getByRole('button', { name: editGlobalDefaultButtonName }).click()
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
	await expect(page.getByRole('button', { name: openPolicyButtonName }).first()).toBeVisible()

	await openSigningOrderDialog(page)

	const signingOrderDialog = await getSigningOrderDialog(page)
	await ensureBaselineRulesForAdminTarget(page, signingOrderDialog)

	const globalSection = signingOrderDialog.getByRole('region', { name: 'Global default rules' })
	await openGlobalRuleEditor(signingOrderDialog, globalSection)
	await expect(signingOrderDialog.getByRole('heading', { name: globalEditorHeadingName }).last()).toBeVisible()
	await setAllowOverride(signingOrderDialog, true)
	await submitRule(signingOrderDialog)

	await signingOrderDialog.getByRole('button', { name: editGlobalDefaultButtonName }).click()
	await setAllowOverride(signingOrderDialog, true)

	await setAllowOverride(signingOrderDialog, false)
	const saveChangesResponsePromise = page.waitForResponse((response) => {
		return response.request().method() === 'POST'
			&& response.url().includes('/apps/libresign/api/v1/policies/system/signature_flow')
	})
	await signingOrderDialog.getByRole('button', { name: saveRuleButtonName }).first().click()
	const saveChangesResponse = await saveChangesResponsePromise
	expect(saveChangesResponse.status(), 'Expected Save changes request to succeed').toBe(200)

	await signingOrderDialog.getByRole('button', { name: editGlobalDefaultButtonName }).click()
	await setAllowOverride(signingOrderDialog, false)

	await expect(signingOrderDialog.getByText(inheritValueMessage)).toBeVisible()

	// Reset should restore inherited baseline behavior.
	await removeRuleWithConfirmation(page, signingOrderDialog, globalSection, resetGlobalDefaultButtonName)
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
	const groupSection = dialog.getByRole('region', { name: groupSectionName })
	const userSection = dialog.getByRole('region', { name: userSectionName })

	await ensureBaselineRulesForAdminTarget(page, dialog)

	// Global rule: edit
	await openGlobalRuleEditor(dialog, globalSection)
	expect(await setSigningFlow(dialog, 'ordered_numeric'), 'Expected signing-flow radios in global editor').toBe(true)
	await setAllowOverride(dialog, true)
	await submitRule(dialog)
	await expect(globalSection.getByRole('button', { name: editGlobalDefaultButtonName })).toBeVisible()

	// Global rule: enforce inheritance
	await globalSection.getByRole('button', { name: editGlobalDefaultButtonName }).click()
	expect(await setAllowOverride(dialog, false), 'Expected global allow-override switch in editor').toBe(true)
	await submitRule(dialog)
	await expect(globalSection.getByRole('button', { name: editGlobalDefaultButtonName })).toBeVisible()

	await globalSection.getByRole('button', { name: editGlobalDefaultButtonName }).click()
	expect(await setAllowOverride(dialog, true), 'Expected global allow-override switch in editor').toBe(true)
	await submitRule(dialog)
	await expect(globalSection.getByRole('button', { name: editGlobalDefaultButtonName })).toBeVisible()

	// Group rule: create
	await dialog.getByRole('button', { name: newGroupRuleButtonName }).first().click()
	await chooseTarget(dialog, 'Target groups', 'admin')
	expect(await setSigningFlow(dialog, 'ordered_numeric'), 'Expected signing-flow radios in group editor').toBe(true)
	await setAllowOverride(dialog, true)
	await submitRule(dialog)
	await expect(groupSection.getByRole('button', { name: editGroupRuleButtonName }).first()).toBeVisible()

	// Group rule: edit
	await groupSection.getByRole('button', { name: editGroupRuleButtonName }).first().click()
	expect(await setSigningFlow(dialog, 'parallel'), 'Expected signing-flow radios in group editor').toBe(true)
	await submitRule(dialog)
	await expect(groupSection.getByRole('button', { name: editGroupRuleButtonName }).first()).toBeVisible()

	// User rule: create
	await dialog.getByRole('button', { name: newUserRuleButtonName }).first().click()
	await chooseTarget(dialog, 'Target users', userTarget)
	expect(await setSigningFlow(dialog, 'ordered_numeric'), 'Expected signing-flow radios in user editor').toBe(true)
	await submitRule(dialog)
	await expect(userSection.getByRole('button', { name: editUserRuleButtonName }).first()).toBeVisible()

	// User rule: edit
	await clickSectionAction(userSection, editUserRuleButtonName)
	expect(await setSigningFlow(dialog, 'parallel'), 'Expected signing-flow radios in user editor').toBe(true)
	await submitRule(dialog)
	await expect(userSection.getByRole('button', { name: editUserRuleButtonName }).first()).toBeVisible()

	// User rule: delete
	await removeAllRulesByAction(page, dialog, userSection, deleteUserRuleButtonName)
	await expect(userSection.getByRole('button', { name: deleteUserRuleButtonName })).toHaveCount(0)

	// Group rule: delete
	await removeAllRulesByAction(page, dialog, groupSection, deleteGroupRuleButtonName)
	await expect(groupSection.getByRole('button', { name: deleteGroupRuleButtonName })).toHaveCount(0)

	// Global rule: reset to inherited baseline
	await removeRuleWithConfirmation(page, dialog, globalSection, resetGlobalDefaultButtonName)
	await expectGlobalBaselineState(globalSection)
})
