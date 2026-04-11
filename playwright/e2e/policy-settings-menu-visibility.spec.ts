/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Scenario: Policies menu visibility follows delegated group rules.
 *
 * 1. (API) Instance admin creates a group policy for GROUP_ID with
 *    allowChildOverride:true, so the group admin can manage rules.
 * 2. (Browser) Log in as group admin → "Policies" nav item must be visible.
 * 3. (Browser) Navigate to Policies → see the editable policy card.
 * 4. (Browser) Click "Configure" → setting dialog opens.
 * 5. (Browser) Click "Create rule" inside dialog → scope-selector dialog opens.
 * 6. (API) Instance admin deletes the group policy.
 * 7. (Browser) Reload as group admin → "Policies" nav item must be hidden.
 *
 * All admin-side operations are performed via the OCS API so no admin browser
 * session is needed, keeping the test as fast as possible.
 */

import { expect, request, test, type APIRequestContext } from '@playwright/test'
import { login } from '../support/nc-login'
import {
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'

// One serial block: a single browser session for the group admin
// across both phases avoids repeated login overhead.
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

// ─── Admin API helpers (no browser needed) ────────────────────────────────────

async function makeAdminContext(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',
		ignoreHTTPSErrors: true,
		extraHTTPHeaders: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: 'Basic ' + Buffer.from(`${ADMIN_USER}:${ADMIN_PASSWORD}`).toString('base64'),
			'Content-Type': 'application/json',
		},
	})
}

/**
 * POST /policies/system/{key} — establish the instance-wide default and allow
 * group admins to override it (allowChildOverride: true).
 */
async function setSystemPolicy(
	ctx: APIRequestContext,
	value: string | null,
	allowChildOverride: boolean,
): Promise<void> {
	const resp = await ctx.post(
		`./ocs/v2.php/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
		{ data: { value, allowChildOverride }, failOnStatusCode: false },
	)
	expect(resp.status(), `setSystemPolicy: expected 200 but got ${resp.status()}`).toBe(200)
}

/**
 * PUT /policies/group/{group}/{key} — create/update a rule scoped to GROUP_ID.
 * This increments groupCount in effective-policies so the menu visibility check
 * passes in Settings.vue.
 */
async function setGroupPolicy(
	ctx: APIRequestContext,
	value: string,
	allowChildOverride: boolean,
): Promise<void> {
	const resp = await ctx.put(
		`./ocs/v2.php/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ data: { value, allowChildOverride }, failOnStatusCode: false },
	)
	expect(resp.status(), `setGroupPolicy: expected 200 but got ${resp.status()}`).toBe(200)
}

async function getEffectivePolicy(
	ctx: APIRequestContext,
): Promise<{ editableByCurrentActor?: boolean, groupCount?: number } | null> {
	const response = await ctx.get('./ocs/v2.php/apps/libresign/api/v1/policies/effective', {
		failOnStatusCode: false,
	})
	expect(response.status(), `getEffectivePolicy: expected 200 but got ${response.status()}`).toBe(200)
	const data = await response.json() as {
		ocs?: {
			data?: {
				policies?: Record<string, {
					editableByCurrentActor?: boolean
					groupCount?: number
				}>
			}
		}
	}

	return data.ocs?.data?.policies?.[POLICY_KEY] ?? null
}

/**
 * DELETE /policies/group/{group}/{key} — remove the rule so groupCount drops to 0
 * and the Policies nav item disappears for the group admin.
 */
