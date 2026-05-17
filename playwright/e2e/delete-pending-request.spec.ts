/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'

test('delete pending signature request', async ({ page }) => {
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
			{ name: 'account', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } } },
			{ name: 'email', enabled: false, mandatory: false },
		]),
	)

	await page.goto('./apps/libresign')
	await expect(page.getByRole('button', { name: 'Upload from URL' })).toBeVisible({ timeout: 20000 })
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Account').fill('a')
	await page.getByRole('option', { name: 'admin@email.tld' }).click()
	await page.getByRole('button', { name: 'Save' }).click()
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// Navigate to the Files list and ensure it is sorted by Created at, newest first
	await page.locator('#fileslist').getByRole('link', { name: 'Files' }).click()
	const createdAtTh = page.getByRole('columnheader', { name: 'Created at' })
	const sortDirection = await createdAtTh.evaluate((el: HTMLElement) => el.ariaSort)
	if (sortDirection !== 'descending') {
		await page.getByRole('button', { name: 'Created at' }).click()
		if (sortDirection === 'none') {
			// Column was sortable but not active: first click set it to ascending, one more for descending
			await page.getByRole('button', { name: 'Created at' }).click()
		}
	}

	// The most recently uploaded document is first — rename it to a unique name
	// so it can be unambiguously identified regardless of other documents in the list.
	// NcActionButton inside NcActions renders as role="menuitem", not role="button".
	const uniqueName = `delete-pending-test-${Date.now()}.pdf`
	const firstRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: 'small_valid' })
		.first()
	await firstRow.getByRole('button', { name: 'Actions' }).click()
	await page.getByRole('menuitem', { name: 'Rename' }).click()
	await page.getByLabel('File name').fill(uniqueName)
	await page.getByLabel('File name').press('Enter')

	// Find the row by its unique name and assert the status
	const targetRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: uniqueName })
	await expect(targetRow).toBeVisible({ timeout: 20000 })
	await expect(targetRow.locator('.status-chip__text')).toHaveText('Ready to sign')

	// Delete it
	await targetRow.getByRole('button', { name: 'Actions' }).click()
	await page.getByRole('menuitem', { name: 'Delete' }).click()

	// Confirm the deletion in the dialog
	await expect(page.getByRole('dialog', { name: 'Confirm' })).toBeVisible()
	await expect(page.getByText('The signature request will be deleted. Do you confirm this action?')).toBeVisible()
	await Promise.all([
		page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/apps/libresign/api/v1/file/file_id/') && response.ok()),
		page.getByRole('button', { name: 'Ok' }).click(),
	])

	// The list updates asynchronously after the backend deletion completes.
	await page.reload()
	await expect(targetRow).toBeHidden({ timeout: 20000 })
})

