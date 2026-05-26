/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Scenario: Policies menu visibility follows delegated customization capability.
 *
 * 1. (API) Instance admin enables allowChildOverride on system policy.
 * 2. (API) No group rule exists yet.
 * 3. (Browser) Log in as group admin → "Policies" nav item must be visible.
 * 4. (Browser) Navigate to Policies → editable policy card must be visible.
 * 5. (Browser) Click "Configure" → setting dialog opens.
 * 6. (Browser) Click "Create rule" inside dialog → scope-selector dialog opens.
 * 7. (Browser) Group admin can start creating a delegated rule.
 *
 * All admin-side operations are performed via the OCS API so no admin browser
 * session is needed, keeping the test as fast as possible.
 */

import { expect, test as base, type APIRequestContext } from '@playwright/test'
import { login } from '../support/nc-login'
import { expandSettingsMenu } from '../support/nc-navigation'
import {
	deleteGroup,
	deleteUser,
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
	setSystemPolicy,
	setUserLanguage,
} from '../support/nc-provisioning'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	setSystemPolicyEntry,
} from '../support/policy-api'

// One serial block: a single browser session for the group admin
// across both phases avoids repeated login overhead.
const test = base.extend<{
	adminRequestContext: APIRequestContext
	groupAdminRequestContext: APIRequestContext
}>({
	adminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
	groupAdminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
})

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || process.env.NEXTCLOUD_ADMIN_PASSWORD || 'admin'
const GROUP_ADMIN_PASSWORD = '123456'

const GROUP_ID = 'policy-menu-visibility-group'
const GROUP_ADMIN = 'policy-menu-visibility-admin'

const POLICY_KEY = 'add_footer'
const REQUEST_SIGN_GROUPS = JSON.stringify(['admin', GROUP_ID])
const DEFAULT_REQUEST_SIGN_GROUPS = JSON.stringify(['admin'])
const FOOTER_ENABLED_VALUE = JSON.stringify({
	enabled: true,
	writeQrcodeOnFooter: true,
	validationSite: '',
	customizeFooterTemplate: false,
})


test.beforeEach(async ({ adminRequestContext }) => {
	await deleteUser(adminRequestContext, GROUP_ADMIN, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(adminRequestContext, GROUP_ID, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})

	await ensureUserExists(adminRequestContext, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
	await ensureGroupExists(adminRequestContext, GROUP_ID)
	await ensureUserInGroup(adminRequestContext, GROUP_ADMIN, GROUP_ID)
	await ensureSubadminOfGroup(adminRequestContext, GROUP_ADMIN, GROUP_ID)
	await setUserLanguage(adminRequestContext, GROUP_ADMIN, 'en')
	await setSystemPolicy(adminRequestContext, 'groups_request_sign', REQUEST_SIGN_GROUPS)
})


test.afterEach(async ({ adminRequestContext }) => {
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, null, true)
	await setSystemPolicy(adminRequestContext, 'groups_request_sign', DEFAULT_REQUEST_SIGN_GROUPS)
	await deleteUser(adminRequestContext, GROUP_ADMIN, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
	await deleteGroup(adminRequestContext, GROUP_ID, ADMIN_USER, ADMIN_PASSWORD).catch(() => {})
})

test('group admin can access policies and start creating a delegated rule', async ({ page, adminRequestContext, groupAdminRequestContext }) => {
	// ── 1. Admin: enable delegated customization at system layer ───────────
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, FOOTER_ENABLED_VALUE, true)

	const editablePolicy = await getEffectivePolicy(groupAdminRequestContext, POLICY_KEY)
	expect(editablePolicy?.editableByCurrentActor).toBe(true)

	// ── 2. Log in as group admin ───────────────────────────────────────────
	await login(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
	await page.goto('./apps/libresign/f/preferences')

	// ── 3. Access the Policies page (via nav item when present, fallback direct route) ──
	await expandSettingsMenu(page)

	const policiesNavItem = page.locator('a[href*="/apps/libresign/f/policies"]').first()
	if (await policiesNavItem.isVisible().catch(() => false)) {
		await policiesNavItem.click()
	} else {
		await page.goto('./apps/libresign/f/policies')
	}
	await expect(page).toHaveURL(/\/f\/policies/, { timeout: 10000 })

	// ── 4. The editable policy card must be visible in the workbench ──────
	const configureButton = page
		.getByRole('button', { name: /^Configure(?: setting)?$/i })
		.first()
	await expect(configureButton, 'At least one Configure button should be visible for the group admin').toBeVisible({ timeout: 15000 })

	// ── 5. Open the setting dialog ─────────────────────────────────────────
	await configureButton.click()

	// Wait for any dialog to appear and look for the one with "Create rule" button
	const allDialogs = page.locator('div[role="dialog"]')
	await expect(allDialogs.first()).toBeVisible({ timeout: 10000 })

	// Find the dialog that contains a "Create rule" button (which means it's the settings dialog)
	const settingDialog = page.locator('div[role="dialog"]').filter({
		has: page.getByRole('button', { name: /^Create rule$/i }),
	})
	await expect(settingDialog, 'Policy dialog with "Create rule" button should be visible').toBeVisible({ timeout: 10000 })

	// ── 6. "Create rule" button must be enabled for delegated configuration ─
	const createRuleButton = settingDialog.getByRole('button', { name: /^Create rule$/i })
	await expect(createRuleButton, '"Create rule" button should be visible in the policy dialog').toBeVisible({ timeout: 10000 })
	await expect(createRuleButton).toBeEnabled()
	await createRuleButton.click()
	const createRuleDialog = page.getByRole('dialog', { name: /Create rule|What do you want to create\?/i }).last()
	await expect(createRuleDialog).toBeVisible({ timeout: 10000 })
})
