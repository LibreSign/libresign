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

function getTargetFieldPattern(kind: 'group' | 'user'): RegExp {
	const labels = kind === 'group'
		? ['Scope groups', 'Target groups']
		: ['Scope accounts', 'Target users']

	return new RegExp(`^(?:${labels.map(escapeRegExp).join('|')})$`, 'i')
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
	await scopeDialog.getByRole('option', { name: new RegExp(`^${scopeName}`) }).click()
}

async function selectTarget(dialogScope: Locator, kind: 'group' | 'user', target: string): Promise<void> {
	const page = dialogScope.page()
	const labelPattern = getTargetFieldPattern(kind)
	const combobox = dialogScope.getByRole('combobox', { name: labelPattern }).first()
	const labeledInput = dialogScope.getByLabel(labelPattern).first()
	const targetInput = await combobox.count() ? combobox : labeledInput
	const selectedTarget = dialogScope.locator('.vs__selected').filter({ hasText: new RegExp(escapeRegExp(target), 'i') }).first()
	const submitButton = dialogScope.getByRole('button', {
		name: /Create rule|Create policy rule|Save changes|Save policy rule changes|Save rule changes/i,
	}).last()

	const isSelectionConfirmed = async () => {
		if (await selectedTarget.isVisible({ timeout: 1000 }).catch(() => false)) {
			return true
		}

		if (await submitButton.isVisible({ timeout: 1000 }).catch(() => false)) {
			return submitButton.isEnabled().catch(() => false)
		}

		return false
	}

	await expect(targetInput).toBeVisible({ timeout: 8000 })
	if (await isSelectionConfirmed()) {
		return
	}

	await targetInput.click()
	if (await isSelectionConfirmed()) {
		return
	}

	const searchInput = targetInput.locator('input, textarea, [contenteditable="true"]').first()
	if (await searchInput.isVisible({ timeout: 1000 }).catch(() => false)) {
		for (let attempt = 0; attempt < 3; attempt += 1) {
			await searchInput.fill(target)

			const matchingOption = page.getByRole('option', { name: new RegExp(escapeRegExp(target), 'i') }).first()
			const matchingVisible = await matchingOption.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)
			if (matchingVisible) {
				await matchingOption.click()
			} else {
				const floatingOption = page.locator('ul[role="listbox"] li, .vs__dropdown-menu--floating li').filter({ hasText: new RegExp(escapeRegExp(target), 'i') }).first()
				if (await floatingOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await floatingOption.click()
				} else {
					await searchInput.press('ArrowDown')
					await searchInput.press('Enter')
				}
			}

			await page.keyboard.press('Escape').catch(() => {})
			await searchInput.press('Tab').catch(() => {})
			if (await isSelectionConfirmed()) {
				break
			}
		}
		await expect.poll(isSelectionConfirmed, { timeout: 8000 }).toBe(true)
	} else {
		await page.keyboard.type(target)
		const matchingOption = page.getByRole('option', { name: new RegExp(escapeRegExp(target), 'i') }).first()
		if (await matchingOption.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false)) {
			await matchingOption.click()
		} else {
			await page.keyboard.press('ArrowDown')
			await page.keyboard.press('Enter')
		}
		await page.keyboard.press('Tab').catch(() => {})
		await expect.poll(isSelectionConfirmed, { timeout: 8000 }).toBe(true)
	}

	await expect(page.locator('ul[role="listbox"].vs__dropdown-menu--floating')).toHaveCount(0)
}

async function ensureCheckboxEnabled(scope: Page | Locator, checkboxLabel: string): Promise<void> {
	const checkbox = scope.getByRole('checkbox', { name: checkboxLabel }).first()
	await expect(checkbox).toBeVisible({ timeout: 10000 })
	const checked = await checkbox.isChecked().catch(() => false)
	if (!checked) {
		await checkbox.setChecked(true, { force: true })
	}
	await expect(checkbox).toBeChecked()
}

async function ensureFooterTemplateEditorVisible(scope: Page | Locator): Promise<Locator> {
	await ensureCheckboxEnabled(scope, 'Add visible footer with signature details')
	await ensureCheckboxEnabled(scope, 'Customize footer template')

	const editorContainer = scope.locator('.code-editor').first()
	const footerTemplateField = editorContainer.locator('.cm-content[contenteditable="true"]').first()
	await expect(footerTemplateField).toBeVisible({ timeout: 10000 })
	return footerTemplateField
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
		await selectTarget(createRuleDialog, 'group', target)
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
