/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig, deleteAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink, extractTokenFromEmail } from '../support/mailpit'

/**
 * An authenticated Nextcloud user can sign a document via the email+token
 * identify method when the signer's email matches their Nextcloud account email.
 *
 * The admin's Nextcloud account email is admin@email.tld. This test adds that
 * same email as the signer's email, keeps the admin logged in, and verifies the
 * full email-token flow succeeds (the backend allows it because the session
 * email matches the signer email in throwIfIsAuthenticatedWithDifferentAccount).
 */
test('sign document with email token as authenticated signer', async ({ page }) => {
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
	await setAppConfig(page.request, 'libresign', 'java_path', '/usr/bin/java')
	await setAppConfig(page.request, 'libresign', 'pdftk_path', '/usr/bin/pdftk')

	const mailpit = createMailpitClient()
	await mailpit.deleteMessages()

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()

	// Add the admin's own email as the signer.
	// Only the email method is active so there are no tabs in the Add signer dialog.
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Email').click()
	await page.getByPlaceholder('Email').pressSequentially('admin@email.tld', { delay: 50 })
	await page.getByRole('option', { name: 'admin@email.tld' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Admin')
	await page.getByRole('button', { name: 'Save' }).click()

	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// Get the sign link from the notification email sent to admin@email.tld.
	// The admin is intentionally NOT logged out — this tests the authenticated path.
	const notificationEmail = await waitForEmailTo(mailpit, 'admin@email.tld', 'LibreSign: There is a file for you to sign')
	const signLink = extractSignLink(notificationEmail.Text)
	if (!signLink) throw new Error('Sign link not found in notification email')

	// Navigate to the sign link while still logged in as admin.
	// throwIfIsAuthenticatedWithDifferentAccount allows this because
	// admin@email.tld === the signer's email address.
	await page.goto(signLink)
	await page.getByRole('button', { name: 'Sign the document.' }).click()

	// Complete the email token identification flow.
	// The email field may be pre-filled with the admin's address; fill() is safe either way.
	await page.getByRole('textbox', { name: 'Email' }).fill('admin@email.tld')
	await page.getByRole('button', { name: 'Send verification code' }).click()

	const tokenEmail = await waitForEmailTo(mailpit, 'admin@email.tld', 'LibreSign: Code to sign file')
	const token = extractTokenFromEmail(tokenEmail.Text)
	if (!token) throw new Error('Token not found in email')
	await page.getByRole('textbox', { name: 'Enter your code' }).fill(token)
	await page.getByRole('button', { name: 'Validate code' }).click()

	await expect(page.getByRole('heading', { name: 'Signature confirmation' })).toBeVisible()
	await expect(page.getByText('Your identity has been')).toBeVisible()
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
	await expect(page.getByText('Congratulations you have')).toBeVisible()
})
