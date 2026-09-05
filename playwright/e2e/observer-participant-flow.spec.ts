/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { createMailpitClient, extractSignLink, extractValidationLink, waitForEmailTo } from '../support/mailpit'
import { login } from '../support/nc-login'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { clickAddObserver, clickAddSigner, selectEmailSigner } from '../support/request-signature'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

test('observer receives validation link and cannot enter signing flow', async ({ page }) => {
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

	await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(true))
	await setSystemPolicy(page.request, 'make_validation_url_private', '0')
	await setSystemPolicy(
		page.request,
		'identify_methods',
		JSON.stringify({
			can_create_account: false,
			factors: [
				{ name: 'account', enabled: false, requirement: 'optional' },
				{ name: 'email', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
			],
		}),
	)

	try {
		const mailpit = createMailpitClient()
		await mailpit.deleteMessages()

		await page.goto('./apps/libresign')
		await page.getByRole('button', { name: 'Upload from URL' }).click()
		await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
		await page.getByRole('button', { name: 'Send' }).click()

		await clickAddSigner(page)
		await selectEmailSigner(page, 'signer01@libresign.coop')
		await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 01')
		await page.getByRole('button', { name: 'Save' }).click()

		await clickAddObserver(page)
		await selectEmailSigner(page, 'observer01@libresign.coop')
		await page.getByRole('textbox', { name: 'Observer name' }).fill('Observer 01')
		await page.getByRole('button', { name: 'Save' }).click()

		const signersSection = page.locator('.participants-section').filter({
			has: page.getByRole('heading', { name: 'Signers', exact: true }),
		})
		const observersSection = page.locator('.participants-section').filter({
			has: page.getByRole('heading', { name: 'Observers', exact: true }),
		})
		await expect(signersSection.getByText('Signer 01', { exact: true })).toBeVisible()
		await expect(observersSection.getByText('Observer 01', { exact: true })).toBeVisible()

		await page.getByRole('button', { name: 'Request signatures' }).click()
		await page.getByRole('button', { name: 'Send' }).click()

		const signerEmail = await waitForEmailTo(
			mailpit,
			'signer01@libresign.coop',
			'LibreSign: A document is ready for your signature',
		)
		const observerEmail = await waitForEmailTo(
			mailpit,
			'observer01@libresign.coop',
			'LibreSign: A document is ready for signature',
		)

		const signerLink = extractSignLink(signerEmail.Text || signerEmail.HTML || '')
		expect(signerLink).toBeTruthy()
		expect(signerLink).toMatch(/\/p\/sign\//)

		const observerLink = extractValidationLink(observerEmail.Text || observerEmail.HTML || '')
		expect(observerLink).toBeTruthy()
		expect(observerLink).toMatch(/validation\//)
		expect(extractSignLink(observerEmail.Text || observerEmail.HTML || '')).toBeNull()

		await page.goto(`.${observerLink}`)
		await page.waitForURL('**/validation/**', { waitUntil: 'commit' })
		await expect(page).not.toHaveURL(/\/p\/sign\//)
		await expect(page.getByRole('button', { name: 'Sign', exact: true })).toHaveCount(0)
	} finally {
		await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(false))
	}
})
