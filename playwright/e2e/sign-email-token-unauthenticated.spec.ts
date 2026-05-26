/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect } from '@playwright/test';
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig, setCertificateEngine, setSystemPolicy } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink, extractTokenFromEmail } from '../support/mailpit'
import { getSmallValidPdfBase64 } from '../support/pdf-fixtures'
import { useFooterPolicyGuard } from '../support/system-policies'

useFooterPolicyGuard()

test('sign document with email token as unauthenticated signer', async ({ page }) => {
	const signerEmail = `signer-email-token-${Date.now()}@libresign.coop`
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

	await setSystemPolicy(
		page.request,
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { emailToken: { enabled: true } }, can_create_account: false },
		]),
	)
	await setSystemPolicy(
		page.request,
		'identification_documents',
		JSON.stringify({ enabled: false, approvers: ['admin'] }),
	)
	await setCertificateEngine(page.request, 'openssl')
	await setAppConfig(page.request, 'libresign', 'signature_engine', 'PhpNative')

	const mailpit = createMailpitClient()
	await mailpit.deleteMessages()

	const pdfBase64 = await getSmallValidPdfBase64()
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	const auth = 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')
	const createResponse = await page.request.fetch('./ocs/v2.php/apps/libresign/api/v1/request-signature', {
		method: 'POST',
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: auth,
			'Content-Type': 'application/json',
		},
		data: JSON.stringify({
			name: `email-token-unauth-${Date.now()}.pdf`,
			status: 1,
			file: { name: 'email-token-unauth.pdf', base64: pdfBase64 },
			signers: [{
				displayName: 'Signer 01',
				identifyMethods: [{ method: 'email', value: signerEmail, mandatory: 1 }],
			}],
		}),
		failOnStatusCode: false,
	})
	expect(
		createResponse.ok(),
		`Create request-signature failed with status ${createResponse.status()}: ${await createResponse.text()}`,
	).toBeTruthy()

	// Keep the browser unauthenticated before opening a public sign link.
	// This avoids logout redirects to absolute hosts that may differ per environment.
	await page.context().clearCookies();
	await page.goto('about:blank');

	const email = await waitForEmailTo(mailpit, signerEmail, 'LibreSign: There is a file for you to sign')
	const signLink = extractSignLink(email.Text)
	if (!signLink) throw new Error('Sign link not found in email')
	const signLinkCandidates = signLink.startsWith('/index.php/')
		? [signLink, signLink.replace(/^\/index\.php/, '')]
		: [signLink, `/index.php${signLink.startsWith('/') ? '' : '/'}${signLink}`]

	let invitationOpened = false
	for (const candidate of signLinkCandidates) {
		await page.goto(candidate)
		const loginHeading = page.getByRole('heading', { name: 'Log in to Nextcloud' })
		if (await loginHeading.isVisible({ timeout: 1_500 }).catch(() => false)) {
			continue
		}
		invitationOpened = true
		break
	}
	if (!invitationOpened) {
		throw new Error(`Invitation link redirected to login instead of public sign page: ${page.url()}`)
	}
	const openSignButton = page.getByRole('button', { name: 'Sign document' }).first()
	const emailTextbox = page.getByRole('textbox', { name: 'Email' }).first()
	await Promise.any([
		openSignButton.waitFor({ state: 'visible', timeout: 10_000 }),
		emailTextbox.waitFor({ state: 'visible', timeout: 10_000 }),
	])
	if (await openSignButton.isVisible().catch(() => false)) {
		await openSignButton.click();
	}
	await expect(emailTextbox).toBeVisible()
	await emailTextbox.click();
	await emailTextbox.fill(signerEmail);
	const sendVerificationCodeButton = page.getByRole('button', { name: 'Send verification code' })
	const codeTextbox = page.getByRole('textbox', { name: 'Enter your code' }).first()
	await Promise.any([
		sendVerificationCodeButton.waitFor({ state: 'visible', timeout: 15_000 }),
		codeTextbox.waitFor({ state: 'visible', timeout: 15_000 }),
	])
	if (!await codeTextbox.isVisible({ timeout: 200 }).catch(() => false)) {
		await expect(sendVerificationCodeButton).toBeVisible({ timeout: 15_000 })
		await expect(sendVerificationCodeButton).toBeEnabled({ timeout: 15_000 })
		await sendVerificationCodeButton.click();
	}
	await expect(codeTextbox).toBeVisible({ timeout: 15_000 })

	const tokenEmail = await waitForEmailTo(mailpit, signerEmail, 'LibreSign: Code to sign file', { timeout: 60_000 })
	const token = extractTokenFromEmail(tokenEmail.Text)
	if (!token) throw new Error('Token not found in email')
	await codeTextbox.click();
	await codeTextbox.fill(token);
	await page.getByRole('button', { name: 'Validate code' }).click();

	await expect(page.getByRole('heading', { name: 'Signature confirmation' })).toBeVisible();
	await expect(page.getByText('Step 3 of 3 - Signature')).toBeVisible();
	await expect(page.getByText('Your identity has been')).toBeVisible();
	await expect(page.getByText('You can now sign the document.')).toBeVisible();
	const signResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/sign/'),
	)
	await page.getByRole('dialog', { name: 'Signature confirmation' }).getByRole('button', { name: 'Sign document' }).click();
	const signResponse = await signResponsePromise
	const signResponseBody = await signResponse.text()
	expect(
		signResponse.ok(),
		`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`,
	).toBeTruthy()
	await expect(page.getByText('This document is valid')).toBeVisible();
	await expect(page.getByText('Congratulations you have')).toBeVisible();
	await expect(page.getByRole('button', { name: 'Sign document' })).not.toBeVisible();
});
