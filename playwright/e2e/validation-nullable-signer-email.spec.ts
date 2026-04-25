/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '@playwright/test'
import type { APIRequestContext, APIResponse } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, deleteAppConfig, setAppConfig } from '../support/nc-provisioning'

type OcsResponse<T> = {
	ocs: {
		data: T
	}
}

type CreatedRequest = {
	uuid?: string
}

async function requestLibreSignAsAdmin(
	request: APIRequestContext,
	path: string,
	body: Record<string, unknown>,
): Promise<APIResponse> {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	const auth = 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')

	return request.fetch(`./ocs/v2.php/apps/libresign/api/v1${path}`, {
		method: 'POST',
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: auth,
			'Content-Type': 'application/json',
		},
		data: JSON.stringify(body),
		failOnStatusCode: false,
	})
}

test('validation page accepts signer with nullable email payload', async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setAppConfig(
		page.request,
		'libresign',
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { emailToken: { enabled: true } }, can_create_account: false },
		]),
	)
	await setAppConfig(page.request, 'libresign', 'signature_engine', 'PhpNative')
	await deleteAppConfig(page.request, 'libresign', 'tsa_url')

	const pdfResponse = await page.request.get('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf', {
		failOnStatusCode: true,
	})
	const pdfBase64 = Buffer.from(await pdfResponse.body()).toString('base64')

	const createResponse = await requestLibreSignAsAdmin(page.request, '/request-signature', {
		name: `Validation nullable email ${Date.now()}`,
		file: {
			name: 'nullable-email.pdf',
			base64: pdfBase64,
		},
		signers: [{
			displayName: 'Signer Nullable Email',
			notify: 0,
			identifyMethods: [{
				method: 'email',
				value: 'signer01@libresign.coop',
				mandatory: 1,
			}],
		}],
	})

	expect(createResponse.ok()).toBeTruthy()
	const createdData = await createResponse.json() as OcsResponse<CreatedRequest>
	const fileUuid = createdData.ocs.data.uuid
	expect(fileUuid).toBeTruthy()

	await page.route('**/ocs/v2.php/apps/libresign/api/v1/file/validate/uuid/**', async (route) => {
		const originalResponse = await route.fetch()
		const payload = await originalResponse.json() as Record<string, unknown>
		const ocs = payload.ocs as Record<string, unknown> | undefined
		const data = ocs?.data as Record<string, unknown> | undefined

		if (data && Array.isArray(data.signers) && data.signers.length > 0) {
			const firstSigner = data.signers[0] as Record<string, unknown>
			firstSigner.email = null
		}

		await route.fulfill({
			status: originalResponse.status(),
			headers: {
				...originalResponse.headers(),
				'content-type': 'application/json',
			},
			body: JSON.stringify(payload),
		})
	})

	await page.goto(`./apps/libresign/p/validation/${fileUuid}`)

	await expect(page.getByText('This document is valid')).toBeVisible()
	await expect(page.getByText('Failed to validate document')).not.toBeVisible()
})
