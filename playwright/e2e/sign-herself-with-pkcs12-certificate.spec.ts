/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, deleteUserPfx, setSystemPolicy } from '../support/nc-provisioning'

test('sign herself with pkcs12 certificate', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setSystemPolicy(
		page.request,
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: true, mandatory: true, signatureMethods: { password: { enabled: true } } },
			{ name: 'email', enabled: false, mandatory: false },
		]),
	)

	// Ensure the user has no existing certificate so the test always goes
	// through the "create password" flow.
	await deleteUserPfx(page.request, adminUser, adminPassword)

	await page.goto('./apps/libresign')

	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Account').fill(adminUser)
	await page.getByText('admin@email.tld').click()
	await page.getByRole('button', { name: 'Save' }).click()
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Sign document' }).click()
	await page.getByRole('button', { name: 'Define a password and sign the document.' }).click()
	await page.getByLabel('Enter a password').fill('Password1234')
	await page.getByRole('button', { name: 'Confirm' }).click()
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByLabel('Signature password').fill('Password1234')
	await page.getByText('Forgot password?').click()
	await expect(page.getByRole('button', { name: 'Read certificate' })).toBeVisible()
	await expect(page.getByRole('button', { name: 'Delete certificate' })).toBeVisible()
	const signResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/sign/'),
	)
	await page.getByRole('button', { name: 'Sign document' }).click()
	const signResponse = await signResponsePromise
	const signResponseBody = await signResponse.text()
	expect(
		signResponse.ok(),
		`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`,
	).toBeTruthy()
	await expect(page.getByText('This document is valid')).toBeVisible()
	await page.getByRole('button', { name: 'Expand details' }).click()
	await page.getByRole('button', { name: 'Expand validation status', exact: true }).click()
	await expect(page.getByRole('link', { name: 'Document integrity verified' })).toBeVisible()
	await page.getByRole('button', { name: 'Expand document certification', exact: true }).click()
})
