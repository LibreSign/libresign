/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect, request, type APIRequestContext } from '@playwright/test';
import { login } from '../support/nc-login'
import { configureOpenSsl, deleteAppConfig, setAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink, extractTokenFromEmail } from '../support/mailpit'

const FOOTER_POLICY_KEY = 'add_footer'
const FOOTER_DISABLED_VALUE = JSON.stringify({
	enabled: false,
	writeQrcodeOnFooter: false,
	validationSite: '',
	customizeFooterTemplate: false,
})

async function makeAdminContext(): Promise<APIRequestContext> {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	return request.newContext({
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',
		ignoreHTTPSErrors: true,
		extraHTTPHeaders: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64'),
			'Content-Type': 'application/json',
		},
	})
}

async function getSystemFooterPolicy(ctx: APIRequestContext): Promise<string | null> {
	const response = await ctx.get(`./ocs/v2.php/apps/libresign/api/v1/policies/system/${FOOTER_POLICY_KEY}`, {
		failOnStatusCode: false,
	})
	if (response.status() === 404) {
		return null
	}

	const payload = await response.json() as { ocs?: { data?: { value?: string | null } } }
	return payload.ocs?.data?.value ?? null
}

async function setSystemFooterPolicy(ctx: APIRequestContext, value: string | null): Promise<void> {
	if (value === null) {
		return
	}

	const response = await ctx.post(`./ocs/v2.php/apps/libresign/api/v1/policies/system/${FOOTER_POLICY_KEY}`, {
		data: {
			value,
			allowChildOverride: true,
		},
		failOnStatusCode: false,
	})

	expect(response.status(), `setSystemFooterPolicy: expected 200 but got ${response.status()}`).toBe(200)
}

let adminContext: APIRequestContext
let originalFooterPolicy: string | null

test.beforeEach(async () => {
	adminContext = await makeAdminContext()
	originalFooterPolicy = await getSystemFooterPolicy(adminContext)
	await setSystemFooterPolicy(adminContext, FOOTER_DISABLED_VALUE)
})

test.afterEach(async () => {
	await setSystemFooterPolicy(adminContext, originalFooterPolicy)
	await adminContext.dispose()
})

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
	await setAppConfig(page.request, 'libresign', 'signature_engine', 'PhpNative')
	await deleteAppConfig(page.request, 'libresign', 'tsa_url')

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

	// Keep the browser unauthenticated before opening a public sign link.
	// This avoids logout redirects to absolute hosts that may differ per environment.
	await page.context().clearCookies();
	await page.goto('about:blank');

	const email = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')
	const signLink = extractSignLink(email.Text)
	if (!signLink) throw new Error('Sign link not found in email')
	await page.goto(signLink);
	const openSignButton = page.getByRole('button', { name: 'Sign the document.' }).first()
	if (await openSignButton.isVisible().catch(() => false)) {
		await openSignButton.click();
	}
	const emailTextbox = page.getByRole('textbox', { name: 'Email' }).first()
	if (await emailTextbox.isVisible().catch(() => false)) {
		await emailTextbox.click();
		await emailTextbox.fill('signer01@libresign.coop');
		await page.getByRole('button', { name: 'Send verification code' }).click();
	}

	const tokenEmail = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: Code to sign file', { timeout: 60_000 })
	const token = extractTokenFromEmail(tokenEmail.Text)
	if (!token) throw new Error('Token not found in email')
	await page.getByRole('textbox', { name: 'Enter your code' }).click();
	await page.getByRole('textbox', { name: 'Enter your code' }).fill(token);
	await page.getByRole('button', { name: 'Validate code' }).click();

	await expect(page.getByRole('heading', { name: 'Signature confirmation' })).toBeVisible();
	await expect(page.getByText('Step 3 of 3 - Signature')).toBeVisible();
	await expect(page.getByText('Your identity has been')).toBeVisible();
	await expect(page.getByText('You can now sign the document.')).toBeVisible();
	const signResponsePromise = page.waitForResponse((response) =>
			response.request().method() === 'POST'
			&& response.url().includes('/apps/libresign/api/v1/sign/'),
		)
	await page.getByRole('button', { name: 'Sign document' }).click();
	const signResponse = await signResponsePromise
	const signResponseBody = await signResponse.text()
	expect(
		signResponse.ok(),
		`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`,
	).toBeTruthy()
	await expect(page.getByText('This document is valid')).toBeVisible();
	await expect(page.getByText('Congratulations you have')).toBeVisible();
	await expect(page.getByRole('button', { name: 'Sign the document.' })).not.toBeVisible();
});
