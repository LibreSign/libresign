/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	getSystemPolicySnapshot,
	policyRequest,
	restoreSystemPolicySnapshot,
	setSystemPolicyEntry,
	type SystemPolicySnapshot,
} from '../support/policy-api'

type BooleanWorkbenchSetting = {
	policyKey: 'envelope_enabled' | 'crl_external_validation_enabled' | 'show_confetti_after_signing'
	title: string
}

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const booleanSettings: BooleanWorkbenchSetting[] = [
	{ policyKey: 'envelope_enabled', title: 'Signing envelopes' },
	{ policyKey: 'crl_external_validation_enabled', title: 'External CRL validation' },
	{ policyKey: 'show_confetti_after_signing', title: 'Confetti animation' },
]

let adminContext: Awaited<ReturnType<typeof createAuthenticatedRequestContext>> | null = null
let originalSnapshots: Partial<Record<BooleanWorkbenchSetting['policyKey'], SystemPolicySnapshot>> = {}

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

test.afterEach(async () => {
	if (!adminContext) {
		return
	}

	for (const setting of booleanSettings) {
		await clearAdminOverrides(adminContext, setting.policyKey)
		const snapshot = originalSnapshots[setting.policyKey]
		if (snapshot) {
			await restoreSystemPolicySnapshot(adminContext, setting.policyKey, snapshot)
		}
	}
	await adminContext.dispose()
	adminContext = null
	originalSnapshots = {}
})

test('boolean settings stay consistent between effective policy and admin initial state', async ({ page }) => {
	adminContext = await createAuthenticatedRequestContext(adminUser, adminPassword)
	const ctx = adminContext
	originalSnapshots = Object.fromEntries(await Promise.all(booleanSettings.map(async (setting) => {
		return [setting.policyKey, await getSystemPolicySnapshot(ctx, setting.policyKey)]
	}))) as Partial<Record<BooleanWorkbenchSetting['policyKey'], SystemPolicySnapshot>>

	await login(page.request, adminUser, adminPassword)
	await page.goto('./settings/admin/libresign')

	for (const setting of booleanSettings) {
		await clearAdminOverrides(ctx, setting.policyKey)
		await setSystemPolicyEntry(ctx, setting.policyKey, JSON.stringify(false), true)
		await page.reload()

		const effectiveDisabled = await getEffectivePolicy(ctx, setting.policyKey)
		expect(effectiveDisabled).not.toBeNull()
		expect(effectiveDisabled?.effectiveValue).toBe(false)
		const initialStateDisabled = await getAdminInitialStateValue(page, setting.policyKey)
		if (initialStateDisabled !== null) {
			expect(initialStateDisabled).toBe(false)
		}

		await setSystemPolicyEntry(ctx, setting.policyKey, JSON.stringify(true), true)
		await page.reload()

		const effectiveEnabled = await getEffectivePolicy(ctx, setting.policyKey)
		expect(effectiveEnabled).not.toBeNull()
		expect(effectiveEnabled?.effectiveValue).toBe(true)
		const initialStateEnabled = await getAdminInitialStateValue(page, setting.policyKey)
		if (initialStateEnabled !== null) {
			expect(initialStateEnabled).toBe(true)
		}
	}
})

/**
 * Removes admin-scoped user and group overrides for a policy key.
 *
 * @param ctx Authenticated admin request context
 * @param policyKey Policy key to clear
 */
async function clearAdminOverrides(
	ctx: Awaited<ReturnType<typeof createAuthenticatedRequestContext>>,
	policyKey: BooleanWorkbenchSetting['policyKey'],
): Promise<void> {
	await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/user/admin/${policyKey}`)
	await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/group/admin/${policyKey}`)
}

/**
 * Reads the current admin initial state value from the browser runtime.
 *
 * @param page Playwright browser page
 * @param stateKey Initial state key to load
 */
async function getAdminInitialStateValue(
	page: Page,
	stateKey: BooleanWorkbenchSetting['policyKey'],
): Promise<boolean | null> {
	return page.evaluate((key) => {
		const loadStateFn = (window as typeof window & {
			OCP?: {
				InitialState?: {
					loadState: (app: string, state: string, fallback: boolean | null) => boolean | null
				}
			}
		}).OCP?.InitialState?.loadState

		if (!loadStateFn) {
			return null
		}

		return loadStateFn('libresign', key, null)
	}, stateKey)
}
