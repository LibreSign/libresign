/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl, setCertificateEngine, setSystemPolicy } from '../support/nc-provisioning'
import { getSmallValidPdfBase64 } from '../support/pdf-fixtures.ts'
import { useFooterPolicyGuard } from '../support/system-policies'

useFooterPolicyGuard()

type SignerRecord = {
	me?: boolean
	sign_request_uuid?: string
}

type DetailedFileResponse = {
	signers?: SignerRecord[]
}

/**
 * Create a request-signature OCS payload as the admin user.
 *
 * @param request Playwright request context.
 * @param body Request-signature payload.
 */
async function requestLibreSignApiAsAdmin(
	request: Page['request'],
	body: Record<string, unknown>,
) {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	const auth = 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')
	const response = await request.fetch('./ocs/v2.php/apps/libresign/api/v1/request-signature', {
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

	if (!response.ok()) {
		throw new Error(`LibreSign OCS request failed: POST /request-signature -> ${response.status()} ${await response.text()}`)
	}

	return response.json() as Promise<{ ocs: { data: DetailedFileResponse } }>
}

/**
 * Seed a self-sign request without paying the full UI setup cost.
 *
 * @param request Playwright request context.
 * @param fileName Unique file name used in the list.
 * @param userId Nextcloud account identifier for the signer.
 */
async function createSelfSignRequest(request: Page['request'], fileName: string, userId: string) {
	const pdfBase64 = await getSmallValidPdfBase64()
	const response = await requestLibreSignApiAsAdmin(request, {
		name: fileName,
		status: 1,
		file: {
			name: fileName,
			base64: pdfBase64,
		},
		signers: [{
			displayName: userId,
			identifyMethods: [{
				method: 'account',
				value: userId,
				mandatory: 1,
			}],
		}],
	})

	const signRequestUuid = response.ocs.data.signers?.find((signer) => signer.me)?.sign_request_uuid
	if (!signRequestUuid) {
		throw new Error('Authenticated signer sign_request_uuid not found in request-signature response')
	}

	return signRequestUuid
}

/**
 * Keep the newest request at the top before filtering the list.
 *
 * @param page Playwright page instance.
 */
async function sortByCreatedAtDescending(page: Page) {
	const createdAtTh = page.getByRole('columnheader', { name: 'Created at' })
	const sortDirection = await createdAtTh.evaluate((element: HTMLElement) => element.ariaSort)
	if (sortDirection !== 'descending') {
		await page.getByRole('button', { name: 'Created at' }).click()
		if (sortDirection === 'none') {
			await page.getByRole('button', { name: 'Created at' }).click()
		}
	}
}

test('updates files list status after signing with native engine', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	await login(
		page.request,
		adminUser,
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setCertificateEngine(page.request, 'openssl')
	await setSystemPolicy(
		page.request,
		'identify_methods',
		JSON.stringify({
			factors: [
				{ name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
				{ name: 'email', enabled: false, requirement: 'optional' },
			],
		}),
	)

	const uniqueName = `native-status-sync-${Date.now()}.pdf`
	const signRequestUuid = await createSelfSignRequest(page.request, uniqueName, adminUser)

	await page.goto('./apps/libresign/f/filelist/sign')
	await sortByCreatedAtDescending(page)

	const filesSearch = page.getByRole('searchbox', { name: /Search here/i }).first()
	if (await filesSearch.isVisible({ timeout: 2000 }).catch(() => false)) {
		await filesSearch.fill(uniqueName)
	}

	const targetRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: uniqueName })
	await expect(targetRow).toBeVisible({ timeout: 20000 })
	await expect(targetRow.locator('.status-chip__text')).toHaveText('Ready to sign')

	await page.goto(`./apps/libresign/f/sign/${signRequestUuid}/pdf`)
	await expect(page.getByLabel('PDF document to sign')).toBeVisible({ timeout: 15_000 })
	const signButton = page.locator('.sign-pdf-sidebar .button-wrapper').getByRole('button', { name: 'Sign document' })
	await expect(signButton).toBeVisible({ timeout: 15_000 })
	await signButton.click({ force: true })
	const signResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/sign/'),
	)
	await page.getByRole('dialog', { name: 'Sign document' }).getByRole('button', { name: 'Sign document' }).click()
	const signResponse = await signResponsePromise
	const signResponseBody = await signResponse.text()
	expect(
		signResponse.ok(),
		`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`,
	).toBeTruthy()
	await expect(page.getByText('This document is valid')).toBeVisible()

	await page.locator('#fileslist').getByRole('link', { name: 'Files' }).click()
	if (await filesSearch.isVisible({ timeout: 2000 }).catch(() => false)) {
		await filesSearch.fill(uniqueName)
	}
	await expect(targetRow).toBeVisible({ timeout: 20000 })
	await expect(targetRow.locator('.status-chip__text')).toHaveText('Signed')
})
