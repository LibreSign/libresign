/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'
import { ensureLibresignAppEnabled } from './nc-provisioning'

/**
 * Authenticate a Playwright browser context as a specific Nextcloud account.
 * Rejects wrong-user sessions even when the login endpoint redirects.
 */
export async function login(
	request: APIRequestContext,
	user: string,
	password: string,
): Promise<void> {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	await ensureLibresignAppEnabled(request, adminUser, adminPassword)

	for (let attempt = 0; attempt < 3; attempt++) {
		await request.get('./logout', { failOnStatusCode: false, maxRedirects: 0 }).catch(() => {})

		const tokenResponse = await request.get('./csrftoken', {
			failOnStatusCode: true,
			timeout: 20_000,
		})
		const { token: requesttoken } = await tokenResponse.json() as { token: string }
		const origin = new URL(tokenResponse.url()).origin
		const loginResponse = await request.post('./login', {
			form: { user, password, requesttoken },
			headers: { Origin: origin },
			maxRedirects: 0,
			failOnStatusCode: false,
		})

		if (loginResponse.status() >= 500 && attempt < 2) {
			await new Promise(resolve => setTimeout(resolve, (attempt + 1) * 500))
			continue
		}

		const sessionResponse = await request.get('./ocs/v2.php/cloud/user?format=json', {
			headers: { 'OCS-APIRequest': 'true' },
			failOnStatusCode: false,
			maxRedirects: 0,
		})
		const session = sessionResponse.ok()
			? await sessionResponse.json() as { ocs?: { data?: { id?: string } } }
			: null
		const sessionId = session?.ocs?.data?.id
		if (sessionId !== user) {
			throw new Error(`Login failed for user "${user}": session belongs to "${sessionId ?? 'nobody'}" (login status ${loginResponse.status()})`)
		}

		await request.get('./apps/files', { failOnStatusCode: true })
		return
	}
}
