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

import { test, request, type APIRequestContext } from '@playwright/test'

import {
	getSystemPolicySnapshot,
	restoreSystemPolicySnapshot,
	setSystemPolicyEntry,
	type SystemPolicySnapshot,
} from './policy-api'

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
	let originalFooterPolicy: SystemPolicySnapshot

	test.beforeEach(async () => {
		adminContext = await makeAdminContext()
		originalFooterPolicy = await getSystemPolicySnapshot(adminContext, FOOTER_POLICY_KEY)
		await setSystemPolicyEntry(adminContext, FOOTER_POLICY_KEY, FOOTER_DISABLED_VALUE, true)
	})

	test.afterEach(async () => {
		await restoreSystemPolicySnapshot(adminContext, FOOTER_POLICY_KEY, originalFooterPolicy)
		await adminContext.dispose()
	})
}
