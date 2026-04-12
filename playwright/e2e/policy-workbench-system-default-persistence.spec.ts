/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator, Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { ensureUserExists } from '../support/nc-provisioning'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 45000 })

const changeDefaultButtonName = /^Change$/i
const removeExceptionButtonName = /Remove exception|Remove rule/i
const userRuleTargetLabel = 'policy-e2e-user'
const instanceWideTargetLabel = 'Default (instance-wide)'
const ruleDialogName = /Create rule|Edit rule|What do you want to create\?/i

async function getActiveRuleDialog(page: Page): Promise<Locator> {
	const roleDialog = page.getByRole('dialog', { name: ruleDialogName }).last()
	if (await roleDialog.isVisible().catch(() => false)) {
		return roleDialog
	}

	const headingDialog = page.locator('[role="dialog"]').filter({
		has: page.getByRole('heading', { name: ruleDialogName }),
	}).last()
	await expect(headingDialog).toBeVisible({ timeout: 8000 })
	return headingDialog
}

async function openSigningOrderDialog(page: Page) {
	const signingOrderCardButton = page.getByRole('button', { name: /Signing order/i }).first()
	await expect(signingOrderCardButton).toBeVisible({ timeout: 20000 })
	await signingOrderCardButton.click()
	await expect(page.getByLabel('Signing order')).toBeVisible({ timeout: 10000 })
}

async function getSigningOrderDialog(page: Page): Promise<Locator> {
	const dialog = page.getByLabel('Signing order')
	await expect(dialog).toBeVisible()
	return dialog
}

async function waitForEditorIdle(dialog: Locator) {
	const savingOverlays = dialog.page().locator('[aria-busy="true"]')
	await savingOverlays.first().waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {})
}

async function setSigningFlow(dialog: Locator, flow: 'parallel' | 'ordered_numeric' | 'none'): Promise<boolean> {
	const label = flow === 'parallel'
		? /Simultaneous \(Parallel\)/i
		: flow === 'ordered_numeric'
			? /Sequential/i
			: /Let users choose/i
	const page = dialog.page()
	const activeDialog = await getActiveRuleDialog(page).catch(() => null)
	const root = activeDialog ?? dialog
	const flowRadio = root.getByRole('radio', { name: label }).first()

	if (!(await flowRadio.count())) {
		return false
	}

	if (!(await flowRadio.isChecked())) {
		await flowRadio.click({ force: true })
		if (!(await flowRadio.isChecked())) {
			const optionRow = root.locator('.checkbox-radio-switch').filter({ hasText: label }).first()
			if (await optionRow.count()) {
				await optionRow.click({ force: true })
			}
		}
	}
	return true
}

async function submitRule(dialog: Locator) {
	await waitForEditorIdle(dialog)
	const page = dialog.page()
	const activeDialog = await getActiveRuleDialog(page).catch(() => null)
	const root = activeDialog ?? dialog

	const createButton = root.getByRole('button', { name: /Create rule|Create policy rule/i }).last()
	if (await createButton.isVisible().catch(() => false)) {
		await expect(createButton).toBeEnabled({ timeout: 8000 })
		await createButton.click()
		await waitForEditorIdle(dialog)
		return
	}

	const saveButton = root.getByRole('button', { name: /Save changes|Save policy rule changes|Save rule changes/i }).last()
	await expect(saveButton).toBeVisible({ timeout: 8000 })
	await expect(saveButton).toBeEnabled({ timeout: 8000 })
	await saveButton.click()
	await waitForEditorIdle(dialog)
}

async function submitSystemRuleAndWait(dialog: Locator) {
	const page = dialog.page()
	const saveSystemPolicyResponse = page.waitForResponse((response) => {
		return ['POST', 'PUT', 'PATCH'].includes(response.request().method())
			&& response.url().includes('/apps/libresign/api/v1/policies/system/signature_flow')
	})

	await submitRule(dialog)
	const response = await saveSystemPolicyResponse
	expect(response.status(), 'Expected system policy save request to succeed').toBe(200)
}

