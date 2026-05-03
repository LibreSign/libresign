/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'
import { ensureLibresignAppEnabled } from './nc-provisioning'

/**
 * Login to Nextcloud via API (no browser form involved).
 *
 * This mirrors the approach used by @nextcloud/e2e-test-server:
 * 1. GET /csrftoken  → obtain a CSRF token
 * 2. POST /login     → authenticate using form data + Origin header
 * 3. GET /apps/files → validate the session is active
 *
 * Using page.request keeps the cookies on the browser context, so every
 * subsequent page.goto() will already be authenticated.
 *
 * @param request - The Playwright APIRequestContext (use `page.request`)
 * @param user     - Account name / login
 * @param password - Account password
 */
export async function login(
	request: APIRequestContext,
	user: string,
	password: string,
): Promise<void> {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	await ensureLibresignAppEnabled(request, adminUser, adminPassword)

	// Ensure a previous authenticated session does not leak across persona switches.
	await request.get('./logout', {
		failOnStatusCode: false,
		maxRedirects: 0,
	}).catch(() => {})

	let tokenResponse: Awaited<ReturnType<APIRequestContext['get']>> | null = null
	let lastTokenError: Error | null = null
	for (let attempt = 1; attempt <= 5; attempt++) {
		try {
			tokenResponse = await request.get('./csrftoken', {
				failOnStatusCode: true,
				timeout: 20000,
			})
			break
		} catch (error) {
			lastTokenError = error instanceof Error ? error : new Error(String(error))
			if (attempt < 5) {
				await new Promise((resolve) => setTimeout(resolve, attempt * 250))
			}
		}
	}

	if (!tokenResponse) {
		throw lastTokenError ?? new Error('Failed to fetch csrftoken')
	}

	const { token: requesttoken } = await tokenResponse.json() as { token: string }

	// Strip everything from "index.php" onward so we get the bare origin
	const origin = tokenResponse.url().replace(/index\.php.*/, '')

	const loginResponse = await request.post('./login', {
		form: {
			user,
			password,
			requesttoken,
		},
		headers: {
			Origin: origin,
		},
		maxRedirects: 0,
		failOnStatusCode: false,
	})

	// The Nextcloud login sets x-user-id on success (even on the 303 response).
	if (!loginResponse.headers()['x-user-id']) {
		throw new Error(`Login failed for user "${user}": no x-user-id header in response (status ${loginResponse.status()})`)
	}

	// Confirm the session is valid
	await request.get('./apps/files', {
		failOnStatusCode: true,
	})
}
