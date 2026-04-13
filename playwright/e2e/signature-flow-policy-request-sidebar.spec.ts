/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, request, test as base, type APIRequestContext, type Page } from '@playwright/test'
import { login } from '../support/nc-login'
import {
	configureOpenSsl,
	ensureGroupExists,
	ensureSubadminOfGroup,
	ensureUserExists,
	ensureUserInGroup,
	setAppConfig,
} from '../support/nc-provisioning'

const POLICY_KEY = 'signature_flow'
const GROUP_ADMIN_USER = 'signature-flow-e2e-group-admin'
const GROUP_ADMIN_PASSWORD = '123456'
const GROUP_ADMIN_GROUP = 'signature-flow-e2e-group'
const ADMIN_USER = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const ADMIN_PASSWORD = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const test = base.extend<{
	adminRequestContext: APIRequestContext
	groupAdminRequestContext: APIRequestContext
}>({
	adminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(ADMIN_USER, ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
	groupAdminRequestContext: async ({}, use) => {
		const ctx = await createAuthenticatedRequestContext(GROUP_ADMIN_USER, GROUP_ADMIN_PASSWORD)
		await use(ctx)
		await ctx.dispose()
	},
})

test.setTimeout(120_000)
test.describe.configure({ mode: 'serial' })

type OcsPolicyResponse = {
	ocs?: {
		meta?: {
			statuscode?: number
			message?: string
		}
		data?: Record<string, unknown>
	}
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
		},
	})
}

async function policyRequest(
	requestContext: APIRequestContext,
	method: 'POST' | 'DELETE',
	path: string,
	body?: Record<string, unknown>,
) {
	const response = method === 'POST'
		? await requestContext.post(`./ocs/v2.php${path}`, {
			data: body,
			headers: {
				'Content-Type': 'application/json',
			},
			failOnStatusCode: false,
		})
		: await requestContext.delete(`./ocs/v2.php${path}`, { failOnStatusCode: false })

	const text = await response.text()
	const parsed = text ? JSON.parse(text) as OcsPolicyResponse : { ocs: { data: {} } }

	return {
		httpStatus: response.status(),
		statusCode: parsed.ocs?.meta?.statuscode ?? response.status(),
		message: parsed.ocs?.meta?.message ?? '',
	}
}

