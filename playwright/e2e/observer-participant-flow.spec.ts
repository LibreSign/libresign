/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { createMailpitClient, extractSignLink, extractValidationLink, waitForEmailTo } from '../support/mailpit'
import { login } from '../support/nc-login'
import { configureOpenSsl, ensureUserExists, setSystemPolicy } from '../support/nc-provisioning'
import { clickAddObserver, clickAddSigner, selectAccountSigner, selectEmailSigner, selectIdentifyMethodTab } from '../support/request-signature'
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

		await page.context().clearCookies()
		await page.goto('about:blank')
		await page.goto(`.${observerLink}`)
		await page.waitForURL('**/validation/**', { waitUntil: 'commit' })
		await expect(page).not.toHaveURL(/\/p\/sign\//)
		await expect(page.getByRole('button', { name: 'Sign', exact: true })).toHaveCount(0)
		await expect(page.locator('.container .logo img')).toBeVisible()
		await expect(page.locator('#validation-content')).toBeVisible()
		await expect(page.getByRole('heading', { name: 'Signers', exact: true })).toBeVisible()
		await expect(page.getByRole('heading', { name: 'Observers', exact: true })).toBeVisible()
		await expect(page.getByRole('button', { name: 'View document' })).toBeVisible()

		const pdfResponsePromise = page.context().waitForEvent('response', (response) => (
			response.url().includes('/apps/libresign/p/pdf/')
			&& response.request().resourceType() !== 'preflight'
		))
		const popupPromise = page.waitForEvent('popup')
		await page.getByRole('button', { name: 'View document' }).click()
		const [popup, pdfResponse] = await Promise.all([popupPromise, pdfResponsePromise])
		expect(pdfResponse.status()).toBe(200)
		expect(pdfResponse.headers()['content-type'] ?? '').toMatch(/pdf/i)
		await expect(popup).toHaveURL(/\/apps\/libresign\/p\/pdf\//)
		await expect(popup).not.toHaveURL(/\/login/)
	} finally {
		await login(
			page.request,
			process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
			process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
		)
		await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(false))
	}
})

test('email observer receives validation link when the signer uses account identification', async ({ page }) => {
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
				{ name: 'account', enabled: true, requirement: 'optional', signatureMethods: { clickToSign: { enabled: true } } },
				{ name: 'email', enabled: true, requirement: 'optional', signatureMethods: { clickToSign: { enabled: true } } },
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
		await selectIdentifyMethodTab(page, 'Account')
		await selectAccountSigner(page, 'a')
		await page.getByRole('button', { name: 'Save' }).click()

		await clickAddObserver(page)
		await selectIdentifyMethodTab(page, 'Email')
		await selectEmailSigner(page, 'observer01@libresign.coop')
		await page.getByRole('textbox', { name: 'Observer name' }).fill('Observer 01')
		await page.getByRole('button', { name: 'Save' }).click()

		const observersSection = page.locator('.participants-section').filter({
			has: page.getByRole('heading', { name: 'Observers', exact: true }),
		})
		await expect(observersSection.getByText('Observer 01', { exact: true })).toBeVisible()

		await page.getByRole('button', { name: 'Request signatures' }).click()
		await page.getByRole('button', { name: 'Send' }).click()

		const observerEmail = await waitForEmailTo(
			mailpit,
			'observer01@libresign.coop',
			'LibreSign: A document is ready for signature',
		)

		const observerLink = extractValidationLink(observerEmail.Text || observerEmail.HTML || '')
		expect(observerLink).toBeTruthy()
		expect(observerLink).toMatch(/validation\//)
		expect(extractSignLink(observerEmail.Text || observerEmail.HTML || '')).toBeNull()

		await page.context().clearCookies()
		await page.goto('about:blank')
		await page.goto(`.${observerLink}`)
		await page.waitForURL('**/validation/**', { waitUntil: 'commit' })
		await expect(page).not.toHaveURL(/\/p\/sign\//)
		await expect(page).not.toHaveURL(/\/login/)
		await expect(page.getByRole('button', { name: 'Sign', exact: true })).toHaveCount(0)
	} finally {
		await login(
			page.request,
			process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
			process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
		)
		await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(false))
	}
})

test('authenticated observer opens the request in read-only mode', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	const observerUser = 'observer-readonly-e2e'
	const observerPassword = '123456'

	await login(page.request, adminUser, adminPassword)
	await ensureUserExists(page.request, observerUser, observerPassword)

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(true))
	await setSystemPolicy(
		page.request,
		'identify_methods',
		JSON.stringify({
			can_create_account: false,
			factors: [
				{ name: 'account', enabled: true, requirement: 'optional', signatureMethods: { clickToSign: { enabled: true } } },
				{ name: 'email', enabled: true, requirement: 'optional', signatureMethods: { clickToSign: { enabled: true } } },
			],
		}),
	)

	try {
		await page.goto('./apps/libresign')
		await page.getByRole('button', { name: 'Upload from URL' }).click()
		await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
		await page.getByRole('button', { name: 'Send' }).click()

		await clickAddSigner(page)
		await selectIdentifyMethodTab(page, 'Account')
		await selectAccountSigner(page, 'a')
		await page.getByRole('button', { name: 'Save' }).click()

		await clickAddObserver(page)
		await selectIdentifyMethodTab(page, 'Account')
		await selectAccountSigner(page, observerUser, new RegExp(observerUser, 'i'))
		await page.getByRole('textbox', { name: 'Observer name' }).fill('Readonly Observer')
		await page.getByRole('button', { name: 'Save' }).click()

		await expect(page.locator('.participants-section').filter({
			has: page.getByRole('heading', { name: 'Observers', exact: true }),
		}).getByText('Readonly Observer', { exact: true })).toBeVisible()

		await page.getByRole('button', { name: 'Request signatures' }).click()
		await page.getByRole('button', { name: 'Send' }).click()

		await page.context().clearCookies()
		await login(page.request, observerUser, observerPassword)
		await page.goto('./apps/libresign')

		const fileRow = page.getByRole('row').filter({ hasText: /small_valid|Readonly|Observer/i }).first()
		await expect(fileRow.or(page.locator('.files-list__row').first())).toBeVisible({ timeout: 20_000 })
		const clickTarget = await fileRow.isVisible().catch(() => false)
			? fileRow
			: page.locator('.files-list__row, [data-cy-files-list-row-name], .file-row').first()
		await clickTarget.click()

		const sidebar = page.locator('#request-signature-tab, .app-sidebar')
		await expect(sidebar.getByRole('heading', { name: 'Signers', exact: true })).toBeVisible({ timeout: 15_000 })
		await expect(sidebar.getByRole('heading', { name: 'Observers', exact: true })).toBeVisible()
		await expect(sidebar.getByRole('button', { name: 'Sign document', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'Add', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'Add signer', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'Request signatures', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'Send reminder', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'Send notification', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'Setup signature positions', exact: true })).toHaveCount(0)
		await expect(sidebar.getByRole('button', { name: 'View signature positions', exact: true }).or(sidebar.getByRole('button', { name: 'Open file', exact: true }))).toBeVisible()
	} finally {
		await login(page.request, adminUser, adminPassword)
		await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(false))
	}
})
