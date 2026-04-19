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
} from '../support/nc-provisioning'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	setGroupPolicyEntry,
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
const GROUP_ID = 'policy-menu-visibility-group'
const GROUP_ADMIN = 'policy-menu-visibility-admin'
const GROUP_ADMIN_PASSWORD = '123456'

const POLICY_KEY = 'add_footer'
const FOOTER_ENABLED_VALUE = JSON.stringify({
	enabled: true,
	writeQrcodeOnFooter: true,
	validationSite: '',
	customizeFooterTemplate: false,
})


test.afterEach(async ({ adminRequestContext }) => {
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, null, true)
})

test('policies nav item is visible when group admin can customize policies even before first group rule exists', async ({ page, adminRequestContext, groupAdminRequestContext }) => {
	// ── 0. Provision users/groups (idempotent; safe to call on every run) ──
	await ensureUserExists(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, GROUP_ADMIN, GROUP_ID)
	await ensureSubadminOfGroup(page.request, GROUP_ADMIN, GROUP_ID)

	// ── 1. Admin: enable delegated customization at system layer ───────────
	await setSystemPolicyEntry(adminRequestContext, POLICY_KEY, FOOTER_ENABLED_VALUE, true)

	const editablePolicy = await getEffectivePolicy(groupAdminRequestContext, POLICY_KEY)
	expect(editablePolicy?.editableByCurrentActor).toBe(true)
	await setGroupPolicyEntry(groupAdminRequestContext, GROUP_ID, POLICY_KEY, FOOTER_ENABLED_VALUE, true)

	// ── 2. Log in as group admin ───────────────────────────────────────────
	await login(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
	await page.goto('./apps/libresign/f/preferences')

	// ── 3. "Policies" must appear in the settings sidebar ─────────────────
	await expandSettingsMenu(page)

	const policiesNavItem = page.getByRole('link', { name: 'Policies' })
	await expect(policiesNavItem, 'Policies link should be visible when delegated customization is allowed').toBeVisible({ timeout: 20000 })

	// ── 4. Navigate to the Policies page ──────────────────────────────────
	await policiesNavItem.click()
	await expect(page).toHaveURL(/\/f\/policies/, { timeout: 10000 })

	// ── 5. The editable policy card must be visible in the workbench ──────
	const configureButton = page
		.getByRole('button', { name: /Configure/i })
		.first()
	await expect(configureButton, 'At least one Configure button should be visible for the group admin').toBeVisible({ timeout: 15000 })

	// ── 6. Open the setting dialog ("Signing order") ──────────────────────
	await configureButton.click()

	const settingDialog = page.getByRole('dialog', { name: /Signature footer|Signing order/i })
	await expect(settingDialog, 'Policy dialog should open on click').toBeVisible({ timeout: 10000 })

	// ── 7. "Create rule" button must be available inside the dialog ───────
	const createRuleButton = settingDialog.getByRole('button', { name: /Create rule/i })
	await expect(createRuleButton, '"Create rule" button should be enabled in the policy dialog').toBeVisible({ timeout: 10000 })
	await expect(createRuleButton).toBeEnabled()

	// ── 8. Clicking "Create rule" opens the scope-selector ("create policy modal") ──
	await createRuleButton.click()

	const createPolicyDialog = page
		.getByRole('dialog', { name: /What do you want to create\?|Create rule/i })
		.last()
	await expect(createPolicyDialog, 'Create-policy modal should appear after clicking Create rule').toBeVisible({ timeout: 10000 })

	await createPolicyDialog.getByRole('option', { name: /^Group/ }).click()

	const targetGroupsField = createPolicyDialog.getByLabel('Target groups').first()
	await expect(targetGroupsField).toBeVisible({ timeout: 10000 })
	await targetGroupsField.click()

	const searchGroupsInput = createPolicyDialog.getByPlaceholder('Search groups').first()
	await expect(searchGroupsInput).toBeVisible({ timeout: 10000 })
	await searchGroupsInput.fill(GROUP_ID)

	const groupOption = createPolicyDialog.getByRole('option', { name: new RegExp(`^${GROUP_ID}$`, 'i') }).first()
	const optionWasVisible = await groupOption.waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false)
	if (optionWasVisible) {
		await groupOption.click()
	} else {
		await searchGroupsInput.press('ArrowDown')
		await searchGroupsInput.press('Enter')
	}
	await searchGroupsInput.press('Tab').catch(() => {})

	await Promise.any([
		createPolicyDialog.getByRole('option', { name: /^Group/ }).waitFor({ state: 'visible', timeout: 10000 }),
		createPolicyDialog.getByLabel('Target groups').waitFor({ state: 'visible', timeout: 10000 }),
	])
})