async function setSystemSignatureFlowPolicy(
	requestContext: APIRequestContext,
	value: 'none' | 'parallel' | 'ordered_numeric',
	allowChildOverride: boolean,
) {
	const result = await policyRequest(
		requestContext,
		'POST',
		`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
		{ value, allowChildOverride },
	)

	expect(result.httpStatus, `Failed to set system signature flow policy: ${result.message}`).toBe(200)
}

async function clearOwnPreference(requestContext: APIRequestContext) {
	const result = await policyRequest(
		requestContext,
		'DELETE',
		`/apps/libresign/api/v1/policies/user/${POLICY_KEY}`,
	)

	expect([200, 500], `clearOwnPreference: expected 200 or 500 but got ${result.httpStatus}`).toContain(result.httpStatus)
}

async function addEmailSigner(page: Page, email: string, name: string) {
	const dialog = page.getByRole('dialog', { name: 'Add new signer' })
	await page.getByRole('button', { name: 'Add signer' }).click()
	await dialog.getByPlaceholder('Email').click()
	await dialog.getByPlaceholder('Email').pressSequentially(email, { delay: 50 })
	await expect(page.getByRole('option', { name: email })).toBeVisible({ timeout: 10_000 })
	await page.getByRole('option', { name: email }).click()
	await dialog.getByRole('textbox', { name: 'Signer name' }).fill(name)

	const saveSignerResponsePromise = page.waitForResponse((response) => {
		return response.url().includes('/apps/libresign/api/v1/request-signature')
			&& ['POST', 'PATCH'].includes(response.request().method())
	})

	await dialog.getByRole('button', { name: 'Save' }).click()
	const saveSignerResponse = await saveSignerResponsePromise
	expect(saveSignerResponse.status()).toBe(200)
	await expect(dialog).toBeHidden()
}

test.afterEach(async ({ adminRequestContext, groupAdminRequestContext }) => {
	await clearOwnPreference(adminRequestContext)
	await clearOwnPreference(groupAdminRequestContext)
	await setSystemSignatureFlowPolicy(adminRequestContext, 'none', true)
	await setAppConfig(adminRequestContext, 'libresign', 'groups_request_sign', JSON.stringify(['admin']))
})

test('request sidebar persists signature flow preference through policies endpoint', async ({ page, adminRequestContext }) => {
	await login(page.request, ADMIN_USER, ADMIN_PASSWORD)

	await configureOpenSsl(adminRequestContext, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setAppConfig(
		adminRequestContext,
		'libresign',
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
		]),
	)

	await setSystemSignatureFlowPolicy(adminRequestContext, 'parallel', true)
	await clearOwnPreference(adminRequestContext)

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()

	await addEmailSigner(page, 'signer01@libresign.coop', 'Signer 01')
	await addEmailSigner(page, 'signer02@libresign.coop', 'Signer 02')

	await expect(page.getByLabel('Use this as my default signing order')).toBeVisible()
	await page.getByText('Use this as my default signing order').click()

	const saveOrderedPreference = page.waitForResponse((response) => {
		const req = response.request()
		return req.method() === 'PUT'
			&& req.url().includes('/apps/libresign/api/v1/policies/user/signature_flow')
			&& (req.postData() ?? '').includes('ordered_numeric')
	})

	await expect(page.getByLabel('Sign in order')).toBeVisible()
	await page.getByText('Sign in order').click()
	await expect(page.getByLabel('Sign in order')).toBeChecked()

	const saveOrderedPreferenceResponse = await saveOrderedPreference
	expect(saveOrderedPreferenceResponse.status()).toBe(200)
})

for (const systemFlow of ['ordered_numeric', 'parallel'] as const) {
	test(`fixed system ${systemFlow} signature flow hides request toggles for groupadmin`, async ({ page, adminRequestContext, groupAdminRequestContext }) => {
		await ensureUserExists(adminRequestContext, GROUP_ADMIN_USER, GROUP_ADMIN_PASSWORD)
		await ensureGroupExists(adminRequestContext, GROUP_ADMIN_GROUP)
		await ensureUserInGroup(adminRequestContext, GROUP_ADMIN_USER, GROUP_ADMIN_GROUP)
		await ensureSubadminOfGroup(adminRequestContext, GROUP_ADMIN_USER, GROUP_ADMIN_GROUP)

		await configureOpenSsl(adminRequestContext, 'LibreSign Test', {
			C: 'BR',
			OU: ['Organization Unit'],
			ST: 'Rio de Janeiro',
			O: 'LibreSign',
			L: 'Rio de Janeiro',
		})

		await setAppConfig(
			adminRequestContext,
			'libresign',
			'identify_methods',
			JSON.stringify([
				{ name: 'account', enabled: false, mandatory: false },
				{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
			]),
		)

		await setAppConfig(
			adminRequestContext,
			'libresign',
			'groups_request_sign',
			JSON.stringify(['admin', GROUP_ADMIN_GROUP]),
		)

		await setSystemSignatureFlowPolicy(adminRequestContext, systemFlow, false)
		await clearOwnPreference(groupAdminRequestContext)

		await login(page.request, GROUP_ADMIN_USER, GROUP_ADMIN_PASSWORD)
		await page.goto('./apps/libresign/f/request')
		await expect(page.getByRole('heading', { name: 'Request Signatures' })).toBeVisible()
		await page.getByRole('button', { name: 'Upload from URL' }).click()
		await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
		await page.getByRole('button', { name: 'Send' }).click()

		await addEmailSigner(page, 'signer11@libresign.coop', 'Signer 11')
		await addEmailSigner(page, 'signer12@libresign.coop', 'Signer 12')

		await expect(page.getByLabel('Sign in order')).toBeHidden()
		await expect(page.getByLabel('Use this as my default signing order')).toBeHidden()

		const sendRequestResponsePromise = page.waitForResponse((response) => {
			const requestData = response.request()
			const body = requestData.postData() ?? ''
			return response.url().includes('/apps/libresign/api/v1/request-signature')
				&& ['POST', 'PATCH'].includes(requestData.method())
				&& body.includes('"status":1')
		})

		await page.getByRole('button', { name: 'Request signatures' }).click()
		await page.getByRole('button', { name: 'Send' }).click()

		const sendRequestResponse = await sendRequestResponsePromise
		expect(sendRequestResponse.status()).toBe(200)

		const sendRequestPayload = JSON.parse(sendRequestResponse.request().postData() ?? '{}') as {
			signatureFlow?: string
		}
		expect(sendRequestPayload.signatureFlow).toBeUndefined()

		const sendRequestBody = await sendRequestResponse.json() as {
			ocs?: {
				data?: {
					signatureFlow?: string
					signers?: Array<{ signingOrder?: number }>
				}
			}
		}
		expect(sendRequestBody.ocs?.data?.signatureFlow).toBe(systemFlow)

		if (systemFlow === 'ordered_numeric') {
			expect(sendRequestBody.ocs?.data?.signers?.map((signer) => signer.signingOrder)).toEqual([1, 2])
		}
	})
}
