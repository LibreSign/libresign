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
const changeDefaultButtonName = /^Change$/i
const removeExceptionButtonName = /Remove exception|Remove rule/i
const groupRuleTargetLabel = 'admin'
const userRuleTargetLabel = 'policy-e2e-user'
const instanceWideTargetLabel = 'Default (instance-wide)'
const globalEditorHeadingName = /Default rule|Global default rule|Instance default rule/i

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

	const editorPanel = dialog.locator('.policy-workbench__editor-panel').last()
	const createButton = editorPanel.getByRole('button', { name: /Create policy rule/i }).first()
	if (await createButton.isVisible().catch(() => false)) {
		await expect(createButton).toBeEnabled({ timeout: 8000 })
		await createButton.click()
		await waitForEditorIdle(dialog)
		return
	}

	const saveButton = editorPanel.getByRole('button', { name: /Save policy rule changes/i }).first()
	await expect(saveButton).toBeVisible({ timeout: 8000 })
	await expect(saveButton).toBeEnabled({ timeout: 8000 })
	await saveButton.click()
	await waitForEditorIdle(dialog)
}

function getRuleRow(dialog: Locator, _scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	return dialog.locator('tbody tr').filter({
		hasText: targetLabel,
	}).first()
}

async function openSystemDefaultEditor(dialog: Locator) {
	await dialog.getByRole('button', { name: changeDefaultButtonName }).first().click()
}

async function openRuleActions(dialog: Locator, scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	const row = getRuleRow(dialog, scope, targetLabel)
	await expect(row).toBeVisible({ timeout: 8000 })
	await row.getByRole('button', { name: 'Rule actions' }).first().click()
	return row
}

async function clickRuleMenuAction(dialog: Locator, actionName: 'Edit' | 'Remove') {
	const page = dialog.page()
	const actionItem = page
		.locator('.action-item, [role="menuitem"], li')
		.filter({ hasText: new RegExp(`^${actionName}$`, 'i') })
		.first()

	await expect(actionItem).toBeVisible({ timeout: 5000 })
	await actionItem.click()
}

async function editRule(dialog: Locator, scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	await openRuleActions(dialog, scope, targetLabel)
	await clickRuleMenuAction(dialog, 'Edit')
}

async function removeRule(dialog: Locator, scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	await openRuleActions(dialog, scope, targetLabel)
	await clickRuleMenuAction(dialog, 'Remove')
	const page = dialog.page()
	const removeExceptionButton = page.getByRole('button', { name: removeExceptionButtonName }).first()
	if (await removeExceptionButton.isVisible().catch(() => false)) {
		await removeExceptionButton.click()
	} else {
		await page.getByText(/^Remove exception$/i).first().click()
	}
	await waitForEditorIdle(dialog)
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

async function ensureRuleAbsent(dialog: Locator, scope: 'Group' | 'User', targetLabel: string) {
	const row = getRuleRow(dialog, scope, targetLabel)
	if (await row.count()) {
		await removeRule(dialog, scope, targetLabel)
		await expect(getRuleRow(dialog, scope, targetLabel)).toHaveCount(0)
	}
}

async function ensureSystemDefaultBaseline(dialog: Locator) {
	const customBadge = dialog.getByText(/\(custom\)/i).first()
	if (await customBadge.isVisible().catch(() => false)) {
		await removeRule(dialog, 'Instance', instanceWideTargetLabel)
		await expect(dialog.getByText(/\(system\)/i)).toBeVisible()
	}
}

test('system default persists across edit cycles and can be reset to the system baseline', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')
	await expect(page.getByRole('button', { name: openPolicyButtonName }).first()).toBeVisible()

	await openSigningOrderDialog(page)

	const signingOrderDialog = await getSigningOrderDialog(page)
	await ensureSystemDefaultBaseline(signingOrderDialog)
	await ensureRuleAbsent(signingOrderDialog, 'Group', groupRuleTargetLabel)
	await ensureRuleAbsent(signingOrderDialog, 'User', userRuleTargetLabel)

	await openSystemDefaultEditor(signingOrderDialog)
	await expect(signingOrderDialog.getByRole('heading', { name: globalEditorHeadingName }).last()).toBeVisible()
	expect(await setSigningFlow(signingOrderDialog, 'ordered_numeric'), 'Expected signing-flow radios in system editor').toBe(true)
	await submitRule(signingOrderDialog)
	await expect(getRuleRow(signingOrderDialog, 'Instance', instanceWideTargetLabel)).toContainText('Sequential')

	await openSystemDefaultEditor(signingOrderDialog)
	expect(await setSigningFlow(signingOrderDialog, 'parallel'), 'Expected signing-flow radios in system editor').toBe(true)
	const saveChangesResponsePromise = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes('/apps/libresign/api/v1/policies/system/signature_flow')
	})
	await submitRule(signingOrderDialog)
	const saveChangesResponse = await saveChangesResponsePromise
	expect(saveChangesResponse.status(), 'Expected Save changes request to succeed').toBe(200)
	await expect(getRuleRow(signingOrderDialog, 'Instance', instanceWideTargetLabel)).toContainText('Simultaneous (Parallel)')

	await removeRule(signingOrderDialog, 'Instance', instanceWideTargetLabel)
	await expect(signingOrderDialog.getByText(/\(system\)/i)).toBeVisible()
	await expect(signingOrderDialog.getByText(/Default:\s*Let users choose/i)).toBeVisible()
})

