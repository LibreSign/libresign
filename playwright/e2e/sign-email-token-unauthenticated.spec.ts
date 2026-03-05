/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '@playwright/test';
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink, extractTokenFromEmail } from '../support/mailpit'

test('sign document with email token as unauthenticated signer', async ({ page }) => {
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
			{ name: 'account', enabled: true, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { emailToken: { enabled: true } }, can_create_account: false },
		]),
	)

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click();
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).click();
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('http://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf');
	await page.getByRole('button', { name: 'Send' }).click();
	await page.getByRole('button', { name: 'Add signer' }).click();
	await page.getByRole('tab', { name: 'Email' }).click();
	await page.getByPlaceholder('Email').click();
	await page.getByPlaceholder('Email').fill('signer01@libresign.coop');
	await page.getByRole('option', { name: 'signer01@libresign.coop' }).click();
	await page.getByRole('textbox', { name: 'Signer name' }).click();
	await page.getByRole('textbox', { name: 'Signer name' }).press('ControlOrMeta+a');
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 01');
	await page.getByRole('button', { name: 'Save' }).click();

	const mailpit = createMailpitClient()
	await mailpit.deleteMessages()

	await page.getByRole('button', { name: 'Request signatures' }).click();
	await page.getByRole('button', { name: 'Send' }).click();

	// Logout before accessing the sign link to avoid session-related issues.
	await page.getByRole('button', { name: 'Settings menu' }).click();
	await page.getByRole('link', { name: 'Log out' }).click();

	const email = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')
	const signLink = extractSignLink(email.Text)
	if (!signLink) throw new Error('Sign link not found in email')
	await page.goto(signLink);
	await page.getByRole('button', { name: 'Sign the document.' }).click();
	await page.getByRole('textbox', { name: 'Email' }).click();
	await page.getByRole('textbox', { name: 'Email' }).fill('signer01@libresign.coop');
	await page.getByRole('button', { name: 'Send verification code' }).click();

	const tokenEmail = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: Code to sign file')
	const token = extractTokenFromEmail(tokenEmail.Text)
	if (!token) throw new Error('Token not found in email')
	await page.getByRole('textbox', { name: 'Enter your code' }).click();
	await page.getByRole('textbox', { name: 'Enter your code' }).fill(token);
	await page.getByRole('button', { name: 'Validate code' }).click();

	await expect(page.getByRole('heading', { name: 'Signature confirmation' })).toBeVisible();
	await expect(page.getByText('Step 3 of 3 - Signature')).toBeVisible();
	await expect(page.getByText('Your identity has been')).toBeVisible();
	await expect(page.getByText('You can now sign the document.')).toBeVisible();
	await page.getByRole('button', { name: 'Sign document' }).click();
	await page.waitForURL('**/validation/**');
	await expect(page.getByText('This document is valid')).toBeVisible();
	await expect(page.getByText('Congratulations you have')).toBeVisible();

	// Revisit the sign link after the document has been signed.
	// The signer must not be able to sign a second time.
	await page.goto(signLink)
	await expect(page.getByRole('button', { name: 'Sign the document.' })).not.toBeVisible({ timeout: 10_000 })
	await expect(page.getByText('This document is valid')).toBeVisible();
});
