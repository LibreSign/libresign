/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, request, test as base, type APIRequestContext } from '@playwright/test'
import {
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'

const test = base.extend<{
	adminRequestContext: APIRequestContext
}>({
	adminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
})

test.describe.configure({ retries: 0, timeout: 90000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const DEFAULT_TEST_PASSWORD = '123456'

const GROUP_ID = 'policy-e2e-group'
const GROUP_ADMIN_USER = 'policy-e2e-group-admin'
const END_USER = 'policy-e2e-end-user'
const INSTANCE_RESET_USER = 'policy-e2e-instance-reset-user'
const POLICY_KEY = 'signature_flow'

type OcsPolicyResponse = {
	ocs?: {
		meta?: {
			statuscode?: number
			message?: string
		}
		data?: Record<string, unknown>
	}
}

async function policyRequest(
	requestContext: APIRequestContext,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	body?: Record<string, unknown>,
) {
	const requestUrl = `./ocs/v2.php${path}`
	const requestOptions = {
		data: body,
		failOnStatusCode: false,
	}

	const response = method === 'GET'
		? await requestContext.get(requestUrl, requestOptions)
		: method === 'POST'
			? await requestContext.post(requestUrl, requestOptions)
			: method === 'PUT'
				? await requestContext.put(requestUrl, requestOptions)
				: await requestContext.delete(requestUrl, requestOptions)

	const text = await response.text()
	const parsed = text ? JSON.parse(text) as OcsPolicyResponse : { ocs: { data: {} } }

	return {
		httpStatus: response.status(),
		statusCode: parsed.ocs?.meta?.statuscode ?? response.status(),
		message: parsed.ocs?.meta?.message ?? '',
		data: parsed.ocs?.data ?? {},
	}
}

async function getEffectivePolicy(
	requestContext: APIRequestContext,
) {
	const result = await policyRequest(requestContext, 'GET', `/apps/libresign/api/v1/policies/effective`)
	const policies = (result.data.policies ?? {}) as Record<string, {
		effectiveValue?: unknown
		editableByCurrentActor?: boolean
		canSaveAsUserDefault?: boolean
		sourceScope?: string
		allowedValues?: unknown[]
	}>

	return policies[POLICY_KEY] ?? null
}

async function clearOwnUserPreference(
	requestContext: APIRequestContext,
) {
	const result = await policyRequest(requestContext, 'DELETE', `/apps/libresign/api/v1/policies/user/${POLICY_KEY}`)
	expect([200, 500], `clearOwnUserPreference: expected 200 or 500 but got ${result.httpStatus}`).toContain(result.httpStatus)
}

async function createAuthenticatedRequestContext(authUser: string, authPassword: string): Promise<APIRequestContext> {
	const auth = 'Basic ' + Buffer.from(`${authUser}:${authPassword}`).toString('base64')

	return request.newContext({
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',
		ignoreHTTPSErrors: true,
		extraHTTPHeaders: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: auth,
			'Content-Type': 'application/json',
		},
	})
}

test.afterEach(async ({ adminRequestContext }) => {
	await policyRequest(
		adminRequestContext,
		'POST',
		`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
		{ value: null, allowChildOverride: true },
	)
})

test('personas can manage policies according to permissions and override toggles', async ({ page, adminRequestContext }) => {
	await ensureUserExists(page.request, GROUP_ADMIN_USER, DEFAULT_TEST_PASSWORD)
	await ensureUserExists(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, GROUP_ADMIN_USER, GROUP_ID)
	await ensureUserInGroup(page.request, END_USER, GROUP_ID)
	await ensureSubadminOfGroup(page.request, GROUP_ADMIN_USER, GROUP_ID)

	const groupAdminRequest = await createAuthenticatedRequestContext(GROUP_ADMIN_USER, DEFAULT_TEST_PASSWORD)
	const endUserRequest = await createAuthenticatedRequestContext(END_USER, DEFAULT_TEST_PASSWORD)

	// Normalize user-level state before assertions.
	await clearOwnUserPreference(groupAdminRequest)
	await clearOwnUserPreference(endUserRequest)

	// Global admin defines baseline and group policy with override enabled.
	let result = await policyRequest(
		adminRequestContext,
		'POST',
		`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
		{ value: 'parallel', allowChildOverride: true },
	)
	expect(result.httpStatus).toBe(200)

	result = await policyRequest(
		adminRequestContext,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ value: 'ordered_numeric', allowChildOverride: true },
	)
	expect(result.httpStatus).toBe(200)

	// Group admin can edit own group rule.
	result = await policyRequest(
		groupAdminRequest,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ value: 'ordered_numeric', allowChildOverride: false },
	)
	expect(result.httpStatus).toBe(200)

	const groupPolicyReadback = await policyRequest(
		groupAdminRequest,
		'GET',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
	)
	expect(groupPolicyReadback.httpStatus).toBe(200)
	expect(groupPolicyReadback.data?.policy).toMatchObject({
		targetId: GROUP_ID,
		policyKey: POLICY_KEY,
		value: 'ordered_numeric',
		allowChildOverride: false,
	})

	// End user cannot manage group policy and cannot save user preference while group blocks lower layers.
	result = await policyRequest(
		endUserRequest,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ value: 'parallel', allowChildOverride: true },
	)
	expect(result.httpStatus).toBe(403)

	result = await policyRequest(
		endUserRequest,
		'PUT',
		`/apps/libresign/api/v1/policies/user/${POLICY_KEY}`,
		{ value: 'parallel' },
	)
	expect(result.httpStatus).toBe(400)

	let endUserEffective = await getEffectivePolicy(endUserRequest)
	expect(endUserEffective?.effectiveValue).toBe('ordered_numeric')
	expect(endUserEffective?.canSaveAsUserDefault).toBe(false)

	// Group admin enables lower-layer overrides again.
	result = await policyRequest(
		groupAdminRequest,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ value: 'ordered_numeric', allowChildOverride: true },
	)
	expect(result.httpStatus).toBe(200)

	// End user can now save personal preference and it becomes effective.
	result = await policyRequest(
		endUserRequest,
		'PUT',
		`/apps/libresign/api/v1/policies/user/${POLICY_KEY}`,
		{ value: 'parallel' },
	)
	expect(result.httpStatus).toBe(200)

	endUserEffective = await getEffectivePolicy(endUserRequest)
	expect(endUserEffective?.effectiveValue).toBe('parallel')
	expect(endUserEffective?.sourceScope).toBe('user')
	expect(endUserEffective?.canSaveAsUserDefault).toBe(true)
	await Promise.all([
		groupAdminRequest.dispose(),
		endUserRequest.dispose(),
	])
})

