/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo, extractSignLink } from '../support/mailpit'

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

	// Add first signer via email tab
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByRole('tab', { name: 'Email' }).click()
	await page.getByPlaceholder('Email').fill('signer01@libresign.coop')
	await page.getByRole('option', { name: 'signer01@libresign.coop' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 01')
	await page.getByRole('button', { name: 'Save' }).click()

	// Add second signer via email tab
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByRole('tab', { name: 'Email' }).click()
	await page.getByPlaceholder('Email').fill('signer02@libresign.coop')
	await page.getByRole('option', { name: 'signer02@libresign.coop' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 02')
	await page.getByRole('button', { name: 'Save' }).click()

	// Enable sequential signing — the switch must be accessible by role="switch"
	const signInOrderSwitch = page.getByRole('switch', { name: 'Sign in order' })
	await expect(signInOrderSwitch).toBeVisible()
	await signInOrderSwitch.click()
	await expect(signInOrderSwitch).toBeChecked()

	// Send the signature request
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// In sequential mode only signer01 (order 1) gets the email immediately.
	// Proof: signer01's email arrives, but signer02's does NOT at this point.
	const email01 = await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')

	const afterFirst = await mailpit.searchMessages({ query: 'subject:"LibreSign: There is a file for you to sign"' })
	expect(afterFirst.messages).toHaveLength(1)

	// Signer01 signs via the link received in the email
	const signLink = extractSignLink(email01.Text)
	if (!signLink) throw new Error('Sign link not found in email')
	await page.goto(signLink)
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByRole('button', { name: 'Sign document' }).click()
	await page.waitForURL('**/validation/**')
	await expect(page.getByText('This document is valid')).toBeVisible()

	// Now that signer01 has signed, signer02 must receive their notification.
	await waitForEmailTo(mailpit, 'signer02@libresign.coop', 'LibreSign: There is a file for you to sign')

	const afterSecond = await mailpit.searchMessages({ query: 'subject:"LibreSign: There is a file for you to sign"' })
	expect(afterSecond.messages).toHaveLength(2)
})
