/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Generic helpers for the LibreSign Policy OCS API, shared across all
 * policy-related spec files.
 */

import { expect, request, type APIRequestContext } from '@playwright/test'

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type OcsPolicyResponse = {
	ocs?: {
		meta?: { statuscode?: number; message?: string }
		data?: Record<string, unknown>
	}
}

export type PolicyApiResult = {
	httpStatus: number
	statusCode: number
	message: string
	data: Record<string, unknown>
}

export type EffectivePolicyEntry = {
	effectiveValue?: unknown
	sourceScope?: string
	canSaveAsUserDefault?: boolean
	editableByCurrentActor?: boolean
	allowedValues?: unknown[]
	everyoneCount?: number
}

export type SystemPolicySnapshot = {
	exists: boolean
	value: unknown
	allowChildOverride: boolean
}

// ---------------------------------------------------------------------------
// HTTP context
// ---------------------------------------------------------------------------

/**
 * Creates a Playwright `APIRequestContext` pre-configured with OCS headers
 * and Basic authentication for the given user.
 */
export async function createAuthenticatedRequestContext(
	authUser: string,
	authPassword: string,
): Promise<APIRequestContext> {
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

// ---------------------------------------------------------------------------
// Low-level OCS request wrapper
// ---------------------------------------------------------------------------

/**
 * Issues an OCS request to the LibreSign policy API and returns a normalised
 * result object.  Never throws on non-2xx — callers decide what is acceptable.
 */
export async function policyRequest(
	requestContext: APIRequestContext,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	body?: Record<string, unknown>,
): Promise<PolicyApiResult> {
	const requestUrl = `./ocs/v2.php${path}`
	const requestOptions = { data: body, failOnStatusCode: false }

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

// ---------------------------------------------------------------------------
// Policy read helpers
// ---------------------------------------------------------------------------

/**
 * Returns the effective policy entry for `policyKey` from the
 * `/policies/effective` endpoint, or `null` when the key is absent.
 */
export async function getEffectivePolicy(
	requestContext: APIRequestContext,
	policyKey: string,
): Promise<EffectivePolicyEntry | null> {
	const result = await policyRequest(requestContext, 'GET', '/apps/libresign/api/v1/policies/effective')
	const policies = (result.data.policies ?? {}) as Record<string, EffectivePolicyEntry>
	return policies[policyKey] ?? null
}

/**
 * Reads the current system-layer payload together with the effective rule count
 * so tests can distinguish an explicit persisted system rule from the implicit
 * built-in default.
 */
export async function getSystemPolicySnapshot(
	ctx: APIRequestContext,
	policyKey: string,
): Promise<SystemPolicySnapshot> {
	const [systemResult, effectivePolicy] = await Promise.all([
		policyRequest(ctx, 'GET', `/apps/libresign/api/v1/policies/system/${policyKey}`),
		getEffectivePolicy(ctx, policyKey),
	])

	expect(
		systemResult.httpStatus,
		`getSystemPolicySnapshot(${policyKey}): expected 200 but got ${systemResult.httpStatus}`,
	).toBe(200)

	const policy = (systemResult.data.policy ?? {}) as {
		value?: unknown
		allowChildOverride?: boolean
	}

	return {
		exists: (effectivePolicy?.everyoneCount ?? 0) > 0,
		value: policy.value ?? null,
		allowChildOverride: policy.allowChildOverride === true,
	}
}

/**
 * Restores a previously captured system-layer snapshot.
 *
 * When the original state had no explicit persisted system rule, restore by
 * posting the built-in default with `allowChildOverride=false`, which triggers
 * `PolicySource::clearSystemPolicy()` and removes the leaked global delegation.
 */
export async function restoreSystemPolicySnapshot(
	ctx: APIRequestContext,
	policyKey: string,
	snapshot: SystemPolicySnapshot,
): Promise<void> {
	if (!snapshot.exists) {
		await setSystemPolicyEntry(ctx, policyKey, null, false)
		return
	}

	const response = await policyRequest(
		ctx,
		'POST',
		`/apps/libresign/api/v1/policies/system/${policyKey}`,
		{
			value: snapshot.value ?? null,
			allowChildOverride: snapshot.allowChildOverride,
		},
	)

	expect(
		response.httpStatus,
		`restoreSystemPolicySnapshot(${policyKey}): expected 200 but got ${response.httpStatus}`,
	).toBe(200)
}

/**
 * Polls until `canSaveAsUserDefault` reaches the expected value.
 * Throws after `maxAttempts` unsuccessful reads.
 */
export async function waitForPolicyCanSaveAsUserDefault(
	requestContext: APIRequestContext,
	policyKey: string,
	expected: boolean,
	maxAttempts = 10,
): Promise<void> {
	for (let attempt = 0; attempt < maxAttempts; attempt++) {
		const entry = await getEffectivePolicy(requestContext, policyKey)
		if (entry?.canSaveAsUserDefault === expected) {
			return
		}
	}

	throw new Error(`Policy ${policyKey} did not reach canSaveAsUserDefault=${expected} after ${maxAttempts} attempts`)
}

// ---------------------------------------------------------------------------
// Policy write helpers
// ---------------------------------------------------------------------------

/**
 * Sets a system-level policy entry and asserts HTTP 200.
 * Pass `value: null` to clear an explicit system value.
 */
export async function setSystemPolicyEntry(
	ctx: APIRequestContext,
	policyKey: string,
	value: string | null,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(
		ctx,
		'POST',
		`/apps/libresign/api/v1/policies/system/${policyKey}`,
		{ value, allowChildOverride },
	)
	expect(response.httpStatus, `setSystemPolicyEntry(${policyKey}): expected 200 but got ${response.httpStatus}`).toBe(200)
}

/**
 * Sets a group-level policy entry and asserts HTTP 200.
 */
export async function setGroupPolicyEntry(
	ctx: APIRequestContext,
	groupId: string,
	policyKey: string,
	value: string,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(
		ctx,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${groupId}/${policyKey}`,
		{ value, allowChildOverride },
	)
	expect(response.httpStatus, `setGroupPolicyEntry(${groupId}/${policyKey}): expected 200 but got ${response.httpStatus}`).toBe(200)
}

/**
 * Deletes the authenticated user's own preference for `policyKey`.
 * Accepted statuses default to `[200, 500]`; pass `[200, 401, 500]` when the
 * user may not yet exist at cleanup time.
 */
export async function clearUserPolicyPreference(
	ctx: APIRequestContext,
	policyKey: string,
	acceptedStatuses: number[] = [200, 500],
): Promise<void> {
	const response = await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/user/${policyKey}`)
	expect(
		acceptedStatuses,
		`clearUserPolicyPreference(${policyKey}): expected ${acceptedStatuses.join(' or ')} but got ${response.httpStatus}`,
	).toContain(response.httpStatus)
}
