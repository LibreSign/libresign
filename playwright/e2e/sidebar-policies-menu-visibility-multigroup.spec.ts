/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Scenario: Policies menu in sidebar visibility based on group admin's manageable groups count.
 *
 * The Policies menu should only appear for:
 * - Instance admins (always)
 * - Group admins who manage 2+ groups (can delegate policies across groups)
 *
 * The Policies menu should NOT appear for:
 * - Group admins who manage only 1 group
 * - Regular users without group management
 *
 * Test Cases:
 * 1. Instance admin → Policies menu visible
 * 2. Group admin with 1 group → Policies menu NOT visible
 * 3. Group admin with 2 groups (admin of 1) → Policies menu visible
 * 4. Group admin with 2 groups (admin of both) → Policies menu visible
 */

import { expect, test as base, type APIRequestContext } from '@playwright/test'
import { randomBytes } from 'node:crypto'
import { login } from '../support/nc-login'
import { expandSettingsMenu } from '../support/nc-navigation'
import {
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
	setUserLanguage,
} from '../support/nc-provisioning'
import {
	createAuthenticatedRequestContext,
} from '../support/policy-api'

const test = base.extend<{
	adminRequestContext: APIRequestContext
}>({
	adminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
})

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const TEST_NAMESPACE = randomBytes(6).toString('hex')
const GROUP_1 = `group1-${TEST_NAMESPACE}`
const GROUP_2 = `group2-${TEST_NAMESPACE}`

const SINGLE_GROUP_ADMIN_NAME = `single-admin-${TEST_NAMESPACE}`
const SINGLE_GROUP_ADMIN_PASSWORD = '123456'

const MULTI_GROUP_ADMIN_NAME = `multi-admin-${TEST_NAMESPACE}`
const MULTI_GROUP_ADMIN_PASSWORD = '123456'

const REGULAR_USER_NAME = `regular-user-${TEST_NAMESPACE}`
const REGULAR_USER_PASSWORD = '123456'

test.beforeAll(async ({ browser }) => {
	// Use browser context directly for provisioning
	const ctx = browser.contexts()[0]
	const page = ctx.pages()[0] ?? (await ctx.newPage())

	const adminCtx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)

	try {
		// Create groups
		await ensureGroupExists(page.request, GROUP_1)
		await ensureGroupExists(page.request, GROUP_2)

		// Create single-group admin
		await ensureUserExists(page.request, SINGLE_GROUP_ADMIN_NAME, SINGLE_GROUP_ADMIN_PASSWORD)
		await ensureUserInGroup(page.request, SINGLE_GROUP_ADMIN_NAME, GROUP_1)
		await ensureSubadminOfGroup(page.request, SINGLE_GROUP_ADMIN_NAME, GROUP_1)
		await setUserLanguage(page.request, SINGLE_GROUP_ADMIN_NAME, 'en')

		// Create multi-group admin
		await ensureUserExists(page.request, MULTI_GROUP_ADMIN_NAME, MULTI_GROUP_ADMIN_PASSWORD)
		await ensureUserInGroup(page.request, MULTI_GROUP_ADMIN_NAME, GROUP_1)
		await ensureUserInGroup(page.request, MULTI_GROUP_ADMIN_NAME, GROUP_2)
		await ensureSubadminOfGroup(page.request, MULTI_GROUP_ADMIN_NAME, GROUP_1)
		await ensureSubadminOfGroup(page.request, MULTI_GROUP_ADMIN_NAME, GROUP_2)
		await setUserLanguage(page.request, MULTI_GROUP_ADMIN_NAME, 'en')

		// Create regular user
		await ensureUserExists(page.request, REGULAR_USER_NAME, REGULAR_USER_PASSWORD)
		await setUserLanguage(page.request, REGULAR_USER_NAME, 'en')

		await adminCtx.dispose()
	} finally {
		if (page.context() !== ctx) {
			await page.close()
		}
	}
})

test('instance admin sees Policies menu in sidebar', async ({ page }) => {
	await login(page, ADMIN_USER, ADMIN_PASSWORD)

	// Navigate to settings to ensure sidebar is visible
	await expandSettingsMenu(page)

	// Look for Policies menu item
	const policiesMenuItem = page.locator('[data-nav-id="policies"], [data-test-id="policies-menu"], a[href*="policies"]')

	// Admin should see it somewhere in the interface (Settings.vue component)
	await expect(policiesMenuItem.or(page.locator('text="Policies"'))).toBeVisible()
})

test('group admin with 1 group does NOT see Policies menu in sidebar', async ({ page }) => {
	await login(page, SINGLE_GROUP_ADMIN_NAME, SINGLE_GROUP_ADMIN_PASSWORD)

	// Navigate to settings
	await expandSettingsMenu(page)

	// Policies menu should not be visible
	const policiesMenuItems = page.locator('text="Policies"')

	// Check that Policies menu item is not visible in the left sidebar
	// (it might exist in HTML but should be hidden/v-if=false)
	const visiblePoliciesItems = policiesMenuItems.filter({ hasText: 'Policies' })
	const count = await visiblePoliciesItems.count()

	// Should not see Policies menu in sidebar navigation
	expect(count).toBe(0)
})

test('group admin with 2+ groups sees Policies menu in sidebar', async ({ page }) => {
	await login(page, MULTI_GROUP_ADMIN_NAME, MULTI_GROUP_ADMIN_PASSWORD)

	// Navigate to settings
	await expandSettingsMenu(page)

	// Policies menu should be visible now that admin manages 2 groups
	const policiesMenuItem = page.locator('[data-nav-id="policies"], text=Policies')

	await expect(policiesMenuItem).toBeVisible()
})

test('regular user without group management does NOT see Policies menu', async ({ page }) => {
	await login(page, REGULAR_USER_NAME, REGULAR_USER_PASSWORD)

	// Navigate to settings
	await expandSettingsMenu(page)

	// Regular user should not see Policies menu
	const policiesMenuItems = page.locator('text="Policies"')
	const visiblePoliciesItems = policiesMenuItems.filter({ hasText: 'Policies' })
	const count = await visiblePoliciesItems.count()

	expect(count).toBe(0)
})