test('admin can remove explicit instance policy and restore system baseline', async ({ page, adminRequestContext }) => {
	await ensureUserExists(page.request, INSTANCE_RESET_USER, DEFAULT_TEST_PASSWORD)

	const instanceResetUserRequest = await createAuthenticatedRequestContext(INSTANCE_RESET_USER, DEFAULT_TEST_PASSWORD)

	await clearOwnUserPreference(instanceResetUserRequest)

		let result = await policyRequest(
			adminRequestContext,
			'POST',
			`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
			{ value: 'parallel', allowChildOverride: true },
		)
		expect(result.httpStatus).toBe(200)

		let effectivePolicy = await getEffectivePolicy(instanceResetUserRequest)
		expect(effectivePolicy?.effectiveValue).toBe('parallel')
		expect(effectivePolicy?.sourceScope).toBe('global')

		result = await policyRequest(
			adminRequestContext,
			'POST',
			`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
			{ value: null, allowChildOverride: false },
		)
		expect(result.httpStatus).toBe(200)

		effectivePolicy = await getEffectivePolicy(instanceResetUserRequest)
		expect(effectivePolicy?.effectiveValue).toBe('none')
		expect(effectivePolicy?.sourceScope).toBe('system')
	await instanceResetUserRequest.dispose()
})
