/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink } from '../support/mailpit'

/**
 * When an authenticated Nextcloud user visits a sign link that belongs to a
 * different email address, LibreSign must block the attempt with a clear error
 * message instead of silently failing or allowing the wrong user to sign.
 *
 * The admin is logged in as admin@email.tld but the email sign request is for
 * signer01@libresign.coop — they do NOT match, so the backend throws:
 * "This document is not yours. Log out and use the sign link again."
 */
test('authenticated user sees error when accessing another signer\'s email link', async ({ page }) => {
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
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
		]),
	)

	const mailpit = createMailpitClient()
	await mailpit.deleteMessages()

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()

	// Email signer — only the email method is active so there are no tabs in the Add signer dialog
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Email').click()
	await page.getByPlaceholder('Email').pressSequentially('signer01@libresign.coop', { delay: 50 })
	await page.getByRole('option', { name: 'signer01@libresign.coop' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 01')
	await page.getByRole('button', { name: 'Save' }).click()

	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// Retrieve the sign link from the notification email sent to the signer.
	// The admin is intentionally NOT logged out — this simulates the wrong-session scenario.
	const email = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')
	const signLink = extractSignLink(email.Text)
	if (!signLink) throw new Error('Sign link not found in email')

	// Admin is still logged in (admin@email.tld) but navigates to a link
	// that belongs to signer01@libresign.coop — the emails do NOT match.
	// The identity check runs on page load; the "Sign the document." button is
	// never rendered — the error is shown directly in the signing status panel.
	await page.goto(signLink)

	// Backend must return the "wrong session" error via ACTION_DO_NOTHING.
	await expect(page.getByText('This document is not yours. Log out and use the sign link again.')).toBeVisible()
})
