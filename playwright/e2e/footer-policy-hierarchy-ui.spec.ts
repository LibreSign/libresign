/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test as base, type APIRequestContext, type Locator, type Page } from '@playwright/test'
import { login } from '../support/nc-login'
import {
	configureOpenSsl,
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
	setUserLanguage,
} from '../support/nc-provisioning'
import {
	clearUserPolicyPreference,
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	policyRequest,
	setGroupPolicyEntry,
	setSystemPolicyEntry,
} from '../support/policy-api'

const test = base.extend<{
	adminRequestContext: APIRequestContext
	endUserRequestContext: APIRequestContext
}>({
	adminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
	endUserRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(END_USER, DEFAULT_TEST_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
})

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const DEFAULT_TEST_PASSWORD = '123456'

const GROUP_ID = 'libresign-footer-ui-flow-group'
const END_USER = 'signer1'
const FOOTER_POLICY_KEY = 'add_footer'
const REQUEST_SIGN_POLICY_KEY = 'groups_request_sign'
const REQUEST_SIGN_GROUP_POLICY_VALUE = JSON.stringify({
	allowGroups: [GROUP_ID],
	denyGroups: [],
})
const SYSTEM_FOOTER_DISABLED_VALUE = JSON.stringify({
	enabled: false,
	writeQrcodeOnFooter: false,
	validationSite: '',
	customizeFooterTemplate: false,
})

function buildFooterPolicyValue(template: string): string {
	return JSON.stringify({
		enabled: true,
		writeQrcodeOnFooter: true,
		validationSite: '',
		customizeFooterTemplate: true,
		footerTemplate: template,
	})
}

function normalizeFooterPolicyValue(value: unknown): Record<string, unknown> {
	if (typeof value === 'string') {
		return JSON.parse(value) as Record<string, unknown>
	}

	if (value && typeof value === 'object') {
		return value as Record<string, unknown>
	}

	return {}
}

function getTrimmedFooterTemplate(value: unknown): string {
	const parsed = normalizeFooterPolicyValue(value)
	const template = parsed.footerTemplate
	return typeof template === 'string' ? template.trim() : ''
}

function escapeRegExp(value: string): string {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

async function deleteGroupPolicyEntry(
	ctx: APIRequestContext,
	groupId: string,
	policyKey: string,
): Promise<void> {
	const response = await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/group/${groupId}/${policyKey}`)
	expect([200, 404, 500]).toContain(response.httpStatus)
}

async function deleteUserPolicyEntry(
	ctx: APIRequestContext,
	userId: string,
	policyKey: string,
): Promise<void> {
	const response = await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/user/${userId}/${policyKey}`)
	expect([200, 404, 500]).toContain(response.httpStatus)
}

async function setUserPolicyEntry(
	ctx: APIRequestContext,
	userId: string,
	policyKey: string,
	value: string,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(ctx, 'PUT', `/apps/libresign/api/v1/policies/user/${userId}/${policyKey}`, {
		value,
		allowChildOverride,
	})
	expect(response.httpStatus, `setUserPolicyEntry(${userId}/${policyKey}): expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function resetFooterHierarchyState(
	adminRequestContext: APIRequestContext,
	endUserRequestContext: APIRequestContext,
): Promise<void> {
	await clearUserPolicyPreference(endUserRequestContext, FOOTER_POLICY_KEY, [200, 401, 500])
	await deleteUserPolicyEntry(adminRequestContext, END_USER, FOOTER_POLICY_KEY)
	await deleteGroupPolicyEntry(adminRequestContext, GROUP_ID, FOOTER_POLICY_KEY)
	await setSystemPolicyEntry(adminRequestContext, FOOTER_POLICY_KEY, SYSTEM_FOOTER_DISABLED_VALUE, true)
}

async function waitForPolicyRequest(page: Page, method: 'PUT' | 'DELETE', urlPart: string, action: () => Promise<void>) {
	const requestPromise = page.waitForRequest((request) => {
		return request.method() === method
			&& request.url().includes(urlPart)
	})

	await action()
	return requestPromise
}

async function openFooterPolicyDialog(page: Page): Promise<Locator> {
	await page.goto('/apps/libresign/f/policies')
	await expect(page).toHaveURL(/\/apps\/libresign\/f\/policies/, { timeout: 20000 })

	const searchField = page.getByRole('textbox', { name: 'Search settings' })
	await expect(searchField).toBeVisible({ timeout: 20000 })
	await searchField.fill('Signature footer')

	const configureButton = page.getByRole('button', { name: 'Configure setting' }).first()
	await expect(configureButton).toBeVisible({ timeout: 20000 })
	await configureButton.click()

	const dialog = page.getByRole('dialog', { name: 'Signature footer' }).first()
	await expect(dialog).toBeVisible({ timeout: 20000 })
	return dialog
}

async function openCreateRuleEditor(dialog: Locator, scopeName: 'Group' | 'User'): Promise<void> {
	await dialog.getByRole('button', { name: 'Create rule' }).click()

	const scopeDialog = dialog.page().getByRole('dialog').last()
	await expect(scopeDialog).toBeVisible({ timeout: 10000 })
	await scopeDialog.getByRole('option', { name: new RegExp(`^${scopeName === 'User' ? 'Account' : scopeName}`) }).click()
}

async function selectTarget(dialogScope: Locator, kind: 'group' | 'user', target: string): Promise<void> {
	const page = dialogScope.page()
	const labelPattern = getTargetFieldPattern(kind)
	const scopeLabel = dialogScope.locator('.policy-workbench__label').filter({ hasText: labelPattern }).first()
	const searchInput = dialogScope.locator('.nc-select-users .vs__search input').first()
	await expect(scopeLabel).toBeVisible({ timeout: 8000 })
	await expect(searchInput).toBeVisible({ timeout: 8000 })

	const searchEndpoint = kind === 'group' ? '/cloud/groups/details' : '/cloud/users/details'
	const searchResponse = page.waitForResponse((response) => {
		return response.url().includes(searchEndpoint)
			&& new URL(response.url()).searchParams.get('search') === target
	}, { timeout: 15000 })
	await searchInput.fill(target)
	const response = await searchResponse
	expect(response.ok(), `Failed to search ${kind} targets: ${response.status()}`).toBe(true)

	const results = await response.json()
	expect(JSON.stringify(results), `Search did not return ${kind} target ${target}`).toContain(target)
	await searchInput.click()
	const option = page.getByRole('option', { name: new RegExp(escapeRegExp(target), 'i') }).first()
	await expect(option).toBeVisible({ timeout: 8000 })
	const optionId = await option.getAttribute('id')
	expect(optionId).toBeTruthy()
	if (!await searchInput.getAttribute('aria-activedescendant')) {
		await searchInput.press('ArrowDown')
	}
	await expect(searchInput).toHaveAttribute('aria-activedescendant', optionId!)
	await searchInput.press('Enter')

	await expect(dialogScope.getByText(target, { exact: true })).toBeVisible()
}

async function ensureSwitchEnabled(scope: Page | Locator, switchLabel: string): Promise<void> {
	const toggle = scope.getByRole('switch', { name: switchLabel }).first()
	await expect(toggle).toBeVisible({ timeout: 10000 })
	const checked = await toggle.isChecked().catch(() => false)
	if (!checked) {
		await toggle.setChecked(true, { force: true })
	}
	await expect(toggle).toBeChecked()
}

async function ensureFooterTemplateEditorVisible(scope: Page | Locator): Promise<Locator> {
	await ensureSwitchEnabled(scope, 'Add visible footer with signature details')
	await ensureSwitchEnabled(scope, 'Customize footer template')

	const editorContainer = scope.locator('.code-editor').first()
	const footerTemplateField = editorContainer.locator('.cm-content[contenteditable="true"]').first()
	await expect(footerTemplateField).toBeVisible({ timeout: 10000 })
	return footerTemplateField
}

/**
 * Choose a group through the real NcSelectUsers dropdown. The server already
 * filters results by group ID, so wait for those results and use the select's
 * own dropdown control if asynchronous updates have closed the menu.
 */
async function selectGroupTargetWithOpenDropdown(dialogScope: Locator, target: string): Promise<void> {
	const page = dialogScope.page()
	const picker = dialogScope.locator('.nc-select-users').first()
	const searchInput = picker.getByRole('combobox')
	await expect(searchInput).toBeVisible({ timeout: 8000 })

	const searchResponse = page.waitForResponse((response) => {
		return response.url().includes('/cloud/groups/details')
			&& new URL(response.url()).searchParams.get('search') === target
	}, { timeout: 15000 })

	await searchInput.fill(target)
	const response = await searchResponse
	expect(response.ok(), `Failed to search group target ${target}: ${response.status()}`).toBe(true)
	const body = await response.json() as { ocs?: { data?: { groups?: Array<{ id: string, displayname?: string }> } } }
	const matchingGroup = body.ocs?.data?.groups?.find((group) => group.id === target)
	expect(matchingGroup, `OCS returned no matching group for ${target}`).toBeDefined()
	const groupLabel = matchingGroup?.displayname || target

	await expect(picker).not.toHaveClass(/vs--loading/, { timeout: 8000 })
	const option = page.getByRole('option', { name: new RegExp(escapeRegExp(groupLabel), 'i') }).first()
	if (await searchInput.getAttribute('aria-expanded') !== 'true') {
		// Clicking the dropdown indicator uses vue-select's actual mouse
		// handler, which opens the list and restores focus to its search input.
		await picker.locator('.vs__open-indicator-button').click()
	}
	try {
		await expect(searchInput).toHaveAttribute('aria-expanded', 'true', { timeout: 8000 })
	} catch (error) {
		// Report real picker state without weakening the failed assertion.
		const state = await picker.evaluate((element) => {
			const input = element.querySelector<HTMLInputElement>('[role="combobox"]')
			return {
				classes: element.className,
				expanded: input?.getAttribute('aria-expanded'),
				focused: input === document.activeElement,
				localOptions: element.querySelectorAll('[role="option"]').length,
				dropdown: element.querySelectorAll('.vs__dropdown-menu').length,
			}
		})
		console.error('Group picker state after reopening:', JSON.stringify(state))
		throw error
	}
	await expect(option).toBeVisible({ timeout: 8000 })
	await option.click()
	await expect(picker.locator('.vs__selected').filter({ hasText: groupLabel })).toBeVisible()
}

async function createFooterRuleViaUi(
	page: Page,
	scopeName: 'Group' | 'User',
	target: string,
	template: string,
	requestUrlPart: string,
): Promise<void> {
	const dialog = await openFooterPolicyDialog(page)
	await openCreateRuleEditor(dialog, scopeName)

	const createRuleDialog = page.getByRole('dialog', { name: 'Create rule' }).last()
	await expect(createRuleDialog).toBeVisible({ timeout: 10000 })

	if (scopeName === 'Group') {
		await selectGroupTargetWithOpenDropdown(createRuleDialog, target)
	} else {
		await selectTarget(createRuleDialog, 'user', target)
	}

	const footerTemplateField = await ensureFooterTemplateEditorVisible(createRuleDialog)
	await footerTemplateField.click()
	await footerTemplateField.press('Control+a')
	await footerTemplateField.fill(template)

	await waitForPolicyRequest(page, 'PUT', requestUrlPart, async () => {
		await page.getByRole('button', { name: 'Create rule' }).last().click()
	})
	await dialog.getByRole('button', { name: 'Close' }).first().click()
	await expect(dialog).toBeHidden({ timeout: 10000 })
}

async function expectFooterTemplateValue(page: Page, expectedValue: string): Promise<void> {
	const footerTemplateField = await ensureFooterTemplateEditorVisible(page)
	await expect.poll(async () => {
		const text = await footerTemplateField.textContent()
		return (text ?? '').trim()
	}, { timeout: 10000 }).toContain(expectedValue)
}

test.beforeEach(async ({ page, adminRequestContext, endUserRequestContext }) => {
	await ensureUserExists(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, END_USER, GROUP_ID)
	await setUserLanguage(adminRequestContext, ADMIN_USER, 'en')
	await setUserLanguage(adminRequestContext, END_USER, 'en')
	await setGroupPolicyEntry(adminRequestContext, GROUP_ID, REQUEST_SIGN_POLICY_KEY, REQUEST_SIGN_GROUP_POLICY_VALUE, true)
	await configureOpenSsl(adminRequestContext, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})
	await resetFooterHierarchyState(adminRequestContext, endUserRequestContext)
})

test.afterEach(async ({ adminRequestContext, endUserRequestContext }) => {
	await resetFooterHierarchyState(adminRequestContext, endUserRequestContext)
	await deleteGroupPolicyEntry(adminRequestContext, GROUP_ID, REQUEST_SIGN_POLICY_KEY)
})

test('rule target picker retains focus during nested dialog entry', async ({ page }) => {
	await login(page.request, ADMIN_USER, ADMIN_PASSWORD)
	for (const [scope, kind, target] of [
		['Group', 'group', GROUP_ID],
		['User', 'user', END_USER],
	] as const) {
		const dialog = await openFooterPolicyDialog(page)
		// Exercise the interval before the nested modal activates its own focus trap.
		await page.addStyleTag({ content: '.policy-workbench__rule-dialog.fade-enter-active { transition-duration: 1500ms !important; }' })
		await openCreateRuleEditor(dialog, scope)
		await selectTarget(page.getByRole('dialog', { name: 'Create rule' }).last(), kind, target)
	}
})

test('footer hierarchy works through policies and preferences UI', async ({ page, adminRequestContext, endUserRequestContext }) => {
	const uniqueId = Date.now()
	const groupTemplate = `<div>Group footer ${uniqueId}</div>`
	const userTemplate = `<div>User footer ${uniqueId}</div>`
	const adminUserTemplate = `<div>Admin override ${uniqueId}</div>`

	await login(page.request, ADMIN_USER, ADMIN_PASSWORD)
	await createFooterRuleViaUi(
		page,
		'Group',
		GROUP_ID,
		groupTemplate,
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${FOOTER_POLICY_KEY}`,
	)

	let effectivePolicy = await getEffectivePolicy(endUserRequestContext, FOOTER_POLICY_KEY)
	expect(normalizeFooterPolicyValue(effectivePolicy?.effectiveValue)).toMatchObject({
		enabled: true,
		writeQrcodeOnFooter: true,
		customizeFooterTemplate: true,
	})
	expect(getTrimmedFooterTemplate(effectivePolicy?.effectiveValue)).toBe(groupTemplate)
	expect(effectivePolicy?.sourceScope).toBe('group')

	await login(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await page.goto('/apps/libresign/f/preferences')
	await expect(page).toHaveURL(/\/apps\/libresign\/f\/preferences/, { timeout: 20000 })
	await expectFooterTemplateValue(page, groupTemplate)

	await waitForPolicyRequest(page, 'PUT', `/apps/libresign/api/v1/policies/user/${FOOTER_POLICY_KEY}`, async () => {
		const footerTemplateField = await ensureFooterTemplateEditorVisible(page)
		await footerTemplateField.click()
		await footerTemplateField.press('Control+a')
		await footerTemplateField.fill(userTemplate)
		await footerTemplateField.press('Tab')
	})
	await expect(page.getByText('Preference saved', { exact: true })).toBeVisible({ timeout: 20000 })

	effectivePolicy = await getEffectivePolicy(endUserRequestContext, FOOTER_POLICY_KEY)
	expect(normalizeFooterPolicyValue(effectivePolicy?.effectiveValue)).toMatchObject({
		enabled: true,
		writeQrcodeOnFooter: true,
		customizeFooterTemplate: true,
	})
	expect(getTrimmedFooterTemplate(effectivePolicy?.effectiveValue)).toBe(userTemplate)
	expect(effectivePolicy?.sourceScope).toBe('user')
	await expectFooterTemplateValue(page, userTemplate)

	await waitForPolicyRequest(page, 'DELETE', `/apps/libresign/api/v1/policies/user/${FOOTER_POLICY_KEY}`, async () => {
		await page.getByRole('button', { name: 'Reset to default' }).first().click()
	})
	await expectFooterTemplateValue(page, groupTemplate)

	effectivePolicy = await getEffectivePolicy(endUserRequestContext, FOOTER_POLICY_KEY)
	expect(normalizeFooterPolicyValue(effectivePolicy?.effectiveValue)).toMatchObject({
		enabled: true,
		writeQrcodeOnFooter: true,
		customizeFooterTemplate: true,
	})
	expect(getTrimmedFooterTemplate(effectivePolicy?.effectiveValue)).toBe(groupTemplate)
	expect(effectivePolicy?.sourceScope).toBe('group')

	await login(page.request, ADMIN_USER, ADMIN_PASSWORD)
	await setUserPolicyEntry(adminRequestContext, END_USER, FOOTER_POLICY_KEY, buildFooterPolicyValue(adminUserTemplate), true)

	effectivePolicy = await getEffectivePolicy(endUserRequestContext, FOOTER_POLICY_KEY)
	expect(normalizeFooterPolicyValue(effectivePolicy?.effectiveValue)).toMatchObject({
		enabled: true,
		writeQrcodeOnFooter: true,
		customizeFooterTemplate: true,
	})
	expect(getTrimmedFooterTemplate(effectivePolicy?.effectiveValue)).toBe(adminUserTemplate)
	expect(effectivePolicy?.sourceScope).toBe('user_policy')

	await login(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await page.goto('/apps/libresign/f/preferences')
	await expectFooterTemplateValue(page, adminUserTemplate)

	await deleteUserPolicyEntry(adminRequestContext, END_USER, FOOTER_POLICY_KEY)

	await page.reload()
	await expectFooterTemplateValue(page, groupTemplate)

	effectivePolicy = await getEffectivePolicy(endUserRequestContext, FOOTER_POLICY_KEY)
	expect(normalizeFooterPolicyValue(effectivePolicy?.effectiveValue)).toMatchObject({
		enabled: true,
		writeQrcodeOnFooter: true,
		customizeFooterTemplate: true,
	})
	expect(getTrimmedFooterTemplate(effectivePolicy?.effectiveValue)).toBe(groupTemplate)
	expect(effectivePolicy?.sourceScope).toBe('group')
	await expect(page.getByText('Preference saved', { exact: true })).toHaveCount(0)
})