async function getSystemSignatureFlowValue(page: Page): Promise<unknown> {
	const response = await page.request.get('./ocs/v2.php/apps/libresign/api/v1/policies/system/signature_flow', {
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
		},
	})
	expect(response.status(), 'Expected system policy fetch request to succeed').toBe(200)
	const data = await response.json() as {
		ocs?: {
			data?: {
				policy?: {
					value?: unknown
				}
			}
		}
	}

	return data.ocs?.data?.policy?.value ?? null
}

async function clearSystemSignatureFlowValue(page: Page): Promise<void> {
	const response = await page.request.post('./ocs/v2.php/apps/libresign/api/v1/policies/system/signature_flow', {
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
		},
		data: {
			value: null,
			allowChildOverride: true,
		},
	})
	expect(response.status(), 'Expected system policy reset request to succeed').toBe(200)
}

function getRuleRow(dialog: Locator, _scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	return dialog.locator('tbody tr').filter({
		hasText: targetLabel,
	}).first()
}

async function openSystemDefaultEditor(dialog: Locator) {
	await dialog.getByRole('button', { name: changeDefaultButtonName }).first().click()
	await getActiveRuleDialog(dialog.page())
}

async function getCreateScopeDialog(page: Page): Promise<Locator> {
	const dialog = await getActiveRuleDialog(page)
	await expect(dialog.getByRole('heading', { name: /What do you want to create\?/i })).toBeVisible()
	return dialog
}

async function getCreateScopeOption(page: Page, scopeLabel: 'User' | 'Group' | 'Instance') {
	const dialog = await getCreateScopeDialog(page)
	return dialog.getByRole('option', { name: new RegExp(`^${scopeLabel}\\b`, 'i') }).first()
}

async function openRuleActions(dialog: Locator, scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	const row = getRuleRow(dialog, scope, targetLabel)
	await expect(row).toBeVisible({ timeout: 8000 })
	await row.getByRole('button', { name: 'Rule actions' }).first().click()
	return row
}

async function clickRuleMenuAction(dialog: Locator, actionName: 'Edit' | 'Remove'): Promise<boolean> {
	const page = dialog.page()
	const actionPattern = actionName === 'Remove'
		? /^(Remove|Delete)$/i
		: /^Edit$/i
	const actionItem = page
		.locator('.action-item:visible, [role="menuitem"]:visible, li.action:visible')
		.filter({ hasText: actionPattern })
		.first()

	if (!(await actionItem.isVisible().catch(() => false))) {
		return false
	}

	const clicked = await actionItem.click({ timeout: 1500 }).then(() => true).catch(() => false)
	if (!clicked) {
		return false
	}

	return true
}

async function editRule(dialog: Locator, scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	for (let attempt = 0; attempt < 3; attempt += 1) {
		await openRuleActions(dialog, scope, targetLabel)
		if (await clickRuleMenuAction(dialog, 'Edit')) {
			return
		}
		await dialog.page().waitForTimeout(200)
	}

	expect(false, 'Expected Edit action to be visible in rule menu').toBe(true)
}

async function removeRule(dialog: Locator, scope: 'Instance' | 'Group' | 'User', targetLabel: string) {
	for (let attempt = 0; attempt < 3; attempt += 1) {
		await openRuleActions(dialog, scope, targetLabel)
		if (await clickRuleMenuAction(dialog, 'Remove')) {
			const page = dialog.page()
			const removeExceptionButton = page.getByRole('button', { name: removeExceptionButtonName }).first()
			if (await removeExceptionButton.isVisible().catch(() => false)) {
				await removeExceptionButton.click()
			} else {
				const removeExceptionText = page.getByText(/^Remove exception$/i).first()
				if (await removeExceptionText.isVisible().catch(() => false)) {
					await removeExceptionText.click()
				}
			}
			await waitForEditorIdle(dialog)
			await dialog.page().waitForTimeout(150)
			return
		}
		await dialog.page().waitForTimeout(200)
	}

	expect(false, 'Expected Remove action to be visible in rule menu').toBe(true)
}

