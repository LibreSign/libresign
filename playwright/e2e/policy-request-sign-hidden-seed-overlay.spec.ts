/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test as base, type APIRequestContext, type Locator, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import {
	configureOpenSsl,
	deleteGroup,
	deleteUser,
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
	setUserLanguage,
} from '../support/nc-provisioning'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	policyRequest,
	setGroupPolicyEntry,
} from '../support/policy-api'

const test = base.extend<{
	adminRequestContext: APIRequestContext
	groupAdminRequestContext: APIRequestContext
}>({
	adminRequestContext: async ({ browserName }, use) => {
		if (!browserName) {
			throw new Error('Missing browserName fixture')
		}
		const ctx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
	groupAdminRequestContext: async ({ browserName }, use) => {
		if (!browserName) {
			throw new Error('Missing browserName fixture')
		}
		const ctx = await createAuthenticatedRequestContext(GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
})

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || process.env.NEXTCLOUD_ADMIN_PASSWORD || 'admin'
const GROUP_ADMIN = 'policy-request-sign-overlay-admin'
const GROUP_ADMIN_PASSWORD = '123456'
const BOARD_GROUP = 'policy-request-sign-overlay-board'
const COMPANY_GROUP = 'policy-request-sign-overlay-company'
const POLICY_KEY = 'groups_request_sign'

/**
 * Removes a group-scoped request-sign policy entry, tolerating missing rows.
 *
 * @param ctx Authenticated API request context.
 * @param groupId Group identifier whose rule should be cleared.
 * @param acceptedStatuses HTTP statuses accepted for cleanup.
 */
async function clearGroupPolicyEntry(
	ctx: APIRequestContext,
	groupId: string,
	acceptedStatuses: number[] = [200, 404],
): Promise<void> {
	const response = await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/group/${groupId}/${POLICY_KEY}`)
	expect(
		acceptedStatuses,
		`clearGroupPolicyEntry(${groupId}): expected ${acceptedStatuses.join(' or ')} but got ${response.httpStatus}`,
	).toContain(response.httpStatus)
}

/**
 * Waits for a single policy API request triggered by the provided UI action.
 *
 * @param page Active Playwright page.
 * @param method Expected HTTP method.
 * @param urlPart URL fragment that identifies the request.
 * @param action UI action that should trigger the request.
 */
async function waitForPolicyRequest(
	page: Page,
	method: 'PUT' | 'DELETE',
	urlPart: string,
	action: () => Promise<void>,
): Promise<void> {
	const requestPromise = page.waitForRequest((request) => {
		return request.method() === method && request.url().includes(urlPart)
	})

	await action()
	await requestPromise
}

/**
 * Locates a policy table row by the visible target label.
 *
 * @param dialog Policy dialog containing the rules table.
 * @param targetLabel Visible target label to match in the table.
 */
function getRuleRow(dialog: Locator, targetLabel: string): Locator {
	return dialog.locator('tbody tr').filter({ hasText: targetLabel }).first()
}

/**
 * Clicks a visible row-menu action when it is available in the floating menu.
 *
 * @param page Active Playwright page.
 * @param actionName Supported action label to click.
 */
async function clickVisibleRuleMenuAction(page: Page, actionName: 'Remove'): Promise<boolean> {
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

	return actionItem.click({ timeout: 1500 }).then(() => true).catch(() => false)
}

/**
 * Removes a visible rule row and confirms the deletion dialog when needed.
 *
 * @param dialog Policy dialog containing the visible rule row.
 * @param targetLabel Visible target label to remove.
 */
async function removeVisibleRule(dialog: Locator, targetLabel: string): Promise<void> {
	const page = dialog.page()

	for (let attempt = 0; attempt < 3; attempt += 1) {
		const row = getRuleRow(dialog, targetLabel)
		await expect(row).toBeVisible({ timeout: 10000 })
		await row.getByRole('button', { name: 'Rule actions' }).first().click()

		if (await clickVisibleRuleMenuAction(page, 'Remove')) {
			const confirmDialog = page.getByRole('dialog', { name: /Confirm rule removal/i }).last()
			if (await confirmDialog.isVisible({ timeout: 3000 }).catch(() => false)) {
				await confirmDialog.getByRole('button', { name: /Remove exception|Remove rule/i }).click()
			} else {
				const removeExceptionButton = page.getByRole('button', { name: /Remove exception|Remove rule/i }).first()
				if (await removeExceptionButton.isVisible({ timeout: 3000 }).catch(() => false)) {
					await removeExceptionButton.click()
				} else {
					await page.getByText(/^Remove exception$/i).first().click()
				}
			}

			await expect(dialog.locator('tbody tr').filter({ hasText: targetLabel })).toHaveCount(0, { timeout: 10000 })
			return
		}

		await page.waitForTimeout(200)
	}

	expect(false, `Expected Remove action to be visible for rule ${targetLabel}`).toBe(true)
}

/**
 * Escapes a string so it can be embedded safely inside a RegExp.
 *
 * @param value Raw string value to escape.
 */
function escapeRegExp(value: string): string {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Selects an option in a Nextcloud multi-select field by its visible label.
 *
 * @param scope Locator scoping the editor dialog.
 * @param label Visible field label.
 * @param target Option label to select.
 */
async function selectMultiSelectOption(scope: Locator, label: string, target: string): Promise<void> {
	const page = scope.page()
	const combobox = scope.getByRole('combobox', { name: new RegExp(label, 'i') }).first()
	const textAnchor = scope.getByText(new RegExp(`^${label}$`, 'i')).first()
	const anchoredCombobox = textAnchor.locator('xpath=following::*[@role="combobox"][1]').first()
	const targetInput = await combobox.count() ? combobox : anchoredCombobox
	const fieldScope = targetInput.locator('xpath=ancestor-or-self::*[@role="combobox"][1]').first()
	const selectedTarget = fieldScope.getByRole('button', {
		name: new RegExp(`Deselect\\s+${escapeRegExp(target)}`, 'i'),
	}).first()

	await expect(targetInput).toBeVisible({ timeout: 10000 })
	if (await selectedTarget.isVisible({ timeout: 1000 }).catch(() => false)) {
		return
	}

	await targetInput.click()

	const searchInput = fieldScope.getByRole('searchbox').first()
	if (await searchInput.count()) {
		await searchInput.fill(target)

		const matchingOption = page.getByRole('option', { name: new RegExp(target, 'i') }).first()
		const matchingVisible = await matchingOption.waitFor({ state: 'visible', timeout: 5000 }).then(() => true).catch(() => false)
		if (matchingVisible) {
			await matchingOption.click()
		} else {
			const floatingOption = page.locator('ul[role="listbox"] li, .vs__dropdown-menu--floating li').filter({ hasText: new RegExp(target, 'i') }).first()
			await expect(floatingOption).toBeVisible({ timeout: 5000 })
			await floatingOption.click()
		}

		await searchInput.press('Tab').catch(() => {})
	} else {
		const fallbackTextbox = fieldScope.getByRole('textbox').first()
		await fallbackTextbox.fill(target)
		await fallbackTextbox.press('ArrowDown')
		await fallbackTextbox.press('Enter')
		await fallbackTextbox.press('Tab').catch(() => {})
	}

	await expect(selectedTarget).toBeVisible({ timeout: 5000 })
}

/**
 * Opens the policy dialog for the signature-request access setting.
 *
 * @param page Active Playwright page.
 */
async function openSignatureRequestAccessDialog(page: Page): Promise<Locator> {
	await page.goto('./apps/libresign/f/policies')
	await expect(page).toHaveURL(/\/apps\/libresign\/f\/policies/, { timeout: 20000 })

	const searchField = page.getByRole('textbox', { name: 'Search settings' })
	await expect(searchField).toBeVisible({ timeout: 20000 })
	await searchField.fill('Signature request access')

	const configureButton = page.getByRole('button', { name: /^Configure(?: setting)?$/i }).first()
	await expect(configureButton).toBeVisible({ timeout: 15000 })
	await configureButton.click()

	const dialog = page.locator('div[role="dialog"]').filter({
		has: page.getByRole('button', { name: /^Create rule$/i }),
	}).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	return dialog
}

/**
 * Opens the group-rule editor, supporting both the direct-create shortcut and the scope chooser flow.
 *
 * @param page Active Playwright page.
 * @param settingDialog Policy dialog containing the create-rule action.
 */
async function openGroupCreateRuleDialog(page: Page, settingDialog: Locator): Promise<Locator> {
	await settingDialog.getByRole('button', { name: /^Create rule$/i }).click()

	const createScopeDialog = page.getByRole('dialog').filter({ hasText: /What do you want to create\?/i }).last()
	if (await createScopeDialog.isVisible({ timeout: 1500 }).catch(() => false)) {
		await createScopeDialog.getByRole('option', { name: /^Group/i }).click()
	}

	const createRuleDialog = page.getByRole('dialog', { name: /Create rule/i }).last()
	await expect(createRuleDialog).toBeVisible({ timeout: 10000 })
	return createRuleDialog
}

test.beforeEach(async ({ adminRequestContext }) => {
	await clearGroupPolicyEntry(adminRequestContext, BOARD_GROUP).catch(() => {})
	await clearGroupPolicyEntry(adminRequestContext, COMPANY_GROUP).catch(() => {})
	await deleteUser(adminRequestContext, GROUP_ADMIN, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(adminRequestContext, BOARD_GROUP, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(adminRequestContext, COMPANY_GROUP, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})

	await ensureUserExists(adminRequestContext, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
	await ensureGroupExists(adminRequestContext, BOARD_GROUP)
	await ensureGroupExists(adminRequestContext, COMPANY_GROUP)
	await ensureUserInGroup(adminRequestContext, GROUP_ADMIN, BOARD_GROUP)
	await ensureUserInGroup(adminRequestContext, GROUP_ADMIN, COMPANY_GROUP)
	await ensureSubadminOfGroup(adminRequestContext, GROUP_ADMIN, BOARD_GROUP)
	await ensureSubadminOfGroup(adminRequestContext, GROUP_ADMIN, COMPANY_GROUP)
	await setUserLanguage(adminRequestContext, GROUP_ADMIN, 'en')
	await configureOpenSsl(adminRequestContext, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setGroupPolicyEntry(
		adminRequestContext,
		BOARD_GROUP,
		POLICY_KEY,
		JSON.stringify({ allowGroups: [BOARD_GROUP], denyGroups: [] }),
		true,
	)
})

test.afterEach(async ({ adminRequestContext }) => {
	await clearGroupPolicyEntry(adminRequestContext, BOARD_GROUP).catch(() => {})
	await clearGroupPolicyEntry(adminRequestContext, COMPANY_GROUP).catch(() => {})
	await deleteUser(adminRequestContext, GROUP_ADMIN, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(adminRequestContext, BOARD_GROUP, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(adminRequestContext, COMPANY_GROUP, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
})

test('delegated group admin can keep a sibling allow while denying a hidden request-sign seed', async ({
	page,
	adminRequestContext,
	groupAdminRequestContext,
}) => {
	await login(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)

	const settingDialog = await openSignatureRequestAccessDialog(page)
	await expect(settingDialog.getByText(BOARD_GROUP, { exact: false })).toHaveCount(0)

	const createRuleDialog = await openGroupCreateRuleDialog(page, settingDialog)

	await expect(createRuleDialog.getByText('Scope groups')).toHaveCount(0)

	await expect(createRuleDialog.getByText('Denied requester groups')).toBeVisible({ timeout: 10000 })
	await expect(createRuleDialog.getByText('Authorized requester groups')).toBeVisible({ timeout: 10000 })

	const submitButton = createRuleDialog.getByRole('button', { name: /Create rule|Save changes/i }).last()
	await expect(submitButton).toBeDisabled()

	await selectMultiSelectOption(createRuleDialog, 'Authorized requester groups', COMPANY_GROUP)
	await expect(submitButton).toBeEnabled()

	await selectMultiSelectOption(createRuleDialog, 'Denied requester groups', BOARD_GROUP)
	await expect(submitButton).toBeEnabled()

	await waitForPolicyRequest(
		page,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${COMPANY_GROUP}/${POLICY_KEY}`,
		async () => {
			await submitButton.click()
		},
	)

	const companyPolicyAfterCreate = await policyRequest(
		groupAdminRequestContext,
		'GET',
		`/apps/libresign/api/v1/policies/group/${COMPANY_GROUP}/${POLICY_KEY}`,
	)
	expect(companyPolicyAfterCreate.httpStatus).toBe(200)
	expect((companyPolicyAfterCreate.data.policy as { value?: string } | undefined)?.value).toBe(
		JSON.stringify({ allowGroups: [COMPANY_GROUP], denyGroups: [] }),
	)

	const effectivePolicyAfterCreate = await getEffectivePolicy(groupAdminRequestContext, POLICY_KEY)
	expect(effectivePolicyAfterCreate?.sourceScope).toBe('group')
	expect(effectivePolicyAfterCreate?.editableByCurrentActor).toBe(true)

	const groupAdminBoardPolicyAfterCreate = await policyRequest(
		groupAdminRequestContext,
		'GET',
		`/apps/libresign/api/v1/policies/group/${BOARD_GROUP}/${POLICY_KEY}`,
	)
	expect(groupAdminBoardPolicyAfterCreate.httpStatus).toBe(200)
	expect((groupAdminBoardPolicyAfterCreate.data.policy as { value?: string } | undefined)?.value).toBe(
		JSON.stringify({ allowGroups: [BOARD_GROUP], denyGroups: [BOARD_GROUP] }),
	)

	await expect(getRuleRow(settingDialog, BOARD_GROUP)).toBeVisible({ timeout: 10000 })
	await expect(getRuleRow(settingDialog, COMPANY_GROUP)).toBeVisible({ timeout: 10000 })

	await waitForPolicyRequest(
		page,
		'DELETE',
		`/apps/libresign/api/v1/policies/group/${BOARD_GROUP}/${POLICY_KEY}`,
		async () => {
			await removeVisibleRule(settingDialog, BOARD_GROUP)
		},
	)

	const boardPolicyAfterDelete = await policyRequest(
		adminRequestContext,
		'GET',
		`/apps/libresign/api/v1/policies/group/${BOARD_GROUP}/${POLICY_KEY}`,
	)
	expect(boardPolicyAfterDelete.httpStatus).toBe(200)
	expect((boardPolicyAfterDelete.data.policy as { value?: string } | undefined)?.value).toBe(
		JSON.stringify({ allowGroups: [BOARD_GROUP], denyGroups: [] }),
	)

	const companyPolicyAfterDelete = await policyRequest(
		adminRequestContext,
		'GET',
		`/apps/libresign/api/v1/policies/group/${COMPANY_GROUP}/${POLICY_KEY}`,
	)
	expect(companyPolicyAfterDelete.httpStatus).toBe(200)
	expect((companyPolicyAfterDelete.data.policy as { value?: string } | undefined)?.value).toBe(
		JSON.stringify({ allowGroups: [COMPANY_GROUP], denyGroups: [] }),
	)

	const groupAdminCompanyPolicyAfterDelete = await policyRequest(
		groupAdminRequestContext,
		'GET',
		`/apps/libresign/api/v1/policies/group/${COMPANY_GROUP}/${POLICY_KEY}`,
	)
	expect(groupAdminCompanyPolicyAfterDelete.httpStatus).toBe(200)
	expect((groupAdminCompanyPolicyAfterDelete.data.policy as { value?: string } | undefined)?.value).toBe(
		JSON.stringify({ allowGroups: [COMPANY_GROUP], denyGroups: [] }),
	)

	const effectivePolicyAfterDelete = await getEffectivePolicy(groupAdminRequestContext, POLICY_KEY)
	expect(effectivePolicyAfterDelete?.sourceScope).toBe('group')
	expect(effectivePolicyAfterDelete?.editableByCurrentActor).toBe(true)

	await expect(settingDialog.locator('tbody tr').filter({ hasText: BOARD_GROUP })).toHaveCount(0, { timeout: 10000 })
	await expect(getRuleRow(settingDialog, COMPANY_GROUP)).toBeVisible({ timeout: 10000 })

	const groupAdminBoardPolicyAfterDelete = await policyRequest(
		groupAdminRequestContext,
		'GET',
		`/apps/libresign/api/v1/policies/group/${BOARD_GROUP}/${POLICY_KEY}`,
	)
	expect(groupAdminBoardPolicyAfterDelete.httpStatus).toBe(403)
})
