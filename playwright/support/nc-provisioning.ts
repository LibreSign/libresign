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

type SignatureElementResponse = {
	elements?: Array<{
		type: string
		file: {
			nodeId: number
		}
	}>
}

type HasRootCertResponse = {
	hasRootCert?: boolean
}

type AppConfigResponse = {
	data?: string
}

let libresignAppEnablePromise: Promise<void> | null = null

function buildOcsHeaders(adminUser: string, adminPassword: string): Record<string, string> {
	const auth = 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')
	return {
		'OCS-ApiRequest': 'true',
		Accept: 'application/json',
		Authorization: auth,
	}
}

export async function ensureLibresignAppEnabled(
	request: APIRequestContext,
	adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
	adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
): Promise<void> {
	if (libresignAppEnablePromise) {
		await libresignAppEnablePromise
		return
	}

	libresignAppEnablePromise = (async () => {
		let lastError: Error | null = null

		for (let attempt = 1; attempt <= 6; attempt++) {
			try {
				const response = await request.post('./ocs/v2.php/cloud/apps/libresign?format=json', {
					headers: buildOcsHeaders(adminUser, adminPassword),
					failOnStatusCode: false,
				})

				if (!response.ok()) {
					const body = await response.text()
					if ([502, 503, 504].includes(response.status()) && attempt < 6) {
						await new Promise((resolve) => setTimeout(resolve, attempt * 250))
						continue
					}
					throw new Error(`Failed to enable LibreSign app: ${response.status()} ${body}`)
				}

				const rawBody = await response.text()
				if (!rawBody) {
					return
				}

				const body = JSON.parse(rawBody) as OcsResponse<unknown>
				if (body.ocs.meta.statuscode !== 200) {
					throw new Error(`Failed to enable LibreSign app: ${body.ocs.meta.message}`)
				}

				return
			} catch (error) {
				lastError = error instanceof Error ? error : new Error(String(error))
				if (attempt < 6) {
					await new Promise((resolve) => setTimeout(resolve, attempt * 250))
					continue
				}
			}
		}

		throw lastError ?? new Error('Failed to enable LibreSign app')
	})()

	try {
		await libresignAppEnablePromise
	} catch (error) {
		libresignAppEnablePromise = null
		throw error
	}
}

function toStringList(data: unknown): string[] {
	if (Array.isArray(data)) {
		return data.filter((item): item is string => typeof item === 'string')
	}

	if (data && typeof data === 'object') {
		const nested = data as { groups?: unknown[] }
		if (Array.isArray(nested.groups)) {
			return nested.groups.filter((item): item is string => typeof item === 'string')
		}
	}

	return []
}

async function ocsRequest<T = unknown>(
	request: APIRequestContext,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
	adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	body?: Record<string, string>,
	jsonBody?: unknown,
): Promise<OcsResponse<T>> {
	if (path.startsWith('/apps/libresign/')) {
		await ensureLibresignAppEnabled(request, adminUser, adminPassword)
	}

	const url = `./ocs/v2.php${path}`
	const headers: Record<string, string> = buildOcsHeaders(adminUser, adminPassword)
	if (jsonBody !== undefined) {
		headers['Content-Type'] = 'application/json'
	} else if (body !== undefined) {
		headers['Content-Type'] = 'application/x-www-form-urlencoded'
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
		return { ocs: { meta: { status: 'ok', statuscode: response.status(), message: '' }, data: {} as T } }
	}
	return JSON.parse(text) as OcsResponse<T>
}