test('admin can create, edit, and delete global, group, and user rules from the policy workbench', async ({ page }) => {
	const userTarget = userRuleTargetLabel

	await ensureUserExists(page.request, userTarget)

	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')
	await openSigningOrderDialog(page)

	const dialog = await getSigningOrderDialog(page)
	await ensureSystemDefaultBaseline(dialog)
	await ensureRuleAbsent(dialog, 'Group', groupRuleTargetLabel)
	await ensureRuleAbsent(dialog, 'User', userTarget)

	// Global rule: edit
	await openSystemDefaultEditor(dialog)
	expect(await setSigningFlow(dialog, 'ordered_numeric'), 'Expected signing-flow radios in global editor').toBe(true)
	await submitRule(dialog)
	await expect(getRuleRow(dialog, 'Instance', instanceWideTargetLabel)).toContainText('Sequential')

	// Group rule: create
	await dialog.getByRole('button', { name: 'Create rule' }).first().click()
	await dialog.page().getByText(/^Group$/i).first().click()
	await chooseTarget(dialog, 'Target groups', 'admin')
	expect(await setSigningFlow(dialog, 'ordered_numeric'), 'Expected signing-flow radios in group editor').toBe(true)
	await submitRule(dialog)
	await expect(getRuleRow(dialog, 'Group', groupRuleTargetLabel)).toContainText('Sequential')

	// Group rule: edit
	await editRule(dialog, 'Group', groupRuleTargetLabel)
	expect(await setSigningFlow(dialog, 'parallel'), 'Expected signing-flow radios in group editor').toBe(true)
	await submitRule(dialog)
	await expect(getRuleRow(dialog, 'Group', groupRuleTargetLabel)).toContainText('Simultaneous (Parallel)')

	// User rule: create
	await dialog.getByRole('button', { name: 'Create rule' }).first().click()
	await dialog.page().getByText(/^User$/i).first().click()
	await chooseTarget(dialog, 'Target users', userTarget)
	expect(await setSigningFlow(dialog, 'ordered_numeric'), 'Expected signing-flow radios in user editor').toBe(true)
	await submitRule(dialog)
	await expect(getRuleRow(dialog, 'User', userTarget)).toContainText('Sequential')

	// User rule: edit
	await editRule(dialog, 'User', userTarget)
	expect(await setSigningFlow(dialog, 'parallel'), 'Expected signing-flow radios in user editor').toBe(true)
	await submitRule(dialog)
	await expect(getRuleRow(dialog, 'User', userTarget)).toContainText('Simultaneous (Parallel)')

	// User rule: delete
	await removeRule(dialog, 'User', userTarget)
	await expect(getRuleRow(dialog, 'User', userTarget)).toHaveCount(0)

	// Group rule: delete
	await removeRule(dialog, 'Group', groupRuleTargetLabel)
	await expect(getRuleRow(dialog, 'Group', groupRuleTargetLabel)).toHaveCount(0)

	// Global rule: reset to inherited baseline
	await removeRule(dialog, 'Instance', instanceWideTargetLabel)
	await expect(dialog.getByText(/\(system\)/i)).toBeVisible()
	await expect(dialog.getByText(/Default:\s*Let users choose/i)).toBeVisible()
})
