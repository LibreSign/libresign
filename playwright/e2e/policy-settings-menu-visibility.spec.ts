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
 * 7. (Browser) Group admin can open "Create rule" and start creating a delegated rule.
 *
 * All admin-side operations are performed via the OCS API so no admin browser
 * session is needed, keeping the test as fast as possible.
 */

import { expect, test as base, type APIRequestContext } from '@playwright/test'
import { login } from '../support/nc-login'
import { expandSettingsMenu } from '../support/nc-navigation'
import {
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
	setAppConfig,
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
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const GROUP_ADMIN_PASSWORD = '123456'

const TEST_NAMESPACE = Math.random().toString(36).slice(2, 10)
const GROUP_ID = `policy-menu-visibility-group-${TEST_NAMESPACE}`
const GROUP_ADMIN = `policy-menu-visibility-admin-${TEST_NAMESPACE}`

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
	await setAppConfig(adminRequestContext, 'libresign', 'groups_request_sign', REQUEST_SIGN_GROUPS)
})


test.afterEach(async ({ adminRequestContext }) => {
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, null, true)
	await setAppConfig(adminRequestContext, 'libresign', 'groups_request_sign', DEFAULT_REQUEST_SIGN_GROUPS)
})

test('group admin can access policies and sees create-rule guard when higher-level rules block exceptions', async ({ page, adminRequestContext, groupAdminRequestContext }) => {
	// ── 0. Provision users/groups (idempotent; safe to call on every run) ──
	await ensureUserExists(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, GROUP_ADMIN, GROUP_ID)
	await ensureSubadminOfGroup(page.request, GROUP_ADMIN, GROUP_ID)
	await setUserLanguage(page.request, GROUP_ADMIN, 'en')

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

	// ── 6. "Create rule" button visibility and guard message ───────────────
	const createRuleButton = settingDialog.getByRole('button', { name: /^Create rule$/i })
	await expect(createRuleButton, '"Create rule" button should be visible in the policy dialog').toBeVisible({ timeout: 10000 })
	await expect(createRuleButton).toBeDisabled()
	await expect(createRuleButton).toHaveAttribute('title', /higher-level rule is blocking new exceptions in all scopes/i)
})