async function chooseTarget(dialog: Locator, ariaLabel: 'Target groups' | 'Target users', optionText: string) {
	await waitForEditorIdle(dialog)
	const page = dialog.page()
	const activeDialog = await getActiveRuleDialog(page).catch(() => null)
	const root = activeDialog ?? dialog

	const combobox = root.getByRole('combobox', { name: ariaLabel }).first()
	const labeledInput = root.getByLabel(ariaLabel).first()
	const targetInput = await combobox.count() ? combobox : labeledInput

	await expect(targetInput).toBeVisible({ timeout: 8000 })
	await targetInput.click()

	const searchInput = targetInput.locator('input').first()
	if (await searchInput.count()) {
		await searchInput.fill(optionText)
		await page.waitForTimeout(250)
		const matchingOption = page.getByRole('option', { name: new RegExp(optionText, 'i') }).first()
		const matchingVisible = await matchingOption.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)
		if (matchingVisible) {
			await matchingOption.click()
			await searchInput.press('Tab').catch(() => {})
			return
		}

		const exactTextOption = page.getByText(new RegExp(`^${optionText}$`, 'i')).last()
		const exactTextVisible = await exactTextOption.waitFor({ state: 'visible', timeout: 1500 }).then(() => true).catch(() => false)
		if (exactTextVisible) {
			await exactTextOption.click()
			await searchInput.press('Tab').catch(() => {})
			return
		}

		const anyOption = page.getByRole('option').first()
		const anyVisible = await anyOption.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)
		if (anyVisible) {
			await anyOption.click()
			await searchInput.press('Tab').catch(() => {})
			return
		}

		await searchInput.press('ArrowDown')
		await searchInput.press('Enter')
		await searchInput.press('Tab').catch(() => {})
	} else {
		const fallbackTextbox = root.getByRole('textbox').first()
		await fallbackTextbox.fill(optionText)
		await fallbackTextbox.press('ArrowDown')
		await fallbackTextbox.press('Enter')
		await fallbackTextbox.press('Tab').catch(() => {})
	}
}

async function resetSystemRuleToBaseline(dialog: Locator) {
	await clearSystemSignatureFlowValue(dialog.page())
}

async function clearExistingRules(dialog: Locator) {
	const page = dialog.page()

	for (let round = 0; round < 6; round += 1) {
		let removedInRound = false
		const actions = dialog.getByRole('button', { name: 'Rule actions' })

		while ((await actions.count()) > 0) {
			const firstAction = actions.first()
			if (!(await firstAction.isVisible().catch(() => false))) {
				break
			}

			const clickedAction = await firstAction.click({ timeout: 1500 }).then(() => true).catch(() => false)
			if (!clickedAction) {
				await page.waitForTimeout(150)
				continue
			}
			const hasRemoveAction = await clickRuleMenuAction(dialog, 'Remove')
			if (!hasRemoveAction) {
				break
			}

			const removeExceptionButton = page.getByRole('button', { name: removeExceptionButtonName }).first()
			if (await removeExceptionButton.isVisible().catch(() => false)) {
				await removeExceptionButton.click()
			} else {
				const removeExceptionText = page.getByText(/^Remove exception$/i).first()
				if (await removeExceptionText.isVisible().catch(() => false)) {
					await removeExceptionText.click()
				}
			}
			await waitForEditorIdle(dialog)
			await page.waitForTimeout(150)
			removedInRound = true
		}

		if (!removedInRound) {
			await page.waitForTimeout(700)
			if ((await actions.count()) === 0) {
				break
			}
		}
	}

	if (await dialog.getByText(/\(custom\)/i).first().isVisible().catch(() => false)) {
		await resetSystemRuleToBaseline(dialog)
	}

	await expect(dialog).toBeVisible()
}

