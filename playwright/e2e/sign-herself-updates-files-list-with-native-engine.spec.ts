/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'
import { useFooterPolicyGuard } from '../support/system-policies'

useFooterPolicyGuard()

async function sortByCreatedAtDescending(page: Page) {
	const createdAtTh = page.getByRole('columnheader', { name: 'Created at' })
	const sortDirection = await createdAtTh.evaluate((element: HTMLElement) => element.ariaSort)
	if (sortDirection !== 'descending') {
		await page.getByRole('button', { name: 'Created at' }).click()
		if (sortDirection === 'none') {
			await page.getByRole('button', { name: 'Created at' }).click()
		}
	}
}

test('updates files list status after signing with native engine', async ({ page }) => {
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

	await setAppConfig(page.request, 'libresign', 'signature_engine', 'PhpNative')
	await setAppConfig(
		page.request,
		'libresign',
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } } },
			{ name: 'email', enabled: false, mandatory: false },
		]),
	)

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Account').fill('admin')
	await page.getByText('admin@email.tld').click()
	await page.getByRole('button', { name: 'Save' }).click()
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	await page.locator('#fileslist').getByRole('link', { name: 'Files' }).click()
	await sortByCreatedAtDescending(page)

	const uniqueName = `native-status-sync-${Date.now()}.pdf`
	const firstRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: 'small_valid' })
		.first()
	await firstRow.getByRole('button', { name: 'Actions' }).click()
	await page.getByRole('menuitem', { name: 'Rename' }).click()
	await page.getByLabel('File name').fill(uniqueName)
	await page.getByLabel('File name').press('Enter')

	const targetRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: uniqueName })
	await expect(targetRow.locator('.status-chip__text')).toHaveText('Ready to sign')

	await targetRow.getByRole('button', { name: 'Actions' }).click()
	await page.getByRole('menuitem', { name: 'Sign' }).click()
	await page.waitForURL('**/f/sign/**/pdf')
	const signButton = page.getByRole('button', { name: 'Sign the document.' })
	await expect(signButton).toBeVisible()
		await signButton.click()
		const signResponsePromise = page.waitForResponse((response) =>
			response.request().method() === 'POST'
			&& response.url().includes('/apps/libresign/api/v1/sign/'),
		)
		await page.getByRole('button', { name: 'Sign document' }).click()
		const signResponse = await signResponsePromise
		const signResponseBody = await signResponse.text()
		expect(
			signResponse.ok(),
			`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`,
		).toBeTruthy()
		await expect(page.getByText('This document is valid')).toBeVisible()

		await page.locator('#fileslist').getByRole('link', { name: 'Files' }).click()
		await expect(targetRow.locator('.status-chip__text')).toHaveText('Signed')
})