async function deleteGroupPolicy(ctx: APIRequestContext): Promise<void> {
	await ctx.delete(
		`./ocs/v2.php/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ failOnStatusCode: false },
	)
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

// ─── Test ─────────────────────────────────────────────────────────────────────

test('policies nav item is visible only when group admin can edit at least one delegated rule', async ({ page }) => {
	const adminCtx = await makeAdminContext()

	try {
		// ── 0. Provision users/groups (idempotent; safe to call on every run) ──

		await ensureUserExists(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)
		await ensureGroupExists(page.request, GROUP_ID)
		await ensureUserInGroup(page.request, GROUP_ADMIN, GROUP_ID)
		// subadmin role → can_manage_group_policies: true in initial state
		await ensureSubadminOfGroup(page.request, GROUP_ADMIN, GROUP_ID)

		// ── 1. Admin: create group policy ─────────────────────────────────────
		//
		// System policy must allow child overrides so the workbench unlocks the
		// "Create rule" button for the group admin.
		await setSystemPolicy(adminCtx, FOOTER_ENABLED_VALUE, true)
		// Group-scoped rule → groupCount becomes ≥ 1 in the effective-policies
		// API response seen by the group admin.
		await setGroupPolicy(adminCtx, FOOTER_ENABLED_VALUE, true)

		const groupAdminCtx = await request.newContext({
			baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',
			ignoreHTTPSErrors: true,
			extraHTTPHeaders: {
				'OCS-ApiRequest': 'true',
				Accept: 'application/json',
				Authorization: 'Basic ' + Buffer.from(`${GROUP_ADMIN}:${GROUP_ADMIN_PASSWORD}`).toString('base64'),
			},
		})

		const editablePolicy = await getEffectivePolicy(groupAdminCtx)
		expect(editablePolicy?.groupCount).toBeGreaterThan(0)
		expect(editablePolicy?.editableByCurrentActor).toBe(true)
		await groupAdminCtx.dispose()

		// ── 2. Log in as group admin ───────────────────────────────────────────

		await login(page.request, GROUP_ADMIN, GROUP_ADMIN_PASSWORD)

		// Preferences page mounts Preferences.vue which calls fetchEffectivePolicies()
		// on onMounted, populating the Pinia store that Settings.vue reads reactively.
		await page.goto('./apps/libresign/f/preferences')

		// ── 3. "Policies" must appear in the settings sidebar ─────────────────
		await expandSettingsMenu(page)

		const policiesNavItem = page.getByRole('link', { name: 'Policies' })
		await expect(policiesNavItem, 'Policies link should be visible when a delegated group rule exists').toBeVisible({ timeout: 20000 })

		// ── 4. Navigate to the Policies page ──────────────────────────────────

		await policiesNavItem.click()
		await expect(page).toHaveURL(/\/f\/policies/, { timeout: 10000 })

		// ── 5. The editable policy card must be visible in the workbench ──────
		//
		// In group-admin viewMode, only policies satisfying
		//   groupCount > 0 AND editableByCurrentActor === true
		// are rendered. "Signing order" (signature_flow) matches because we set
		// system allowChildOverride:true and a group rule for GROUP_ID.

		const configureButton = page
			.getByRole('button', { name: /Configure/i })
			.first()
		await expect(configureButton, 'At least one Configure button should be visible for the group admin').toBeVisible({ timeout: 15000 })

		// ── 6. Open the setting dialog ("Signing order") ──────────────────────

		await configureButton.click()

		const settingDialog = page.getByRole('dialog', { name: /Signature footer/i })
		await expect(settingDialog, '"Signature footer" dialog should open on click').toBeVisible({ timeout: 10000 })

		// ── 7. "Create rule" button must be available inside the dialog ───────

		const createRuleButton = settingDialog.getByRole('button', { name: /Create rule/i })
		await expect(createRuleButton, '"Create rule" button should be enabled in the policy dialog').toBeVisible()
		await expect(createRuleButton).toBeEnabled()

		// ── 8. Clicking "Create rule" opens the scope-selector ("create policy modal") ──

		await createRuleButton.click()

		// The scope-selector dialog title is "What do you want to create?"
		// (falls back to "Create rule" if the workbench skips the selector)
		const createPolicyDialog = page
			.getByRole('dialog', { name: /What do you want to create\?|Create rule/i })
			.last()
		await expect(createPolicyDialog, 'Create-policy modal should appear after clicking Create rule').toBeVisible({ timeout: 10000 })

		// Close with Escape — no actual rule is created
		await page.keyboard.press('Escape')
		await expect(createPolicyDialog).toBeHidden({ timeout: 5000 })

		// ── 9. Admin: lock the only group rule (no lower-level override) ──────
		//
		// With only one group rule and allowChildOverride:false, the effective
		// policy is not editable by this group admin. The menu must disappear.
		await setGroupPolicy(adminCtx, FOOTER_ENABLED_VALUE, false)

		const lockedGroupAdminCtx = await request.newContext({
			baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',
			ignoreHTTPSErrors: true,
			extraHTTPHeaders: {
				'OCS-ApiRequest': 'true',
				Accept: 'application/json',
				Authorization: 'Basic ' + Buffer.from(`${GROUP_ADMIN}:${GROUP_ADMIN_PASSWORD}`).toString('base64'),
			},
		})

		const lockedPolicy = await getEffectivePolicy(lockedGroupAdminCtx)
		expect(lockedPolicy?.groupCount).toBeGreaterThan(0)
		expect(lockedPolicy?.editableByCurrentActor).toBe(false)
		await lockedGroupAdminCtx.dispose()

		await page.goto('./apps/libresign/f/preferences')
		await expandSettingsMenu(page)
		await expect(page.getByRole('link', { name: 'Policies' }), 'Policies link should be hidden when all delegated rules are read-only for the group admin').toBeHidden({ timeout: 20000 })

		// ── 10. Admin: remove the group policy ────────────────────────────────

		await deleteGroupPolicy(adminCtx)

		// ── 11. Reload as group admin to refresh effective-policies state ──────
		//
		// A full navigation re-triggers fetchEffectivePolicies() in Preferences.vue,
		// causing Settings.vue's hasDelegatedPolicies computed to update reactively.

		await page.goto('./apps/libresign/f/preferences')
		await expandSettingsMenu(page)

		// ── 12. "Policies" must no longer appear in the settings sidebar ───────

		await expect(page.getByRole('link', { name: 'Policies' }), 'Policies link should be gone after the group rule is removed').toBeHidden({ timeout: 20000 })
	} finally {
		// Always restore the environment so other tests are not affected.
		await deleteGroupPolicy(adminCtx).catch(() => {})
		await setSystemPolicy(adminCtx, null, true).catch(() => {})
		await adminCtx.dispose()
	}
})
