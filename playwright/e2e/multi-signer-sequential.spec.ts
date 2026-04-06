/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, deleteAppConfig, ensureJavaDependenciesConfigured, setAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink } from '../support/mailpit'

async function addEmailSigner(
	page: Page,
	email: string,
	name: string,
) {
	await page.getByRole('button', { name: 'Add signer' }).click()
	const emailInput = page.getByPlaceholder('Email')
	await emailInput.click()
	await emailInput.pressSequentially(email, { delay: 50 })
	const option = page.getByRole('option', { name: email })
	await expect(option).toBeVisible({ timeout: 10_000 })
	await option.click()
	const signerNameInput = page.getByRole('textbox', { name: 'Signer name' })
	await expect(signerNameInput).toBeVisible()
	await signerNameInput.fill(name)
	await page.getByRole('button', { name: 'Save' }).click()
}

test('request signatures from two signers in sequential order', async ({ page }) => {
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
	await ensureJavaDependenciesConfigured(page.request)

	await setAppConfig(
		page.request,
		'libresign',
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
		]),
	)
	await setAppConfig(page.request, 'libresign', 'signature_engine', 'PhpNative')
	await deleteAppConfig(page.request, 'libresign', 'tsa_url')

	const mailpit = createMailpitClient()
	await mailpit.deleteMessages()

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()

	// Add first signer — only email method is active, so the field appears directly (no tabs)
	await addEmailSigner(page, 'signer01@libresign.coop', 'Signer 01')

	// Add second signer
	await addEmailSigner(page, 'signer02@libresign.coop', 'Signer 02')

	// Enable sequential signing.
	// The checkbox input is hidden by CSS; click the visible label text to toggle it.
	await expect(page.getByLabel('Sign in order')).toBeVisible()
	await page.getByText('Sign in order').click()
	await expect(page.getByLabel('Sign in order')).toBeChecked()

	// Send the signature request
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// In sequential mode only signer01 (order 1) gets the email immediately.
	// Proof: signer01's email arrives, but signer02's does NOT at this point.
	const email01 = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')

	const afterFirst = await mailpit.searchMessages({ query: 'subject:"LibreSign: There is a file for you to sign"' })
	expect(afterFirst.messages).toHaveLength(1)

	// Keep the browser unauthenticated before opening a public sign link.
	// This avoids logout redirects to absolute hosts that may differ per environment.
	await page.context().clearCookies()
	await page.goto('about:blank')

	// Signer01 signs via the link received in the email
	const signLink = extractSignLink(email01.Text)
	if (!signLink) throw new Error('Sign link not found in email')
	await page.goto(signLink)
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByRole('button', { name: 'Sign document' }).click()
	await page.waitForURL('**/validation/**')
	await expect(page.getByText('This document is valid')).toBeVisible()
	// Signer01 signed; signer02 is still waiting (sequential mode proof at this point)
	await expect(page.getByText('Signer 01')).toBeVisible()
	await page.getByRole('button', { name: 'Expand details of Signer 01' }).click()
	await page.getByRole('button', { name: 'Expand validation status', exact: true }).click();
	await page.getByRole('link', { name: 'Document integrity verified' }).click();
	await page.getByRole('button', { name: 'Expand document certification', exact: true }).click();
	await page.getByRole('link', { name: 'Document has not been' }).click();

	await expect(page.getByText('Signer 02')).toBeVisible()
	await expect(page.getByText('Not signed yet')).toBeVisible()

	// Now that signer01 has signed, signer02 must receive their notification.
	const email02 = await waitForEmailTo(mailpit, 'signer02@libresign.coop', 'LibreSign: There is a file for you to sign')

	const afterSecond = await mailpit.searchMessages({ query: 'subject:"LibreSign: There is a file for you to sign"' })
	expect(afterSecond.messages).toHaveLength(2)

	// Signer02 signs via their email link.
	// The admin is still logged out from the signer01 step, so this is unauthenticated.
	const signLink02 = extractSignLink(email02.Text)
	if (!signLink02) throw new Error('Sign link for signer02 not found in email')
	await page.goto(signLink02)
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByRole('button', { name: 'Sign document' }).click()
	await page.waitForURL('**/validation/**')
	await expect(page.getByText('This document is valid')).toBeVisible()

	// Both signers must appear as signed in the final validation view.
	await expect(page.getByText('Signer 01')).toBeVisible()
	await expect(page.getByText('Signer 02')).toBeVisible()
	await expect(page.getByText('Not signed yet')).not.toBeVisible()
})
