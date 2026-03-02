/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo } from '../support/mailpit'

test('request signatures from two signers in parallel', async ({ page }) => {
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

	// Add first signer — only email method is active, so the field appears directly (no tabs)
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Email').click()
	await page.getByPlaceholder('Email').fill('signer01@libresign.coop')
	await page.getByRole('option', { name: 'signer01@libresign.coop' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 01')
	await page.getByRole('button', { name: 'Save' }).click()
	// Wait for the form to close before opening a new one to avoid ambiguous Email locator
	await expect(page.getByPlaceholder('Email')).not.toBeVisible()

	// Add second signer
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Email').click()
	await page.getByPlaceholder('Email').fill('signer02@libresign.coop')
	await page.getByRole('option', { name: 'signer02@libresign.coop' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 02')
	await page.getByRole('button', { name: 'Save' }).click()

	// With 2+ signers the "Sign in order" switch must be visible and unchecked by default,
	// meaning parallel flow — both signers will be notified at the same time.
	const signInOrderSwitch = page.getByLabel('Sign in order')
	await expect(signInOrderSwitch).toBeVisible()
	await expect(signInOrderSwitch).not.toBeChecked()

	// Send the signature request
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// In parallel mode both signers are notified simultaneously.
	// Proof: wait for signer01's email, then verify that signer02's email also arrived.
	await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')
	await waitForEmailTo(mailpit, 'signer02@libresign.coop', 'LibreSign: There is a file for you to sign')

	// Both emails arrived — both signers were notified at the same time, confirming parallel mode.
	const result = await mailpit.searchMessages({ query: 'subject:"LibreSign: There is a file for you to sign"' })
	expect(result.messages).toHaveLength(2)
})
