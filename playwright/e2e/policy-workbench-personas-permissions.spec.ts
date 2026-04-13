/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test as base, type APIRequestContext } from '@playwright/test'
import {
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'
import {
	clearUserPolicyPreference,
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	policyRequest,
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

test.describe.configure({ retries: 0, timeout: 90000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const DEFAULT_TEST_PASSWORD = '123456'

const GROUP_ID = 'policy-e2e-group'
const GROUP_ADMIN_USER = 'policy-e2e-group-admin'
const END_USER = 'policy-e2e-end-user'
const INSTANCE_RESET_USER = 'policy-e2e-instance-reset-user'
const POLICY_KEY = 'signature_flow'


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
	await clearUserPolicyPreference(groupAdminRequest, POLICY_KEY)
	await clearUserPolicyPreference(endUserRequest, POLICY_KEY)

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

		let endUserEffective = await getEffectivePolicy(endUserRequest, POLICY_KEY)
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

		endUserEffective = await getEffectivePolicy(endUserRequest, POLICY_KEY)
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

	await clearUserPolicyPreference(instanceResetUserRequest, POLICY_KEY)

		let result = await policyRequest(
			adminRequestContext,
			'POST',
			`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
			{ value: 'parallel', allowChildOverride: true },
		)
		expect(result.httpStatus).toBe(200)

		let effectivePolicy = await getEffectivePolicy(instanceResetUserRequest, POLICY_KEY)
		expect(effectivePolicy?.effectiveValue).toBe('parallel')
		expect(effectivePolicy?.sourceScope).toBe('global')

		result = await policyRequest(
			adminRequestContext,
			'POST',
			`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
			{ value: null, allowChildOverride: false },
		)
		expect(result.httpStatus).toBe(200)

		effectivePolicy = await getEffectivePolicy(instanceResetUserRequest, POLICY_KEY)
		expect(effectivePolicy?.effectiveValue).toBe('none')
		expect(effectivePolicy?.sourceScope).toBe('system')
	await instanceResetUserRequest.dispose()
})
