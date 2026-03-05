/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Helpers for configuring the Nextcloud environment from Playwright tests,
 * equivalent to Behat's OCC/OCS helpers.
 *
 * All operations go through the Nextcloud OCS Provisioning API and are
 * performed as admin. No Docker or OCC CLI access is needed.
 */

import type { APIRequestContext } from '@playwright/test'

type OcsResponse<T = unknown> = {
	ocs: {
		meta: { status: string; statuscode: number; message: string }
		data: T
	}
}

async function ocsRequest(
	request: APIRequestContext,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
	adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	body?: Record<string, string>,
	jsonBody?: unknown,
): Promise<OcsResponse> {
	const url = `./ocs/v2.php${path}`
	const auth = 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')
	const headers: Record<string, string> = {
		'OCS-ApiRequest': 'true',
		Accept: 'application/json',
		Authorization: auth,
	}
	if (jsonBody !== undefined) {
		headers['Content-Type'] = 'application/json'
	}
	const response = await request[method.toLowerCase() as 'get' | 'post' | 'put' | 'delete'](url, {
		headers,
		...(jsonBody !== undefined
			? { data: JSON.stringify(jsonBody) }
			: body !== undefined ? { form: body } : {}),
		failOnStatusCode: false,
	})

	if (!response.ok() && response.status() !== 404) {
		throw new Error(`OCS request failed: ${method} ${path} → ${response.status()} ${await response.text()}`)
	}

	const text = await response.text()
	if (!text) {
		return { ocs: { meta: { status: 'ok', statuscode: response.status(), message: '' }, data: {} } } as OcsResponse
	}
	return JSON.parse(text) as OcsResponse
}

// ---------------------------------------------------------------------------
// Users
// ---------------------------------------------------------------------------

/**
 * Creates a user if it doesn't exist yet.
 * Equivalent to Behat: `user :user exists`
 */
export async function ensureUserExists(
	request: APIRequestContext,
	userId: string,
	password = '123456',
): Promise<void> {
	const check = await ocsRequest(request, 'GET', `/cloud/users/${userId}`)
	if (check.ocs.meta.statuscode === 200) {
		return
	}
	const create = await ocsRequest(request, 'POST', '/cloud/users', undefined, undefined, {
		userid: userId,
		password,
	})
	if (create.ocs.meta.statuscode !== 200) {
		throw new Error(`Failed to create user "${userId}": ${create.ocs.meta.message}`)
	}
}

/**
 * Deletes a user. Silently succeeds if the user doesn't exist.
 */
export async function deleteUser(
	request: APIRequestContext,
	userId: string,
): Promise<void> {
	await ocsRequest(request, 'DELETE', `/cloud/users/${userId}`)
}

// ---------------------------------------------------------------------------
// App config  (equivalent to `occ config:app:set`)
// ---------------------------------------------------------------------------

/**
 * Sets an app config value.
 * Equivalent to: `occ config:app:set <appId> <key> --value=<value>`
 */
export async function setAppConfig(
	request: APIRequestContext,
	appId: string,
	key: string,
	value: string,
): Promise<void> {
	const result = await ocsRequest(
		request,
		'POST',
		`/apps/provisioning_api/api/v1/config/apps/${appId}/${key}`,
		undefined,
		undefined,
		{ value },
	)
	if (result.ocs.meta.statuscode !== 200) {
		throw new Error(`Failed to set app config ${appId}/${key}: ${result.ocs.meta.message}`)
	}
}

/**
 * Deletes an app config value.
 * Equivalent to: `occ config:app:delete <appId> <key>`
 */
export async function deleteAppConfig(
	request: APIRequestContext,
	appId: string,
	key: string,
): Promise<void> {
	await ocsRequest(request, 'DELETE', `/apps/provisioning_api/api/v1/config/apps/${appId}/${key}`)
}

// ---------------------------------------------------------------------------
// LibreSign-specific helpers
// ---------------------------------------------------------------------------

type OpenSslCertNames = {
	OU?: string | string[]
	O?: string
	C?: string
	ST?: string
	L?: string
}

/**
 * Configures the OpenSSL certificate engine.
 * Equivalent to: `occ libresign:configure:openssl --cn=... --c=... ...`
 */
export async function configureOpenSsl(
	request: APIRequestContext,
	commonName: string,
	names: OpenSslCertNames = {},
): Promise<void> {
	const normalised: OpenSslCertNames = { ...names }
	if (typeof normalised.OU === 'string') {
		normalised.OU = [normalised.OU]
	}

	const namesArray = (Object.entries(normalised) as [string, string | string[] | undefined][])
		.filter(([, val]) => val !== undefined)
		.map(([id, value]) => ({ id, value }))

	const result = await ocsRequest(
		request,
		'POST',
		'/apps/libresign/api/v1/admin/certificate/openssl',
		undefined,
		undefined,
		undefined,
		{ rootCert: { commonName, names: namesArray } },
	)
	if (result.ocs.meta.statuscode !== 200) {
		throw new Error(`Failed to configure OpenSSL: ${result.ocs.meta.message}`)
	}
}
