/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Helpers for managing LibreSign system policies from Playwright tests.
 *
 * The `useFooterPolicyGuard()` function registers `test.beforeEach` /
 * `test.afterEach` hooks that disable the footer policy before each test and
 * restore the original value afterwards.  Call it once at the top level of any
 * spec file that triggers document signing, because the footer merge step
 * requires PDFtk/Java which may not be available in every environment.
 */

import { test, expect, request, type APIRequestContext } from '@playwright/test'

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

export const FOOTER_POLICY_KEY = 'add_footer'

export const FOOTER_DISABLED_VALUE = JSON.stringify({
	enabled: false,
	writeQrcodeOnFooter: false,
	validationSite: '',
	customizeFooterTemplate: false,
})

// ---------------------------------------------------------------------------
// Low-level helpers
// ---------------------------------------------------------------------------

/**
 * Creates a standalone admin `APIRequestContext` suitable for use in
 * `beforeEach`/`afterEach` hooks where no `page` fixture is available.
 */
export async function makeAdminContext(): Promise<APIRequestContext> {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	return request.newContext({
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',
		ignoreHTTPSErrors: true,
		extraHTTPHeaders: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64'),
			'Content-Type': 'application/json',
		},
	})
}

/**
 * Reads the current value of a system policy.  Returns `null` when the policy
 * has not been set (HTTP 404).
 */
export async function getSystemPolicy(ctx: APIRequestContext, key: string): Promise<string | null> {
	const response = await ctx.get(`./ocs/v2.php/apps/libresign/api/v1/policies/system/${key}`, {
		failOnStatusCode: false,
	})
	if (response.status() === 404) {
		return null
	}

	const payload = await response.json() as { ocs?: { data?: { value?: string | null } } }
	return payload.ocs?.data?.value ?? null
}

/**
 * Writes a system policy value.  When `value` is `null` (meaning the policy
 * was not set before) this is a no-op so the absent state is preserved on
 * restore.
 */
export async function setSystemPolicy(ctx: APIRequestContext, key: string, value: string | null): Promise<void> {
	if (value === null) {
		return
	}

	const response = await ctx.post(`./ocs/v2.php/apps/libresign/api/v1/policies/system/${key}`, {
		data: {
			value,
			allowChildOverride: true,
		},
		failOnStatusCode: false,
	})

	expect(response.status(), `setSystemPolicy(${key}): expected 200 but got ${response.status()}`).toBe(200)
}

// ---------------------------------------------------------------------------
// Spec-level hook
// ---------------------------------------------------------------------------

/**
 * Registers `test.beforeEach` / `test.afterEach` hooks that disable the
 * footer policy for the duration of each test and restore it afterwards.
 *
 * Call once at the top level of any spec file that exercises document signing:
 *
 * ```ts
 * import { useFooterPolicyGuard } from '../support/system-policies'
 * useFooterPolicyGuard()
 * ```
 */
export function useFooterPolicyGuard(): void {
	let adminContext: APIRequestContext
	let originalFooterPolicy: string | null

	test.beforeEach(async () => {
		adminContext = await makeAdminContext()
		originalFooterPolicy = await getSystemPolicy(adminContext, FOOTER_POLICY_KEY)
		await setSystemPolicy(adminContext, FOOTER_POLICY_KEY, FOOTER_DISABLED_VALUE)
	})

	test.afterEach(async () => {
		await setSystemPolicy(adminContext, FOOTER_POLICY_KEY, originalFooterPolicy)
		await adminContext.dispose()
	})
}
