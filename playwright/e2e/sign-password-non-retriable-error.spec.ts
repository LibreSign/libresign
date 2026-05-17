/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, deleteUserPfx, setSystemPolicy } from '../support/nc-provisioning'

async function prepareSignFlow(page: Parameters<typeof test>[1] extends (args: infer T) => any ? T['page'] : never, adminUser: string) {
	await page.goto('./apps/libresign')

	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Account').fill(adminUser)
	await page.getByText('admin@email.tld').click()
	await page.getByRole('button', { name: 'Save' }).click()
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Sign document' }).click()
	await page.getByRole('button', { name: 'Define a password and sign the document.' }).click()
	await page.getByLabel('Enter a password').fill('Password1234')
	await page.getByRole('button', { name: 'Confirm' }).click()
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByLabel('Signature password').fill('Password1234')
}

async function bootstrapAdminCertificate(page: Parameters<typeof test>[1] extends (args: infer T) => any ? T['page'] : never) {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)

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
			{ name: 'account', enabled: true, mandatory: true, signatureMethods: { password: { enabled: true } } },
			{ name: 'email', enabled: false, mandatory: false },
		]),
	)

	await setSystemPolicy(
		page.request,
		'crl_external_validation_enabled',
		'1',
	)

	await deleteUserPfx(page.request, adminUser, adminPassword)

	return { adminUser }
}

test('switches from blocked (enabled) to normal (disabled) without extra scenarios', async ({ page }) => {
	const { adminUser } = await bootstrapAdminCertificate(page)
	await prepareSignFlow(page, adminUser)

	const signRoute = '**/ocs/v2.php/apps/libresign/api/v1/sign/**'

	const blockedHandler = async (route) => {
		await route.fulfill({
			status: 422,
			contentType: 'application/json',
			body: JSON.stringify({
				ocs: {
					meta: {
						status: 'failure',
						statuscode: 422,
						message: 'Certificate revocation status could not be verified',
					},
					data: {
						action: 0,
						errors: [{
							code: 422,
							message: 'Certificate revocation status could not be verified',
						}],
					},
				},
			}),
		})
	}

	await page.route(signRoute, blockedHandler)

	await page.getByRole('button', { name: 'Sign document' }).click()

	await expect(page.getByLabel('Signature password')).toBeHidden()
	await expect(page.getByRole('button', { name: 'Sign the document.' })).toBeHidden()
	await expect(page.getByRole('button', { name: 'Try signing again' })).toBeVisible()
	await expect(page.locator('.button-wrapper').getByText('Certificate revocation status could not be verified').first()).toBeVisible()
	await page.screenshot({ path: '/tmp/playwright-results/non-retriable-blocked-ui.png', fullPage: true })

	await setSystemPolicy(
		page.request,
		'crl_external_validation_enabled',
		'0',
	)

	await page.unroute(signRoute, blockedHandler)

	await page.route(signRoute, async (route) => {
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				ocs: {
					meta: {
						status: 'ok',
						statuscode: 200,
						message: null,
					},
					data: {
						action: 0,
						status: 'signed',
					},
				},
			}),
		})
	})

	await page.getByRole('button', { name: 'Try signing again' }).click()
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByLabel('Signature password').fill('Password1234')
	await page.getByRole('button', { name: 'Sign document' }).click()

	await expect(page.getByText('Signing is blocked until the certificate validation issue is resolved.')).toBeHidden()
	await expect(page.getByRole('button', { name: 'Try signing again' })).toBeHidden()
	await page.screenshot({ path: '/tmp/playwright-results/non-retriable-normal-ui.png', fullPage: true })
})
