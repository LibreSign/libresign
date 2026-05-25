/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Scenario: Policies menu in sidebar visibility based on group admin's manageable groups count.
 *
 * The Policies menu should only appear for:
 * - Instance admins (always)
 *
 * The Policies menu should NOT appear for:
 * - Group admins who manage only 1 group
 * - Regular users without group management
 *
 * Test Cases:
 * 1. Instance admin → Policies menu visible
 * 2. Group admin with 1 group → Policies menu NOT visible
 * 3. Group admin with 2+ groups → Policies menu NOT visible without editable policy
 * 4. Regular user without group management → Policies menu NOT visible
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

test.describe('Policies menu sidebar visibility', () => {
	test('instance admin sees Policies menu in sidebar', async ({ page, adminRequestContext }) => {
		// Provision groups and users using the admin request context
		await ensureGroupExists(adminRequestContext, GROUP_1)
		await ensureGroupExists(adminRequestContext, GROUP_2)

		// Create single-group admin
		await ensureUserExists(adminRequestContext, SINGLE_GROUP_ADMIN_NAME, SINGLE_GROUP_ADMIN_PASSWORD)
		await ensureUserInGroup(adminRequestContext, SINGLE_GROUP_ADMIN_NAME, GROUP_1)
		await ensureSubadminOfGroup(adminRequestContext, SINGLE_GROUP_ADMIN_NAME, GROUP_1)
		await setUserLanguage(adminRequestContext, SINGLE_GROUP_ADMIN_NAME, 'en')

		// Create multi-group admin
		await ensureUserExists(adminRequestContext, MULTI_GROUP_ADMIN_NAME, MULTI_GROUP_ADMIN_PASSWORD)
		await ensureUserInGroup(adminRequestContext, MULTI_GROUP_ADMIN_NAME, GROUP_1)
		await ensureUserInGroup(adminRequestContext, MULTI_GROUP_ADMIN_NAME, GROUP_2)
		await ensureSubadminOfGroup(adminRequestContext, MULTI_GROUP_ADMIN_NAME, GROUP_1)
		await ensureSubadminOfGroup(adminRequestContext, MULTI_GROUP_ADMIN_NAME, GROUP_2)
		await setUserLanguage(adminRequestContext, MULTI_GROUP_ADMIN_NAME, 'en')

		// Create regular user
		await ensureUserExists(adminRequestContext, REGULAR_USER_NAME, REGULAR_USER_PASSWORD)
		await setUserLanguage(adminRequestContext, REGULAR_USER_NAME, 'en')

		// Test instance admin
		await login(page.request, ADMIN_USER, ADMIN_PASSWORD)
		await page.goto('./apps/libresign/f/preferences')

		// Navigate to settings to ensure sidebar is visible
		await expandSettingsMenu(page)

		// Admin should see Policies menu item in the sidebar
		const policiesLink = page.locator('#app-navigation-vue').getByRole('link', { name: 'Policies' })
		await expect(policiesLink).toBeVisible()
	})

	test('group admin with 1 group does NOT see Policies menu in sidebar', async ({ page }) => {
		await login(page.request, SINGLE_GROUP_ADMIN_NAME, SINGLE_GROUP_ADMIN_PASSWORD)
		await page.goto('./apps/libresign/f/preferences')

		// Navigate to settings
		await expandSettingsMenu(page)

		// Policies menu should not be visible
		const policiesLink = page.locator('#app-navigation-vue').getByRole('link', { name: 'Policies' })

		// Should not see Policies menu in sidebar navigation
		await expect(policiesLink).not.toBeVisible()
	})

	test('group admin with 2+ groups does NOT see Policies menu in sidebar without editable policy', async ({ page }) => {
		await login(page.request, MULTI_GROUP_ADMIN_NAME, MULTI_GROUP_ADMIN_PASSWORD)
		await page.goto('./apps/libresign/f/preferences')

		// Navigate to settings
		await expandSettingsMenu(page)

		// Without an editable groups_request_sign policy, the sidebar keeps Policies hidden.
		const policiesLink = page.locator('#app-navigation-vue').getByRole('link', { name: 'Policies' })

		await expect(policiesLink).not.toBeVisible()
	})

	test('regular user without group management does NOT see Policies menu', async ({ page }) => {
		await login(page.request, REGULAR_USER_NAME, REGULAR_USER_PASSWORD)
		await page.goto('./apps/libresign/f/preferences')

		// Navigate to settings
		await expandSettingsMenu(page)

		// Regular user should not see Policies menu
		const policiesLink = page.locator('#app-navigation-vue').getByRole('link', { name: 'Policies' })

		await expect(policiesLink).not.toBeVisible()
	})
})
