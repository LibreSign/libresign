/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test as base, type APIRequestContext } from '@playwright/test'
import { login } from '../support/nc-login'
import {
	configureOpenSsl,
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'
import {
	clearUserPolicyPreference,
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	setGroupPolicyEntry,
	setSystemPolicyEntry,
	waitForPolicyCanSaveAsUserDefault,
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

test.describe.configure({ retries: 0, timeout: 90000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const DEFAULT_TEST_PASSWORD = '123456'

const GROUP_ID = 'policy-preferences-group'
const END_USER = 'policy-preferences-member'
const POLICY_KEY = 'signature_flow'
const FOOTER_POLICY_KEY = 'add_footer'
const FOOTER_ENABLED_VALUE = JSON.stringify({
	enabled: true,
	writeQrcodeOnFooter: true,
	validationSite: '',
	customizeFooterTemplate: false,
})
const FOOTER_DISABLED_VALUE = JSON.stringify({
	enabled: false,
	writeQrcodeOnFooter: false,
	validationSite: '',
	customizeFooterTemplate: false,
})

async function resetPolicyPreferencesState(
	adminRequestContext: APIRequestContext,
	endUserRequestContext: APIRequestContext,
): Promise<void> {
	await clearUserPolicyPreference(endUserRequestContext, POLICY_KEY)
	await clearUserPolicyPreference(endUserRequestContext, FOOTER_POLICY_KEY)
	await setSystemPolicyEntry(adminRequestContext, FOOTER_POLICY_KEY, FOOTER_DISABLED_VALUE, true)
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, null, true)
}

async function expandSettingsMenu(page: import('@playwright/test').Page): Promise<void> {
	await page.keyboard.press('Escape').catch(() => {})
	const sidebar = page.locator('#app-navigation-vue')
	const settingsLink = sidebar.getByRole('link', { name: 'Account' })
	if (await settingsLink.count()) {
		return
	}

	const settingsToggle = sidebar.getByRole('button', { name: 'Settings' })
	if (await settingsToggle.count()) {
		await settingsToggle.first().click()
	}
}

test.beforeEach(async ({ page, adminRequestContext, endUserRequestContext }) => {
	await ensureUserExists(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, END_USER, GROUP_ID)
	await configureOpenSsl(adminRequestContext, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})
	await resetPolicyPreferencesState(adminRequestContext, endUserRequestContext)
})

test.afterEach(async ({ adminRequestContext, endUserRequestContext }) => {
	await resetPolicyPreferencesState(adminRequestContext, endUserRequestContext)
})

test('group member sees Preferences controls only when lower-layer customization is allowed', async ({ page, adminRequestContext, endUserRequestContext }) => {
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, 'parallel', true)
	await setGroupPolicyEntry(adminRequestContext, GROUP_ID, POLICY_KEY, 'ordered_numeric', false)
	await setSystemPolicyEntry(adminRequestContext, FOOTER_POLICY_KEY, FOOTER_ENABLED_VALUE, true)
	await setGroupPolicyEntry(adminRequestContext, GROUP_ID, FOOTER_POLICY_KEY, FOOTER_ENABLED_VALUE, false)

	let effectivePolicy = await getEffectivePolicy(endUserRequestContext, POLICY_KEY)
	expect(effectivePolicy?.effectiveValue).toBe('ordered_numeric')
	expect(effectivePolicy?.canSaveAsUserDefault).toBe(false)

	await login(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await page.goto('./apps/libresign/f/preferences')
	await expandSettingsMenu(page)

	await setGroupPolicyEntry(adminRequestContext, GROUP_ID, POLICY_KEY, 'ordered_numeric', true)

	effectivePolicy = await getEffectivePolicy(endUserRequestContext, POLICY_KEY)
	expect(effectivePolicy?.canSaveAsUserDefault).toBe(true)

	await setGroupPolicyEntry(adminRequestContext, GROUP_ID, FOOTER_POLICY_KEY, FOOTER_ENABLED_VALUE, true)
	await waitForPolicyCanSaveAsUserDefault(endUserRequestContext, FOOTER_POLICY_KEY, true)

	await page.goto('./apps/libresign/f/preferences')
	await expandSettingsMenu(page)

	const customizeTemplateToggle = page.getByText('Customize footer template', { exact: true })
	if (await customizeTemplateToggle.count() === 0) {
		const enableFooterToggle = page.getByText('Add visible footer with signature details', { exact: true })
		await expect(enableFooterToggle).toBeVisible()
		await enableFooterToggle.click()
	}
	await expect(customizeTemplateToggle).toBeVisible()
	const footerTemplateLabel = page.getByText('Footer template', { exact: true })
	await customizeTemplateToggle.click()
	await expect(footerTemplateLabel).toBeVisible()

	await customizeTemplateToggle.click()
	await expect(footerTemplateLabel).toHaveCount(0)
})
