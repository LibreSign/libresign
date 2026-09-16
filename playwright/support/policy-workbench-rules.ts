/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'
import { expect } from '@playwright/test'

const defaultRemoveExceptionButtonName = /Remove exception|Remove rule/i

export async function waitForPolicyWorkbenchIdle(page: Page): Promise<void> {
	const savingOverlays = page.locator('[aria-busy="true"]')
	await savingOverlays.first().waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {})
}

async function clickRemoveAction(page: Page): Promise<boolean> {
	const actionItem = page
		.locator('.action-item:visible, [role="menuitem"]:visible, li.action:visible')
		.filter({ hasText: /^(Remove|Delete)$/i })
		.first()

	if (!(await actionItem.isVisible().catch(() => false))) {
		return false
	}

	await actionItem.scrollIntoViewIfNeeded().catch(() => {})

	const clickedNormally = await actionItem
		.click({ timeout: 1500 })
		.then(() => true)
		.catch(() => false)

	if (clickedNormally) {
		return true
	}

	const clickedForced = await actionItem
		.click({ timeout: 1500, force: true })
		.then(() => true)
		.catch(() => false)

	return clickedForced
}

export async function clearPolicyWorkbenchRules(
	dialog: Locator,
	options?: {
		maxRounds?: number
		removeExceptionButtonName?: RegExp
	},
): Promise<void> {
	const page = dialog.page()
	const maxRounds = options?.maxRounds ?? 8
	const removeExceptionButtonName = options?.removeExceptionButtonName ?? defaultRemoveExceptionButtonName

	for (let round = 0; round < maxRounds; round += 1) {
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

			const removed = await clickRemoveAction(page)
			if (!removed) {
				// Close the action popup only — avoid pressing Escape which would close the parent dialog
				const openMenus = page.locator('[role="menu"]:visible, .action-item__menutoggle--open')
				if (await openMenus.count() > 0) {
					await page.keyboard.press('Escape').catch(() => {})
				}
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

			await waitForPolicyWorkbenchIdle(page)
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
}

async function clickRuleMenuAction(page: Page, actionName: 'Edit'): Promise<boolean> {
	const actionItem = page
		.locator('.action-item:visible, [role="menuitem"]:visible, li.action:visible')
		.filter({ hasText: /^Edit$/i })
		.first()

	if (!(await actionItem.isVisible().catch(() => false))) {
		return false
	}

	return actionItem.click({ timeout: 1500 }).then(() => true).catch(() => false)
}

async function openExistingEveryoneRuleEditor(dialog: Locator): Promise<void> {
	const page = dialog.page()
	const changeButton = dialog.getByRole('button', { name: /^Change$/i }).first()
	if (await changeButton.isVisible({ timeout: 3000 }).catch(() => false)) {
		await changeButton.click()
		return
	}

	const everyoneRow = dialog.locator('tbody tr').filter({ hasText: /^Everyone\b/i }).first()
	await expect(everyoneRow).toBeVisible({ timeout: 8000 })
	await everyoneRow.getByRole('button', { name: 'Rule actions' }).first().click()
	const edited = await clickRuleMenuAction(page, 'Edit')
	expect(edited, 'Expected Edit action for existing Everyone rule').toBe(true)
}

async function confirmEveryoneScopeSelection(createScopeDialog: Locator): Promise<void> {
	const confirmScopeButton = createScopeDialog.getByRole('button', { name: /Create rule|Continue|Next/i }).first()
	if (await confirmScopeButton.isVisible().catch(() => false)) {
		await confirmScopeButton.click()
	}
}

async function selectEveryoneScopeOrEditExisting(
	dialog: Locator,
	createScopeDialog: Locator,
): Promise<void> {
	const page = dialog.page()
	const everyoneOption = createScopeDialog.getByRole('option', { name: /^Everyone\b/i }).first()
	const everyoneRadio = createScopeDialog.getByRole('radio', { name: /^Everyone\b/i }).first()

	if (await everyoneOption.isVisible().catch(() => false)) {
		await everyoneOption.click()
		await confirmEveryoneScopeSelection(createScopeDialog)
		return
	}

	if (await everyoneRadio.isVisible().catch(() => false)) {
		await everyoneRadio.click()
		await confirmEveryoneScopeSelection(createScopeDialog)
		return
	}

	await page.keyboard.press('Escape').catch(() => {})
	const cancelButton = createScopeDialog.getByRole('button', { name: /Cancel|Close/i }).first()
	if (await cancelButton.isVisible().catch(() => false)) {
		await cancelButton.click()
	}
	await createScopeDialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {})
	await openExistingEveryoneRuleEditor(dialog)
}

export async function openPolicyWorkbenchSystemRuleEditor(
	dialog: Locator,
	options?: {
		createButtonName?: RegExp
		ruleDialogName?: RegExp
	},
): Promise<Locator> {
	const createButtonName = options?.createButtonName ?? /Create rule|Create policy rule/i
	const ruleDialogName = options?.ruleDialogName ?? /Edit rule|Create rule/i

	const changeButton = dialog.getByRole('button', { name: /^Change$/i }).first()
	if (await changeButton.isVisible({ timeout: 3000 }).catch(() => false)) {
		await changeButton.click()
	} else {
		const createButton = dialog.getByRole('button', { name: createButtonName }).first()
		await expect(createButton).toBeVisible({ timeout: 10000 })
		await createButton.click()

		const page = dialog.page()
		const createScopeDialog = page.getByRole('dialog').filter({ hasText: /What do you want to create\?/i }).last()
		if (await createScopeDialog.isVisible({ timeout: 3000 }).catch(() => false)) {
			await selectEveryoneScopeOrEditExisting(dialog, createScopeDialog)
		}
	}

	const ruleDialog = dialog.page().getByRole('dialog', { name: ruleDialogName }).last()
	await expect(ruleDialog).toBeVisible({ timeout: 10000 })
	return ruleDialog
}
