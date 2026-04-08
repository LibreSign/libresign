/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl } from '../support/nc-provisioning'

test('new signature request opens LibreSign tab and does not duplicate file row', async ({ page }) => {
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

	const uniqueName = `libresign-upload-${Date.now()}.pdf`
	const pdfResponse = await page.request.get('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf', {
		failOnStatusCode: true,
	})
	const pdfBuffer = Buffer.from(await pdfResponse.body())

	await page.goto('./apps/files')

	await page.getByRole('button', { name: 'New' }).click()
	const newSignatureRequestEntry = page.getByText('New signature request', { exact: true })
	await expect(newSignatureRequestEntry).toBeVisible()
	const fileChooserPromise = page.waitForEvent('filechooser')
	await newSignatureRequestEntry.click()
	const chooser = await fileChooserPromise
	await chooser.setFiles({
		name: uniqueName,
		mimeType: 'application/pdf',
		buffer: pdfBuffer,
	})

	const libresignTab = page.getByRole('tab', { name: 'LibreSign' })
	await expect(libresignTab).toHaveAttribute('aria-selected', 'true')
	await expect(page.getByRole('button', { name: 'Add signer' })).toBeVisible()

	const filesTable = page.getByRole('table', {
		name: /List of your files and folders/i,
	})

	const matchingFileCheckboxes = filesTable.getByRole('checkbox', {
		name: `Toggle selection for file "${uniqueName}"`,
	})

	await expect(matchingFileCheckboxes).toHaveCount(1, { timeout: 15000 })
})