export async function clearSignatureElements(
	request: APIRequestContext,
	userId = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
	password = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
): Promise<void> {
	const result = await ocsRequest<SignatureElementResponse>(
		request,
		'GET',
		'/apps/libresign/api/v1/signature/elements',
		userId,
		password,
	)

	for (const element of result.ocs.data.elements ?? []) {
		await ocsRequest(
			request,
			'DELETE',
			`/apps/libresign/api/v1/signature/elements/${element.file.nodeId}`,
			userId,
			password,
		)
	}
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

/**
 * Forces a user's Nextcloud language via Provisioning API.
 */
export async function setUserLanguage(
	request: APIRequestContext,
	userId: string,
	language: string,
): Promise<void> {
	const result = await ocsRequest(
		request,
		'PUT',
		`/cloud/users/${encodeURIComponent(userId)}`,
		undefined,
		undefined,
		{ key: 'language', value: language },
	)

	if (result.ocs.meta.statuscode !== 200) {
		throw new Error(`Failed to set language for user "${userId}" to "${language}": ${result.ocs.meta.message}`)
	}
}

// ---------------------------------------------------------------------------
// Groups and delegated administration
// ---------------------------------------------------------------------------

/**
 * Creates a group if it does not exist.
 */
export async function ensureGroupExists(
	request: APIRequestContext,
	groupId: string,
): Promise<void> {
	const check = await ocsRequest(request, 'GET', `/cloud/groups?search=${encodeURIComponent(groupId)}`)
	const groups = toStringList(check.ocs.data)
	if (groups.includes(groupId)) {
		return
	}

	const create = await ocsRequest(request, 'POST', '/cloud/groups', undefined, undefined, {
		groupid: groupId,
	})
	if (create.ocs.meta.statuscode !== 200 && create.ocs.meta.statuscode !== 102) {
		throw new Error(`Failed to create group "${groupId}": ${create.ocs.meta.message}`)
	}
}

/**
 * Adds a user to a group.
 */
export async function ensureUserInGroup(
	request: APIRequestContext,
	userId: string,
	groupId: string,
): Promise<void> {
	const groupsResponse = await ocsRequest(request, 'GET', `/cloud/users/${encodeURIComponent(userId)}/groups`)
	const groups = toStringList(groupsResponse.ocs.data)
	if (groups.includes(groupId)) {
		return
	}

	const add = await ocsRequest(
		request,
		'POST',
		`/cloud/users/${encodeURIComponent(userId)}/groups`,
		undefined,
		undefined,
		{ groupid: groupId },
	)
	if (add.ocs.meta.statuscode !== 200) {
		throw new Error(`Failed to add user "${userId}" to group "${groupId}": ${add.ocs.meta.message}`)
	}

	const verify = await ocsRequest(request, 'GET', `/cloud/users/${encodeURIComponent(userId)}/groups`)
	if (!toStringList(verify.ocs.data).includes(groupId)) {
		throw new Error(`User "${userId}" is not in group "${groupId}" after assignment.`)
	}
}

/**
 * Grants subadmin rights for a specific group.
 */
export async function ensureSubadminOfGroup(
	request: APIRequestContext,
	userId: string,
	groupId: string,
): Promise<void> {
	const subadmins = await ocsRequest(request, 'GET', `/cloud/users/${encodeURIComponent(userId)}/subadmins`)
	const groups = toStringList(subadmins.ocs.data)
	if (groups.includes(groupId)) {
		return
	}

	const grant = await ocsRequest(
		request,
		'POST',
		`/cloud/users/${encodeURIComponent(userId)}/subadmins`,
		undefined,
		undefined,
		{ groupid: groupId },
	)
	if (grant.ocs.meta.statuscode !== 200) {
		throw new Error(`Failed to grant subadmin for user "${userId}" in group "${groupId}": ${grant.ocs.meta.message}`)
	}

	const verify = await ocsRequest(request, 'GET', `/cloud/users/${encodeURIComponent(userId)}/subadmins`)
	if (!toStringList(verify.ocs.data).includes(groupId)) {
		throw new Error(`User "${userId}" was not granted subadmin rights for group "${groupId}".`)
	}
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

export async function getAppConfig(
	request: APIRequestContext,
	appId: string,
	key: string,
): Promise<string | null> {
	const result = await ocsRequest<AppConfigResponse>(
		request,
		'GET',
		`/apps/provisioning_api/api/v1/config/apps/${appId}/${key}`,
	)

	if (result.ocs.meta.statuscode === 404) {
		return null
	}

	return typeof result.ocs.data?.data === 'string'
		? result.ocs.data.data
		: null
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
 * Deletes the PFX certificate of a user so the next signing attempt
 * starts without an existing certificate.
 * Equivalent to: DELETE /ocs/v2.php/apps/libresign/api/v1/account/pfx
 */
export async function deleteUserPfx(
	request: APIRequestContext,
	userId: string,
	password: string,
): Promise<void> {
	await ocsRequest(request, 'DELETE', '/apps/libresign/api/v1/account/pfx', userId, password)
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
	const rootCertCheck = await ocsRequest<HasRootCertResponse>(
		request,
		'GET',
		'/apps/libresign/api/v1/setting/has-root-cert',
	)

	if (rootCertCheck.ocs.data?.hasRootCert) {
		await clearSignatureElements(request)
		return
	}

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

	await clearSignatureElements(request)
}