test('system default persists across edit cycles and can be reset to the system baseline', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./settings/admin/libresign')

	await openSigningOrderDialog(page)

	const signingOrderDialog = await getSigningOrderDialog(page)
	await clearExistingRules(signingOrderDialog)

	await page.reload()
	await openSigningOrderDialog(page)
	const stableDialog = await getSigningOrderDialog(page)

	await openSystemDefaultEditor(stableDialog)
	expect(await setSigningFlow(stableDialog, 'ordered_numeric'), 'Expected signing-flow radios in system editor').toBe(true)
	await submitSystemRuleAndWait(stableDialog)
	expect(await getSystemSignatureFlowValue(page)).toBe('ordered_numeric')

	await page.reload()
	await openSigningOrderDialog(page)
	const reloadedDialog = await getSigningOrderDialog(page)
	expect(await getSystemSignatureFlowValue(page)).toBe('ordered_numeric')

	await openSystemDefaultEditor(reloadedDialog)
	expect(await setSigningFlow(reloadedDialog, 'parallel'), 'Expected signing-flow radios in system editor').toBe(true)
	await submitSystemRuleAndWait(reloadedDialog)
	expect(await getSystemSignatureFlowValue(page)).toBe('parallel')

	await resetSystemRuleToBaseline(reloadedDialog)
	expect([null, 'none']).toContain(await getSystemSignatureFlowValue(page))
})

test('admin can manage instance, group, and user rules when system default is fixed', async ({ page }) => {
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
	await clearExistingRules(dialog)

	await page.reload()
	await openSigningOrderDialog(page)
	const stableDialog = await getSigningOrderDialog(page)

	// Global rule: edit
	await openSystemDefaultEditor(stableDialog)
	expect(await setSigningFlow(stableDialog, 'ordered_numeric'), 'Expected signing-flow radios in global editor').toBe(true)
	await submitSystemRuleAndWait(stableDialog)
	expect(await getSystemSignatureFlowValue(page)).toBe('ordered_numeric')

	// Instance admins can still create group-level exceptions even when the system default is fixed.
	await stableDialog.getByRole('button', { name: 'Create rule' }).first().click()
	const groupScopeOption = await getCreateScopeOption(stableDialog.page(), 'Group')
	await expect(groupScopeOption).toBeEnabled()

	// User rule: create
	const userScopeOption = await getCreateScopeOption(stableDialog.page(), 'User')
	await expect(userScopeOption).toBeEnabled()
	await userScopeOption.click()
	await chooseTarget(stableDialog, 'Target users', userTarget)
	expect(await setSigningFlow(stableDialog, 'parallel'), 'Expected signing-flow radios in user editor').toBe(true)
	await submitRule(stableDialog)
	await expect(stableDialog).toContainText(userTarget)
	await expect(stableDialog).toContainText('Simultaneous (Parallel)')

	// User rule: edit
	await editRule(stableDialog, 'User', userTarget)
	expect(await setSigningFlow(stableDialog, 'ordered_numeric'), 'Expected signing-flow radios in user editor').toBe(true)
	await submitRule(stableDialog)
	await expect(stableDialog).toContainText(userTarget)
	await expect(stableDialog).toContainText('Sequential')

	await page.reload()
	await openSigningOrderDialog(page)
	const reloadedDialog = await getSigningOrderDialog(page)
	expect(await getSystemSignatureFlowValue(page)).toBe('ordered_numeric')
	await expect(reloadedDialog).toContainText(userTarget)
	await expect(reloadedDialog).toContainText('Sequential')

	// User rule: delete
	await removeRule(reloadedDialog, 'User', userTarget)
	await expect(reloadedDialog).not.toContainText(userTarget)

	// Global rule: reset to explicit "let users choose" baseline
	await resetSystemRuleToBaseline(reloadedDialog)
	expect([null, 'none']).toContain(await getSystemSignatureFlowValue(page))
})
