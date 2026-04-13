/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, request, test, type APIRequestContext } from '@playwright/test'
import { login } from '../support/nc-login'
import {
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'

test.describe.configure({ retries: 0, timeout: 90000 })

const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const DEFAULT_TEST_PASSWORD = '123456'

const GROUP_ID = 'policy-preferences-group'
const END_USER = 'policy-preferences-member'
const POLICY_KEY = 'signature_flow'
const FOOTER_POLICY_KEY = 'add_footer'
const FOOTER_ENABLED_VALUE = JSON.stringify({
	enabled: true,
	writeQrcodeOnFooter: true,
	validationSite: '',
	customizeFooterTemplate: false,
})
const FOOTER_DISABLED_VALUE = JSON.stringify({
	enabled: false,
	writeQrcodeOnFooter: false,
	validationSite: '',
	customizeFooterTemplate: false,
})

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
	const parsed = text ? JSON.parse(text) as {
		ocs?: {
			meta?: { statuscode?: number, message?: string }
			data?: Record<string, unknown>
		}
	} : { ocs: { data: {} } }

	return {
		httpStatus: response.status(),
		statusCode: parsed.ocs?.meta?.statuscode ?? response.status(),
		message: parsed.ocs?.meta?.message ?? '',
		data: parsed.ocs?.data ?? {},
	}
}

async function getEffectiveSignatureFlow(
	requestContext: APIRequestContext,
): Promise<{
	effectiveValue?: unknown
	sourceScope?: string
	canSaveAsUserDefault?: boolean
} | null> {
	const result = await policyRequest(requestContext, 'GET', '/apps/libresign/api/v1/policies/effective')
	const policies = (result.data.policies ?? {}) as Record<string, {
		effectiveValue?: unknown
		sourceScope?: string
		canSaveAsUserDefault?: boolean
	}>

	return policies[POLICY_KEY] ?? null
}

async function setSystemSignatureFlow(
	ctx: APIRequestContext,
	value: string | null,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(
		ctx,
		'POST',
		`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
		{ value, allowChildOverride },
	)
	expect(response.httpStatus, `setSystemSignatureFlow: expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function setGroupSignatureFlow(
	ctx: APIRequestContext,
	value: string,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(
		ctx,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${POLICY_KEY}`,
		{ value, allowChildOverride },
	)
	expect(response.httpStatus, `setGroupSignatureFlow: expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function setSystemFooterPolicy(
	ctx: APIRequestContext,
	value: string | null,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(
		ctx,
		'POST',
		`/apps/libresign/api/v1/policies/system/${FOOTER_POLICY_KEY}`,
		{ value, allowChildOverride },
	)
	expect(response.httpStatus, `setSystemFooterPolicy: expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function setGroupFooterPolicy(
	ctx: APIRequestContext,
	value: string,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(
		ctx,
		'PUT',
		`/apps/libresign/api/v1/policies/group/${GROUP_ID}/${FOOTER_POLICY_KEY}`,
		{ value, allowChildOverride },
	)
	expect(response.httpStatus, `setGroupFooterPolicy: expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function clearOwnUserPreference(ctx: APIRequestContext): Promise<void> {
	const response = await policyRequest(ctx, 'DELETE', `/apps/libresign/api/v1/policies/user/${POLICY_KEY}`)
	expect([200, 500]).toContain(response.httpStatus)
}

async function expandSettingsMenu(page: import('@playwright/test').Page): Promise<void> {
	await page.keyboard.press('Escape').catch(() => {})
	const sidebar = page.locator('#app-navigation-vue')
	const settingsLink = sidebar.getByRole('link', { name: 'Account' })
	if (await settingsLink.count()) {
		return
	}

	const settingsToggle = sidebar.getByRole('button', { name: 'Settings' })
	if (await settingsToggle.count()) {
		await settingsToggle.first().click()
	}
}

test('group member sees Preferences controls only when lower-layer customization is allowed', async ({ page }) => {
	await ensureUserExists(page.request, END_USER, DEFAULT_TEST_PASSWORD)
	await ensureGroupExists(page.request, GROUP_ID)
	await ensureUserInGroup(page.request, END_USER, GROUP_ID)

	const adminRequest = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
	const endUserRequest = await createAuthenticatedRequestContext(END_USER, DEFAULT_TEST_PASSWORD)

	try {
		await clearOwnUserPreference(endUserRequest)
		await setSystemSignatureFlow(adminRequest, 'parallel', true)
		await setGroupSignatureFlow(adminRequest, 'ordered_numeric', false)
		await setSystemFooterPolicy(adminRequest, FOOTER_ENABLED_VALUE, true)
		await setGroupFooterPolicy(adminRequest, FOOTER_ENABLED_VALUE, false)

		let effectivePolicy = await getEffectiveSignatureFlow(endUserRequest)
		expect(effectivePolicy?.effectiveValue).toBe('ordered_numeric')
		expect(effectivePolicy?.canSaveAsUserDefault).toBe(false)

		await login(page.request, END_USER, DEFAULT_TEST_PASSWORD)
		await page.goto('./apps/libresign/f/preferences')
		await expandSettingsMenu(page)

		await expect(page.getByText('does not allow saving a personal default')).toBeVisible()
		await expect(page.getByRole('button', { name: 'Save as my default' })).toHaveCount(0)

		await setGroupSignatureFlow(adminRequest, 'ordered_numeric', true)

		effectivePolicy = await getEffectiveSignatureFlow(endUserRequest)
		expect(effectivePolicy?.canSaveAsUserDefault).toBe(true)

		await setGroupFooterPolicy(adminRequest, FOOTER_ENABLED_VALUE, true)

		await page.goto('./apps/libresign/f/preferences')
		await expandSettingsMenu(page)

		await expect(page.getByText('does not allow saving a personal default')).toHaveCount(0)
		await expect(page.getByRole('button', { name: 'Save as my default' })).toBeVisible()
		await expect(page.getByRole('button', { name: 'Save footer preference' })).toBeVisible()
	} finally {
		await clearOwnUserPreference(endUserRequest).catch(() => {})
		await setSystemFooterPolicy(adminRequest, FOOTER_DISABLED_VALUE, true).catch(() => {})
		await setSystemSignatureFlow(adminRequest, null, true).catch(() => {})
		await adminRequest.dispose()
		await endUserRequest.dispose()
	}
})
